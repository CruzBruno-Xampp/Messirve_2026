<?php

namespace App\Services;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class NormalizadorCsvCatalogo
{
    /*
     * Orden definitivo de las columnas del CSV normalizado.
     *
     * Todos los Excel, independientemente del proveedor,
     * terminarán convertidos a este mismo formato.
     */
    private const COLUMNAS_CSV = [
        'codigo_proveedor',
        'codigo_fabricante',
        'codigo_barra',
        'descripcion_proveedor',
        'linea_proveedor',
        'tipo_producto_proveedor',
        'grupo_proveedor',
        'subgrupo_proveedor',
        'precio_lista',
        'descuento_producto',
        'precio_neto',
    ];

    /*
     * Campos que contienen códigos.
     *
     * Se tratan como texto para evitar perder ceros iniciales.
     */
    private const CAMPOS_CODIGO = [
        'codigo_proveedor',
        'codigo_fabricante',
        'codigo_barra',
    ];

    /*
     * Solo almacenamos como máximo 100 errores por archivo
     * para evitar llenar la memoria con archivos incorrectos.
     */
    private const MAX_ERRORES = 100;

    /**
     * Genera un CSV normalizado desde una hoja del Excel.
     *
     * @param string $rutaExcel Ruta del archivo XLS o XLSX.
     * @param string $nombreHoja Nombre de la hoja seleccionada.
     * @param int $filaEncabezado Fila confirmada como encabezado.
     * @param array $mapeo Relación entre campos y columnas del Excel.
     */
    public function generarCsv(
        string $rutaExcel,
        string $nombreHoja,
        int $filaEncabezado,
        array $mapeo
    ): array {
        if (!is_file($rutaExcel)) {
            throw new InvalidArgumentException(
                'No se encontró el archivo Excel que se quiere convertir.'
            );
        }

        if ($filaEncabezado < 1) {
            throw new InvalidArgumentException(
                'La fila de encabezado seleccionada no es válida.'
            );
        }

        // Limpia y valida las letras de las columnas recibidas.
        $mapeo = $this->normalizarMapeo($mapeo);

        /*
         * Para importar un producto necesitamos obligatoriamente
         * una descripción y un precio de lista.
         */
        if ($mapeo['descripcion_proveedor'] === null) {
            throw new InvalidArgumentException(
                'Debe seleccionar la columna correspondiente a la descripción.'
            );
        }

        if ($mapeo['precio_lista'] === null) {
            throw new InvalidArgumentException(
                'Debe seleccionar la columna correspondiente al precio de lista.'
            );
        }

        /*
         * PhpSpreadsheet reconoce automáticamente si el archivo
         * es XLS o XLSX.
         */
        $lector = IOFactory::createReaderForFile($rutaExcel);

        /*
         * En este caso conservamos formatos porque pueden ser necesarios
         * para reconocer porcentajes o códigos con ceros iniciales.
         */
        $lector->setReadDataOnly(false);

        // Carga únicamente la hoja elegida por el usuario.
        $lector->setLoadSheetsOnly([$nombreHoja]);

        $libro = $lector->load($rutaExcel);

        $archivoCsv = null;
        $rutaCsv = null;
        $ocurrioError = false;

        try {
            $hoja = $libro->getSheetByName($nombreHoja);

            if (!$hoja instanceof Worksheet) {
                throw new InvalidArgumentException(
                    "No se encontró la hoja seleccionada: {$nombreHoja}."
                );
            }

            /*
             * Los CSV normalizados se guardarán temporalmente
             * dentro de storage/app/importaciones_catalogos.
             */
            $directorio = storage_path(
                'app/importaciones_catalogos/normalizados'
            );

            File::ensureDirectoryExists($directorio);

            $nombreSeguroHoja = Str::slug($nombreHoja);

            if ($nombreSeguroHoja === '') {
                $nombreSeguroHoja = 'hoja';
            }

            $nombreCsv = sprintf(
                'catalogo_%s_%s.csv',
                $nombreSeguroHoja,
                Str::uuid()
            );

            $rutaCsv = $directorio . DIRECTORY_SEPARATOR . $nombreCsv;

            $archivoCsv = fopen($rutaCsv, 'wb');

            if ($archivoCsv === false) {
                throw new RuntimeException(
                    'No se pudo crear el archivo CSV normalizado.'
                );
            }

            /*
             * Escribe los encabezados estándares de Messirve.
             *
             * Se utiliza punto y coma como separador porque es común
             * en archivos CSV utilizados en Argentina.
             */
            $this->escribirFilaCsv(
                $archivoCsv,
                self::COLUMNAS_CSV
            );

            $ultimaFila = $hoja->getHighestDataRow();

            // Los productos comienzan después del encabezado.
            $primeraFilaDatos = $filaEncabezado + 1;

            $filasLeidas = 0;
            $filasExportadas = 0;
            $filasOmitidas = 0;
            $errores = [];

            for (
                $numeroFila = $primeraFilaDatos;
                $numeroFila <= $ultimaFila;
                $numeroFila++
            ) {
                $filasLeidas++;

                /*
                 * Extrae los valores utilizando el mapeo confirmado
                 * por el dueño de la pinturería.
                 */
                $valoresOriginales = $this->obtenerValoresFila(
                    $hoja,
                    $numeroFila,
                    $mapeo
                );

                // Las filas completamente vacías no se importan.
                if ($this->filaEstaVacia($valoresOriginales)) {
                    continue;
                }

                $filaNormalizada = $this->normalizarFila(
                    $valoresOriginales
                );

                /*
                 * Evita importar nuevamente un encabezado repetido
                 * dentro de una hoja extensa.
                 */
                if ($this->pareceEncabezadoRepetido($filaNormalizada)) {
                    $filasOmitidas++;
                    continue;
                }

                if ($filaNormalizada['descripcion_proveedor'] === '') {
                    $filasOmitidas++;

                    $this->agregarError(
                        $errores,
                        $numeroFila,
                        'La fila no tiene una descripción de producto.'
                    );

                    continue;
                }

                if ($filaNormalizada['precio_lista'] === null) {
                    $filasOmitidas++;

                    $this->agregarError(
                        $errores,
                        $numeroFila,
                        'El precio de lista está vacío o no tiene un formato válido.'
                    );

                    continue;
                }

                if ((float) $filaNormalizada['precio_lista'] <= 0) {
                    $filasOmitidas++;

                    $this->agregarError(
                        $errores,
                        $numeroFila,
                        'El precio de lista debe ser mayor que cero.'
                    );

                    continue;
                }

                /*
                 * Respeta exactamente el orden definido
                 * en COLUMNAS_CSV.
                 */
                $filaCsv = [];

                foreach (self::COLUMNAS_CSV as $campo) {
                    $filaCsv[] = $filaNormalizada[$campo];
                }

                $this->escribirFilaCsv(
                    $archivoCsv,
                    $filaCsv
                );

                $filasExportadas++;
            }

            return [
                'ruta_csv' => $rutaCsv,
                'nombre_archivo' => $nombreCsv,
                'hoja' => $nombreHoja,
                'fila_encabezado' => $filaEncabezado,
                'filas_leidas' => $filasLeidas,
                'filas_exportadas' => $filasExportadas,
                'filas_omitidas' => $filasOmitidas,
                'errores' => $errores,
            ];
        } catch (Throwable $excepcion) {
            $ocurrioError = true;

            throw $excepcion;
        } finally {
            /*
             * Cierra el CSV aunque ocurra un error durante el proceso.
             */
            if (is_resource($archivoCsv)) {
                fclose($archivoCsv);
            }

            // Libera el Excel de la memoria.
            $libro->disconnectWorksheets();
            unset($libro);

            /*
             * Si la conversión falló, elimina el CSV incompleto.
             */
            if (
                $ocurrioError
                && $rutaCsv !== null
                && is_file($rutaCsv)
            ) {
                File::delete($rutaCsv);
            }
        }
    }

