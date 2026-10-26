<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnalizadorArchivoCatalogo
{
    /*
     * Solo se inspeccionan las primeras 80 filas de cada hoja
     * para encontrar los encabezados.
     */
    private const MAX_FILAS_ANALISIS = 80;

    /*
     * Evita analizar cientos de columnas vacías o con formato.
     */
    private const MAX_COLUMNAS_ANALISIS = 100;

    /*
     * Campos que podrá reconocer y relacionar el sistema.
     *
     * La clave es el nombre interno utilizado por Messirve.
     * El valor es el nombre que verá el usuario.
     */
    private array $camposImportables = [
        'codigo_proveedor' => 'Código del proveedor',
        'codigo_fabricante' => 'Código del fabricante',
        'codigo_barra' => 'Código de barras',
        'descripcion_proveedor' => 'Descripción del producto',
        'linea_proveedor' => 'Línea del proveedor',
        'tipo_producto_proveedor' => 'Tipo de producto del proveedor',
        'grupo_proveedor' => 'Grupo del proveedor',
        'subgrupo_proveedor' => 'Subgrupo del proveedor',
        'precio_lista' => 'Precio de lista',
        'descuento_producto' => 'Descuento del producto',
        'precio_neto' => 'Precio neto / Precio con descuento',
    ];

    /*
     * Posibles nombres que pueden utilizar los proveedores
     * para identificar una misma columna.
     *
     * Esto permite reconocer archivos con encabezados diferentes.
     */
    private array $sinonimos = [
        'codigo_proveedor' => [
            'codigo proveedor',
            'cod proveedor',
            'codigo tersuave',
            'cod tersuave',
            'sku proveedor',
        ],

        'codigo_fabricante' => [
            'codigo fabricante',
            'cod fabricante',
            'sku fabricante',
            'codigo de fabrica',
        ],

        'codigo_barra' => [
            'codigo de barra',
            'cod de barra',
            'codigo de barras',
            'cod de barras',
            'codigo barra',
            'cod barra',
            'ean',
            'gtin',
        ],

        'descripcion_proveedor' => [
            'descripcion proveedor',
            'descripcion',
            'nombre del producto',
            'nombre producto',
            'articulo',
            'producto',
        ],

        'linea_proveedor' => [
            'lineas',
            'linea',
            'linea de producto',
        ],

        'tipo_producto_proveedor' => [
            'tipo de producto',
            'tipo producto',
            'productos',
            'categoria producto',
        ],

        'grupo_proveedor' => [
            'grupos',
            'grupo',
            'grupo i',
            'grupo 1',
        ],

        'subgrupo_proveedor' => [
            'subgrupo',
            'sub grupo',
            'grupo ii',
            'grupo 2',
            'familia',
        ],

        'precio_lista' => [
            'precio de lista',
            'precio lista',
            'lista de precios',
            'precio vigente',
            'vigente',
            'vig',
        ],

        'descuento_producto' => [
            'descuento producto',
            'descuento por producto',
            'descuento porcentaje',
            'dto porcentaje',
            'descuento',
            'dto',
        ],

        /*
         * Precio final informado directamente por el proveedor
         * luego de aplicar sus descuentos comerciales.
         *
         * "Precio c/desc" se normaliza internamente como
         * "precio c desc", por eso se incluye esa variante.
         */
        'precio_neto' => [
            'precio neto',
            'precio con descuento',
            'precio c descuento',
            'precio c desc',
            'precio con desc',
            'precio descontado',
            'precio final proveedor',
        ],
    ];

    /**
     * Analiza todas las hojas del archivo Excel.
     *
     * Este método todavía no guarda nada en la base de datos.
     * Solamente devuelve información para mostrarla al usuario.
     */
    public function analizar(string $rutaArchivo): array
    {
        if (!is_file($rutaArchivo)) {
            throw new InvalidArgumentException(
                'No se encontró el archivo que se intenta analizar.'
            );
        }

        /*
         * PhpSpreadsheet detecta automáticamente si el archivo
         * recibido es XLS o XLSX.
         */
        $lector = IOFactory::createReaderForFile($rutaArchivo);

        /*
         * Cargamos solamente los datos.
         * No necesitamos colores, bordes ni imágenes del Excel.
         */
        $lector->setReadDataOnly(true);

        $libro = $lector->load($rutaArchivo);

        try {
            $hojasAnalizadas = [];

            foreach ($libro->getWorksheetIterator() as $indice => $hoja) {
                $hojasAnalizadas[] = $this->analizarHoja(
                    $hoja,
                    $indice
                );
            }

            return [
                'archivo' => basename($rutaArchivo),

                // Campos disponibles para la pantalla de confirmación.
                'campos_importables' => $this->camposImportables,

                // Resultado individual de cada hoja encontrada.
                'hojas' => $hojasAnalizadas,
            ];
        } finally {
            /*
             * Libera de memoria todas las hojas una vez terminado
             * el análisis del archivo.
             */
            $libro->disconnectWorksheets();
            unset($libro);
        }
    }

    /**
     * Devuelve los campos que podrá seleccionar el usuario
     * en la pantalla de mapeo.
     */
    public function camposImportables(): array
    {
        return $this->camposImportables;
    }

    /**
     * Analiza una hoja individual del Excel.
     */
    private function analizarHoja(
        Worksheet $hoja,
        int $indice
    ): array {
        $totalFilas = max(1, $hoja->getHighestDataRow());

        $ultimaColumnaExcel = $hoja->getHighestDataColumn();

        $totalColumnas = Coordinate::columnIndexFromString(
            $ultimaColumnaExcel
        );

        /*
         * Limitamos la cantidad de filas y columnas utilizadas
         * para buscar el encabezado.
         */
        $filasAAnalizar = min(
            $totalFilas,
            self::MAX_FILAS_ANALISIS
        );

        $columnasAAnalizar = min(
            max(1, $totalColumnas),
            self::MAX_COLUMNAS_ANALISIS
        );

        $filas = $this->obtenerFilas(
            $hoja,
            1,
            $filasAAnalizar,
            $columnasAAnalizar
        );

        // Busca cuál parece ser la fila de encabezados.
        $encabezado = $this->detectarEncabezado($filas);

        /*
         * La vista previa comienza dos filas antes del encabezado
         * sugerido y termina ocho filas después.
         */
        $filaInicialVista = $encabezado['fila'] !== null
            ? max(1, $encabezado['fila'] - 2)
            : 1;

        $filaFinalVista = min(
            $totalFilas,
            $filaInicialVista + 10
        );

        $vistaPrevia = $this->obtenerFilas(
            $hoja,
            $filaInicialVista,
            $filaFinalVista,
            $columnasAAnalizar
        );

        return [
            'indice' => $indice,
            'nombre' => $hoja->getTitle(),
            'total_filas' => $totalFilas,
            'total_columnas' => $totalColumnas,

            /*
             * Indica si la hoja parece contener una tabla
             * que pueda ser importada.
             */
            'es_candidata' => $encabezado['es_candidata'],

            // Número de la fila que parece contener los encabezados.
            'fila_encabezado_sugerida' => $encabezado['fila'],

            // Cantidad de columnas que fueron reconocidas.
            'cantidad_coincidencias' => $encabezado['coincidencias'],

            // Relación sugerida: campo de Messirve => columna del Excel.
            'mapeo_sugerido' => $encabezado['mapeo'],

            // Texto original de los encabezados encontrados.
            'encabezados_sugeridos' => $encabezado['encabezados'],

            // Filas que luego mostraremos al dueño para confirmar.
            'vista_previa' => $this->prepararVistaPrevia($vistaPrevia),
        ];
    }

    /**
     * Obtiene un rango determinado de una hoja.
     *
     * Las columnas se devuelven con letras:
     * A, B, C, D, etc.
     */
    private function obtenerFilas(
        Worksheet $hoja,
        int $filaInicial,
        int $filaFinal,
        int $cantidadColumnas
    ): array {
        $ultimaColumna = Coordinate::stringFromColumnIndex(
            max(1, $cantidadColumnas)
        );

        $rango = sprintf(
            'A%d:%s%d',
            $filaInicial,
            $ultimaColumna,
            $filaFinal
        );

        return $hoja->rangeToArray(
            $rango,
            null,
            true,
            true,
            true
        );
    }

    /**
     * Busca la fila que tenga mayor cantidad de encabezados conocidos.
     */
    private function detectarEncabezado(array $filas): array
    {
        $mapeoVacio = array_fill_keys(
            array_keys($this->camposImportables),
            null
        );

        $mejorResultado = [
            'fila' => null,
            'puntaje' => 0,
            'coincidencias' => 0,
            'mapeo' => $mapeoVacio,
            'encabezados' => [],
            'es_candidata' => false,
        ];

        foreach ($filas as $numeroFila => $fila) {
            $resultadoFila = $this->evaluarFilaComoEncabezado(
                $fila
            );

            if ($resultadoFila['puntaje'] > $mejorResultado['puntaje']) {
                $mejorResultado = [
                    'fila' => (int) $numeroFila,
                    'puntaje' => $resultadoFila['puntaje'],
                    'coincidencias' => $resultadoFila['coincidencias'],
                    'mapeo' => $resultadoFila['mapeo'],
                    'encabezados' => $this->limpiarFila($fila),
                    'es_candidata' => false,
                ];
            }
        }

        /*
         * Una hoja será candidata si se reconocieron al menos
         * dos columnas y una de ellas es descripción o precio.
         */
        $tieneDescripcion =
            $mejorResultado['mapeo']['descripcion_proveedor'] !== null;

        $tienePrecio =
            $mejorResultado['mapeo']['precio_lista'] !== null;

        $mejorResultado['es_candidata'] =
            $mejorResultado['coincidencias'] >= 2
            && ($tieneDescripcion || $tienePrecio);

        /*
         * Si no reconocimos suficientes columnas, no aseguramos
         * que esa fila sea realmente el encabezado.
         */
        if ($mejorResultado['coincidencias'] < 2) {
            $mejorResultado['fila'] = null;
        }

        return $mejorResultado;
    }

    /**
     * Evalúa cada celda de una fila buscando nombres conocidos.
     */
    private function evaluarFilaComoEncabezado(array $fila): array
    {
        $selecciones = [];

        foreach ($fila as $columna => $valor) {
            $encabezado = $this->normalizarTexto($valor);

            if ($encabezado === '') {
                continue;
            }

            $mejorCampo = null;
            $mejorPuntaje = 0;

            foreach ($this->sinonimos as $campo => $sinonimos) {
                foreach ($sinonimos as $sinonimo) {
                    $puntaje = $this->puntuarCoincidencia(
                        $encabezado,
                        $sinonimo
                    );

                    if ($puntaje > $mejorPuntaje) {
                        $mejorPuntaje = $puntaje;
                        $mejorCampo = $campo;
                    }
                }
            }

            if ($mejorCampo === null || $mejorPuntaje === 0) {
                continue;
            }

            /*
             * Si dos columnas parecen representar el mismo campo,
             * conservamos la coincidencia que tenga mayor puntaje.
             */
            if (
                !isset($selecciones[$mejorCampo])
                || $mejorPuntaje > $selecciones[$mejorCampo]['puntaje']
            ) {
                $selecciones[$mejorCampo] = [
                    'columna' => (string) $columna,
                    'puntaje' => $mejorPuntaje,
                ];
            }
        }

        $mapeo = array_fill_keys(
            array_keys($this->camposImportables),
            null
        );

        foreach ($selecciones as $campo => $seleccion) {
            $mapeo[$campo] = $seleccion['columna'];
        }

        $coincidencias = count($selecciones);

        // Cada columna reconocida suma diez puntos.
        $puntajeTotal = $coincidencias * 10;

        // Descripción y precio son los campos más importantes.
        if ($mapeo['descripcion_proveedor'] !== null) {
            $puntajeTotal += 10;
        }

        if ($mapeo['precio_lista'] !== null) {
            $puntajeTotal += 10;
        }

        if ($mapeo['codigo_barra'] !== null) {
            $puntajeTotal += 3;
        }

        return [
            'puntaje' => $puntajeTotal,
            'coincidencias' => $coincidencias,
            'mapeo' => $mapeo,
        ];
    }

    /**
     * Calcula qué tan parecido es un encabezado a un sinónimo.
     */
    private function puntuarCoincidencia(
        string $encabezado,
        string $sinonimo
    ): int {
        $sinonimoNormalizado = $this->normalizarTexto(
            $sinonimo
        );

        // Coincidencia exacta: es la más segura.
        if ($encabezado === $sinonimoNormalizado) {
            return 1000 + strlen($sinonimoNormalizado);
        }

        /*
         * La palabra "producto" sola puede aparecer en encabezados
         * como "Rentabilidad por producto". Para evitar confundirla
         * con la descripción, solo la aceptamos si es exacta.
         */
        if ($sinonimoNormalizado === 'producto') {
            return 0;
        }

        /*
         * Algunos proveedores nombran su código con la marca o empresa,
         * por ejemplo "cód Tersuave" o "cód Sinteplast". Si el encabezado
         * comienza con "cod"/"codigo" y no corresponde a barras o fabricante,
         * se lo puede considerar un código del proveedor.
         */
        if (
            in_array($sinonimoNormalizado, ['codigo proveedor', 'cod proveedor'], true)
            && preg_match('/^(?:codigo|cod)\s+/', $encabezado) === 1
            && !preg_match(
                '/\b(?:barra|barras|ean|gtin|fabricante|fabrica)\b/',
                $encabezado
            )
        ) {
            return 350;
        }

        /*
         * Busca el sinónimo como palabra o conjunto de palabras
         * dentro del encabezado.
         *
         * Ejemplo:
         * "234 Vig. 16.03.2026" contiene la palabra "vig".
         */
        $patron = '/(?:^|\s)'
            . preg_quote($sinonimoNormalizado, '/')
            . '(?:\s|$)/u';

        if (preg_match($patron, $encabezado) === 1) {
            return 500 + strlen($sinonimoNormalizado);
        }

        /*
         * Coincidencia parcial para encabezados que contienen
         * nombres más largos o unidos de forma poco convencional.
         */
        if (
            strlen($sinonimoNormalizado) >= 8
            && str_contains($encabezado, $sinonimoNormalizado)
        ) {
            return 200 + strlen($sinonimoNormalizado);
        }

        return 0;
    }

    /**
     * Normaliza textos para poder comparar encabezados.
     *
     * Ejemplo:
     * "Cód. de Barra" se transforma en "cod de barra".
     */
    private function normalizarTexto(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        $texto = trim((string) $valor);

        if ($texto === '') {
            return '';
        }

        $texto = mb_strtolower($texto, 'UTF-8');

        // Convierte el símbolo % en una palabra comparable.
        $texto = str_replace('%', ' porcentaje ', $texto);

        /*
         * Elimina tildes de forma consistente también en Windows/Laragon.
         *
         * Con iconv(), encabezados como "cód Tersuave", "Descripción"
         * o "cód de barra" podían terminar normalizados de forma distinta
         * según el entorno. Str::ascii() evita esa diferencia.
         */
        $texto = Str::ascii($texto);

        // Reemplaza puntos, guiones y símbolos por espacios.
        $texto = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $texto
        ) ?? '';

        // Elimina espacios repetidos.
        $texto = preg_replace(
            '/\s+/',
            ' ',
            $texto
        ) ?? '';

        return trim($texto);
    }

    /**
     * Prepara las filas que se mostrarán en la vista previa.
     */
    private function prepararVistaPrevia(array $filas): array
    {
        $resultado = [];

        foreach ($filas as $numeroFila => $fila) {
            $celdas = $this->limpiarFila($fila);

            if ($celdas === []) {
                continue;
            }

            $resultado[] = [
                'numero_fila' => (int) $numeroFila,
                'celdas' => $celdas,
            ];
        }

        return $resultado;
    }

    /**
     * Elimina las celdas vacías y prepara sus valores
     * para mostrarlos en pantalla.
     */
    private function limpiarFila(array $fila): array
    {
        $resultado = [];

        foreach ($fila as $columna => $valor) {
            if ($valor instanceof DateTimeInterface) {
                $valor = $valor->format('d/m/Y');
            }

            if (is_string($valor)) {
                $valor = trim($valor);
            }

            if ($valor === null || $valor === '') {
                continue;
            }

            $resultado[(string) $columna] = $valor;
        }

        return $resultado;
    }
}