    /**
     * Valida las columnas enviadas desde el formulario de mapeo.
     */
    private function normalizarMapeo(array $mapeo): array
    {
        $resultado = array_fill_keys(
            self::COLUMNAS_CSV,
            null
        );

        foreach (self::COLUMNAS_CSV as $campo) {
            $columna = $mapeo[$campo] ?? null;

            if ($columna === null || trim((string) $columna) === '') {
                continue;
            }

            $columna = strtoupper(trim((string) $columna));

            /*
             * Una columna de Excel debe tener una letra válida:
             * A, B, C, AA, AB, etc.
             */
            if (preg_match('/^[A-Z]{1,3}$/', $columna) !== 1) {
                throw new InvalidArgumentException(
                    "La columna {$columna} no es válida."
                );
            }

            $numeroColumna = Coordinate::columnIndexFromString(
                $columna
            );

            /*
             * Excel permite como máximo 16.384 columnas,
             * siendo XFD la última columna posible.
             */
            if ($numeroColumna > 16384) {
                throw new InvalidArgumentException(
                    "La columna {$columna} supera el límite permitido por Excel."
                );
            }

            $resultado[$campo] = $columna;
        }

        return $resultado;
    }

    /**
     * Obtiene los valores originales de una fila usando
     * las columnas seleccionadas por el usuario.
     */
    private function obtenerValoresFila(
        Worksheet $hoja,
        int $numeroFila,
        array $mapeo
    ): array {
        $resultado = [];

        foreach (self::COLUMNAS_CSV as $campo) {
            $columna = $mapeo[$campo];

            if ($columna === null) {
                $resultado[$campo] = null;
                continue;
            }

            $resultado[$campo] = $this->obtenerValorCelda(
                $hoja,
                $columna,
                $numeroFila,
                $campo
            );
        }

        return $resultado;
    }

    /**
     * Obtiene el valor real de una celda.
     *
     * También intenta calcular el resultado cuando la celda
     * contiene una fórmula.
     */
    private function obtenerValorCelda(
        Worksheet $hoja,
        string $columna,
        int $numeroFila,
        string $campo
    ): mixed {
        $celda = $hoja->getCell(
            $columna . $numeroFila
        );

        try {
            $valor = $celda->getCalculatedValue();
        } catch (Throwable) {
            // Si la fórmula no puede calcularse, usa el valor original.
            $valor = $celda->getValue();
        }

        /*
         * Los descuentos pueden estar almacenados como 0.10,
         * pero mostrarse en Excel como 10%.
         */
        if ($campo === 'descuento_producto') {
            $valorFormateado = trim(
                (string) $celda->getFormattedValue()
            );

            if (str_contains($valorFormateado, '%')) {
                return $valorFormateado;
            }

            return $valor;
        }

        /*
         * Los códigos se manejan como texto para evitar
         * notación científica o pérdida de ceros iniciales.
         */
        if (in_array($campo, self::CAMPOS_CODIGO, true)) {
            if ($celda->getDataType() === DataType::TYPE_STRING) {
                return $celda->getValue();
            }

            $formatoNumerico = $celda
                ->getStyle()
                ->getNumberFormat()
                ->getFormatCode();

            /*
             * Ejemplo: si el formato es 000000, el valor 123
             * debe conservarse como 000123.
             */
            if (preg_match('/^0+$/', $formatoNumerico) === 1) {
                return $celda->getFormattedValue();
            }
        }

        return $valor;
    }

    /**
     * Limpia y convierte todos los datos de una fila.
     */
    private function normalizarFila(array $fila): array
    {
        return [
            'codigo_proveedor' => $this->normalizarCodigo(
                $fila['codigo_proveedor']
            ),

            'codigo_fabricante' => $this->normalizarCodigo(
                $fila['codigo_fabricante']
            ),

            'codigo_barra' => $this->normalizarCodigo(
                $fila['codigo_barra']
            ),

            'descripcion_proveedor' => $this->normalizarTextoDato(
                $fila['descripcion_proveedor']
            ),

            'linea_proveedor' => $this->normalizarTextoDato(
                $fila['linea_proveedor']
            ),

            'tipo_producto_proveedor' => $this->normalizarTextoDato(
                $fila['tipo_producto_proveedor']
            ),

            'grupo_proveedor' => $this->normalizarTextoDato(
                $fila['grupo_proveedor']
            ),

            'subgrupo_proveedor' => $this->normalizarTextoDato(
                $fila['subgrupo_proveedor']
            ),

            /*
             * El precio se almacena con hasta seis decimales
             * para que coincida con detalle_catalogo.preciolista.
             */
            'precio_lista' => $this->normalizarNumero(
                $fila['precio_lista'],
                6
            ),

            /*
             * El descuento se guarda como porcentaje.
             * Por ejemplo: 10.00 representa un descuento del 10%.
             */
            'descuento_producto' => $this->normalizarPorcentaje(
                $fila['descuento_producto']
            ),

            /*
             * Precio neto opcional informado directamente por el proveedor.
             *
             * Por ejemplo, una columna "Precio c/desc". Si el Excel no la
             * trae, el valor queda NULL y el comparador podrá calcularlo
             * más adelante a partir del precio de lista y los descuentos.
             */
            'precio_neto' => $this->normalizarNumero(
                $fila['precio_neto'],
                6
            ),
        ];
    }

    /**
     * Normaliza códigos sin convertirlos a números decimales.
     */
    private function normalizarCodigo(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_int($valor)) {
            return (string) $valor;
        }

        if (is_float($valor)) {
            /*
             * Evita que un código de barras termine guardado
             * como 7.798018569567E+12.
             */
            if (floor($valor) === $valor) {
                return number_format(
                    $valor,
                    0,
                    '',
                    ''
                );
            }
        }

        $texto = trim((string) $valor);

        // Elimina espacios invisibles utilizados por Excel.
        $texto = str_replace("\u{00A0}", '', $texto);

        /*
         * Si Excel devuelve un código como 13.0,
         * se elimina únicamente la parte decimal.
         */
        if (preg_match('/^(\d+)[,.]0+$/', $texto, $coincidencia) === 1) {
            return $coincidencia[1];
        }

        return $texto;
    }

    /**
     * Limpia textos sin modificar su contenido real.
     */
    private function normalizarTextoDato(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $texto = trim((string) $valor);

        // Reemplaza espacios repetidos por uno solo.
        $texto = preg_replace(
            '/\s+/u',
            ' ',
            $texto
        ) ?? '';

        return trim($texto);
    }

    /**
     * Convierte números escritos con formato argentino
     * o internacional a un formato estándar.
     *
     * Ejemplos:
     * 9.243,32  -> 9243.320000
     * 9,243.32  -> 9243.320000
     * $ 9243,32 -> 9243.320000
     */
    private function normalizarNumero(
        mixed $valor,
        int $decimales
    ): ?string {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return number_format(
                (float) $valor,
                $decimales,
                '.',
                ''
            );
        }

        $texto = trim((string) $valor);

        $texto = str_replace(
            ["\u{00A0}", ' ', '$', '%'],
            '',
            $texto
        );

        // Conserva solamente números, coma, punto y signo negativo.
        $texto = preg_replace(
            '/[^0-9,.\-]/u',
            '',
            $texto
        ) ?? '';

        if ($texto === '' || $texto === '-') {
            return null;
        }

        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        /*
         * Si contiene coma y punto, el último símbolo encontrado
         * se considera el separador decimal.
         */
        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                // Formato argentino: 9.243,32
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                // Formato internacional: 9,243.32
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimaComa !== false) {
            // Formato decimal con coma: 9243,32
            $texto = str_replace(',', '.', $texto);
        } elseif (
            preg_match(
                '/^-?[1-9][0-9]{0,2}(?:\.[0-9]{3})+$/',
                $texto
            ) === 1
        ) {
            // Formato de miles: 9.243
            $texto = str_replace('.', '', $texto);
        }

        if (!is_numeric($texto)) {
            return null;
        }

        return number_format(
            (float) $texto,
            $decimales,
            '.',
            ''
        );
    }

    /**
     * Convierte descuentos a porcentajes estándares.
     *
     * Ejemplos:
     * 10%  -> 10.00
     * 0.10 -> 10.00
     * 10   -> 10.00
     */
    private function normalizarPorcentaje(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '0.00';
        }

        $teniaSimboloPorcentaje = is_string($valor)
            && str_contains($valor, '%');

        $numero = $this->normalizarNumero($valor, 6);

        if ($numero === null) {
            return '0.00';
        }

        $porcentaje = (float) $numero;

        /*
         * Excel puede guardar el 10% internamente como 0.10.
         * En ese caso se transforma a 10.
         */
        if (
            !$teniaSimboloPorcentaje
            && $porcentaje > 0
            && $porcentaje < 1
        ) {
            $porcentaje *= 100;
        }

        return number_format(
            $porcentaje,
            2,
            '.',
            ''
        );
    }

    /**
     * Comprueba si todos los campos seleccionados están vacíos.
     */
    private function filaEstaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Detecta encabezados que aparecen repetidos dentro del listado.
     */
    private function pareceEncabezadoRepetido(array $fila): bool
    {
        $descripcion = mb_strtolower(
            trim($fila['descripcion_proveedor']),
            'UTF-8'
        );

        return in_array(
            $descripcion,
            [
                'descripción',
                'descripcion',
                'producto',
                'artículo',
                'articulo',
                'nombre producto',
                'nombre del producto',
            ],
            true
        );
    }

    /**
     * Agrega un error indicando la fila original del Excel.
     */
    private function agregarError(
        array &$errores,
        int $numeroFila,
        string $mensaje
    ): void {
        if (count($errores) >= self::MAX_ERRORES) {
            return;
        }

        $errores[] = [
            'fila' => $numeroFila,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Escribe una fila utilizando punto y coma como separador.
     */
    private function escribirFilaCsv(
        mixed $archivoCsv,
        array $valores
    ): void {
        $resultado = fputcsv(
            $archivoCsv,
            $valores,
            ';'
        );

        if ($resultado === false) {
            throw new RuntimeException(
                'Ocurrió un error al escribir el archivo CSV.'
            );
        }
    }
}