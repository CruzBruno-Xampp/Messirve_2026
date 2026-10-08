<?php

namespace App\Imports;

use App\Models\DetalleCatalogo;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DetalleCatalogoImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    SkipsOnError,
    SkipsEmptyRows,
    WithChunkReading,
    WithCustomCsvSettings
{
    /*
     * Importable permite ejecutar:
     *
     * $importador->import($archivo);
     */
    use Importable;

    /*
     * Guarda las filas que no superaron las validaciones
     * sin detener toda la importación.
     */
    use SkipsFailures;

    /*
     * Guarda los errores de base de datos
     * sin detener las demás filas.
     */
    use SkipsErrors;

    private int $cantidadCreados = 0;
    private int $cantidadActualizados = 0;
    private bool $relacionesAnterioresCargadas = false;
private array $relacionesAnteriores = [];

    /**
     * El importador recibe el catálogo del proveedor
     * al que pertenecerán todas las filas del CSV.
     */
   public function __construct(
    private readonly int $idCatalogoProveedor,
    private readonly int $idProveedor
) {
    if ($this->idCatalogoProveedor <= 0) {
        throw new InvalidArgumentException(
            'El identificador del catálogo proveedor no es válido.'
        );
    }

    if ($this->idProveedor <= 0) {
        throw new InvalidArgumentException(
            'El identificador del proveedor no es válido.'
        );
    }
}

    /**
     * Convierte cada fila del CSV en un registro
     * de la tabla detalle_catalogo.
     */
    public function model(array $fila): ?DetalleCatalogo
    {
        /*
         * Construye una condición para evitar productos repetidos
         * dentro del mismo catálogo.
         */
        $criterio = $this->construirCriterioIdentidad($fila);

        /*
         * Busca el producto en este catálogo.
         *
         * Si existe, actualiza sus datos.
         * Si no existe, prepara un registro nuevo.
         */
        $detalle = DetalleCatalogo::firstOrNew($criterio);
        $this->heredarRelacionesAnteriores(
            $detalle,
            $fila
        );
        if ($detalle->exists) {
            $this->cantidadActualizados++;
        } else {
            $this->cantidadCreados++;
        }

        /*
         * No modificamos idproducto, idmarca, idsubgrupo ni idiva.
         *
         * Estos campos permanecen NULL hasta que el dueño revise
         * el artículo y lo relacione con los datos internos
         * de la pinturería.
         */
        $detalle->fill([
            'idcatalogoproveedor' => $this->idCatalogoProveedor,

            'codigoproveedor' => $this->textoNullable(
                $fila['codigo_proveedor'] ?? null
            ),

            'codigofabricante' => $this->textoNullable(
                $fila['codigo_fabricante'] ?? null
            ),

            'codigobarra' => $this->textoNullable(
                $fila['codigo_barra'] ?? null
            ),

            'descripcionproveedor' => trim(
                (string) $fila['descripcion_proveedor']
            ),

            /*
             * Clasificación original enviada por el proveedor.
             * Se conserva como texto para no perder información.
             */
            'linea_proveedor' => $this->textoNullable(
                $fila['linea_proveedor'] ?? null
            ),

            'tipo_producto_proveedor' => $this->textoNullable(
                $fila['tipo_producto_proveedor'] ?? null
            ),

            'grupo_proveedor' => $this->textoNullable(
                $fila['grupo_proveedor'] ?? null
            ),

            'subgrupo_proveedor' => $this->textoNullable(
                $fila['subgrupo_proveedor'] ?? null
            ),

            'preciolista' => (float) $fila['precio_lista'],

            'descuentoproducto' => (float) (
                $fila['descuento_producto'] ?? 0
            ),

            /*
             * Precio neto informado directamente por el proveedor.
             *
             * Queda NULL cuando el Excel no trae una columna con el
             * precio ya descontado. Más adelante el comparador podrá
             * calcular el costo neto a partir del precio de lista y
             * los descuentos cuando este valor no exista.
             */
            'precioneto' => $this->numeroNullable(
                $fila['precio_neto'] ?? null
            ),

            // El artículo queda activo dentro del catálogo.
            'estado' => 1,
        ]);

        /*
         * Laravel Excel guardará este modelo automáticamente
         * después de ejecutar este método.
         */
        return $detalle;
    }

    /**
     * Prepara los valores antes de aplicar las validaciones.
     */
    public function prepareForValidation(
        array $datos,
        int $numeroFila
    ): array {
        $camposTexto = [
            'codigo_proveedor',
            'codigo_fabricante',
            'codigo_barra',
            'descripcion_proveedor',
            'linea_proveedor',
            'tipo_producto_proveedor',
            'grupo_proveedor',
            'subgrupo_proveedor',
        ];

        foreach ($camposTexto as $campo) {
            $valor = $datos[$campo] ?? null;

            if ($valor === null) {
                $datos[$campo] = null;
                continue;
            }

            $valor = trim((string) $valor);

            $datos[$campo] = $valor === ''
                ? null
                : $valor;
        }

        /*
         * La descripción debe existir, por eso una celda vacía
         * se convierte en una cadena vacía para que falle required.
         */
        $datos['descripcion_proveedor'] =
            $datos['descripcion_proveedor'] ?? '';

        $datos['precio_lista'] =
            $datos['precio_lista'] ?? null;

        $datos['descuento_producto'] =
            $datos['descuento_producto'] ?? 0;

        /*
         * A diferencia del descuento, el precio neto no debe
         * convertirse en cero cuando no existe.
         */
        $datos['precio_neto'] =
            $datos['precio_neto'] ?? null;

        return $datos;
    }

    /**
     * Reglas que debe cumplir cada fila antes de guardarse.
     */
    public function rules(): array
    {
        return [
            'codigo_proveedor' => [
                'required',
                'string',
                'max:50',
            ],

            'codigo_fabricante' => [
                'nullable',
                'string',
                'max:50',
            ],

            'codigo_barra' => [
                'nullable',
                'string',
                'max:50',
            ],

            'descripcion_proveedor' => [
                'required',
                'string',
                'max:255',
            ],

            'linea_proveedor' => [
                'nullable',
                'string',
                'max:100',
            ],

            'tipo_producto_proveedor' => [
                'nullable',
                'string',
                'max:100',
            ],

            'grupo_proveedor' => [
                'nullable',
                'string',
                'max:100',
            ],

            'subgrupo_proveedor' => [
                'nullable',
                'string',
                'max:150',
            ],

            'precio_lista' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'descuento_producto' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'precio_neto' => [
                'nullable',
                'numeric',
                'gt:0',
            ],
        ];
    }

    /**
     * Mensajes de validación comprensibles para el usuario.
     */
    public function customValidationMessages(): array
    {
        return [
            'descripcion_proveedor.required' =>
                'La descripción del producto es obligatoria.',

            'descripcion_proveedor.max' =>
                'La descripción no puede superar los 255 caracteres.',

            'precio_lista.required' =>
                'El precio de lista es obligatorio.',

            'precio_lista.numeric' =>
                'El precio de lista debe ser numérico.',

            'precio_lista.gt' =>
                'El precio de lista debe ser mayor que cero.',

            'descuento_producto.numeric' =>
                'El descuento debe ser numérico.',

            'descuento_producto.between' =>
                'El descuento debe encontrarse entre 0 y 100.',

            'precio_neto.numeric' =>
                'El precio neto debe ser numérico.',

            'precio_neto.gt' =>
                'El precio neto debe ser mayor que cero.',

            'codigo_proveedor.required' =>
                'El código del proveedor es obligatorio.',
        ];
    }

    /**
     * Nombres que aparecerán en los errores de validación.
     */
    public function customValidationAttributes(): array
    {
        return [
            'codigo_proveedor' => 'código del proveedor',
            'codigo_fabricante' => 'código del fabricante',
            'codigo_barra' => 'código de barras',
            'descripcion_proveedor' => 'descripción',
            'linea_proveedor' => 'línea',
            'tipo_producto_proveedor' => 'tipo de producto',
            'grupo_proveedor' => 'grupo',
            'subgrupo_proveedor' => 'subgrupo',
            'precio_lista' => 'precio de lista',
            'descuento_producto' => 'descuento',
            'precio_neto' => 'precio neto',
        ];
    }

    /**
     * Configuración necesaria para leer el CSV generado
     * por NormalizadorCsvCatalogo.
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '"',
            'escape_character' => '\\',
            'input_encoding' => 'UTF-8',
        ];
    }

    /**
     * Procesa el archivo en grupos de 500 filas
     * para no consumir demasiada memoria.
     */
    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * Devuelve la cantidad de registros nuevos preparados.
     */
    public function cantidadCreados(): int
    {
        return $this->cantidadCreados;
    }

    /**
     * Devuelve la cantidad de registros existentes actualizados.
     */
    public function cantidadActualizados(): int
    {
        return $this->cantidadActualizados;
    }

    /**
     * Determina qué campos identifican al artículo
     * dentro de un catálogo determinado.
     */


    /**
 * Conserva las relaciones internas asignadas al mismo artículo
 * en una versión anterior del catálogo del proveedor.
 */
private function heredarRelacionesAnteriores(
    DetalleCatalogo $detalle,
    array $fila
): void {
    /*
     * Si ya tiene todas sus relaciones, no es necesario
     * buscar información en otras listas.
     */
    if (
        $detalle->idproducto !== null
        && $detalle->idmarca !== null
        && $detalle->idsubgrupo !== null
        && $detalle->idiva !== null
    ) {
        return;
    }

    $codigoProveedor = $this->textoNullable(
        $fila['codigo_proveedor'] ?? null
    );

    if ($codigoProveedor === null) {
        return;
    }

    $this->cargarRelacionesAnteriores();

    $clave = mb_strtolower(
        $codigoProveedor,
        'UTF-8'
    );

    $relaciones = $this->relacionesAnteriores[$clave]
        ?? null;

    if ($relaciones === null) {
        return;
    }

    /*
     * Solamente completa valores NULL.
     * Nunca reemplaza una relación ya confirmada.
     */
    if ($detalle->idproducto === null) {
        $detalle->idproducto =
            $relaciones['idproducto'];
    }

    if ($detalle->idmarca === null) {
        $detalle->idmarca =
            $relaciones['idmarca'];
    }

    if ($detalle->idsubgrupo === null) {
        $detalle->idsubgrupo =
            $relaciones['idsubgrupo'];
    }

    if ($detalle->idiva === null) {
        $detalle->idiva =
            $relaciones['idiva'];
    }
}

/**
 * Carga una sola vez las relaciones de versiones anteriores.
 *
 * Esto evita ejecutar una consulta por cada producto del Excel.
 */
private function cargarRelacionesAnteriores(): void
{
    if ($this->relacionesAnterioresCargadas) {
        return;
    }

    $this->relacionesAnterioresCargadas = true;

    $detallesAnteriores = DetalleCatalogo::query()
        ->join(
            'catalogo_proveedor as catalogo_anterior',
            'catalogo_anterior.idcatalogoproveedor',
            '=',
            'detalle_catalogo.idcatalogoproveedor'
        )
        ->where(
            'catalogo_anterior.idproveedor',
            $this->idProveedor
        )
        ->where(
            'detalle_catalogo.idcatalogoproveedor',
            '<>',
            $this->idCatalogoProveedor
        )
        ->whereNotNull(
            'detalle_catalogo.codigoproveedor'
        )
        ->where(function ($consulta): void {
            $consulta
                ->whereNotNull(
                    'detalle_catalogo.idproducto'
                )
                ->orWhereNotNull(
                    'detalle_catalogo.idmarca'
                )
                ->orWhereNotNull(
                    'detalle_catalogo.idsubgrupo'
                )
                ->orWhereNotNull(
                    'detalle_catalogo.idiva'
                );
        })
        /*
         * Prioriza detalles que ya estén vinculados con
         * un producto interno de Messirve.
         */
        ->orderByRaw(
            'detalle_catalogo.idproducto IS NULL ASC'
        )
        ->orderByRaw(
            'catalogo_anterior.fecha_vigencia IS NULL ASC'
        )
        ->orderByDesc(
            'catalogo_anterior.fecha_vigencia'
        )
        ->orderByDesc(
            'detalle_catalogo.iddetallecatalogo'
        )
        ->select([
            'detalle_catalogo.codigoproveedor',
            'detalle_catalogo.idproducto',
            'detalle_catalogo.idmarca',
            'detalle_catalogo.idsubgrupo',
            'detalle_catalogo.idiva',
        ])
        ->get();

    foreach ($detallesAnteriores as $detalleAnterior) {
        $codigoProveedor = $this->textoNullable(
            $detalleAnterior->codigoproveedor
        );

        if ($codigoProveedor === null) {
            continue;
        }

        $clave = mb_strtolower(
            $codigoProveedor,
            'UTF-8'
        );

        /*
         * La consulta ya viene ordenada por prioridad.
         * Conservamos la primera coincidencia de cada código.
         */
        if (
            array_key_exists(
                $clave,
                $this->relacionesAnteriores
            )
        ) {
            continue;
        }

        $this->relacionesAnteriores[$clave] = [
            'idproducto' =>
                $detalleAnterior->idproducto,

            'idmarca' =>
                $detalleAnterior->idmarca,

            'idsubgrupo' =>
                $detalleAnterior->idsubgrupo,

            'idiva' =>
                $detalleAnterior->idiva,
        ];
    }
}
    
    
    private function construirCriterioIdentidad(array $fila): array
    {
        $criterio = [
            'idcatalogoproveedor' => $this->idCatalogoProveedor,
        ];

        /*
         * Prioridad para reconocer el artículo:
         *
         * 1. Código del proveedor.
         * 2. Código del fabricante.
         * 3. Código de barras.
         * 4. Descripción.
         */
        $codigoProveedor = $this->textoNullable(
            $fila['codigo_proveedor'] ?? null
        );

        if ($codigoProveedor !== null) {
            $criterio['codigoproveedor'] = $codigoProveedor;

            return $criterio;
        }

        $codigoFabricante = $this->textoNullable(
            $fila['codigo_fabricante'] ?? null
        );

        if ($codigoFabricante !== null) {
            $criterio['codigofabricante'] = $codigoFabricante;

            return $criterio;
        }

        $codigoBarra = $this->textoNullable(
            $fila['codigo_barra'] ?? null
        );

        if ($codigoBarra !== null) {
            $criterio['codigobarra'] = $codigoBarra;

            return $criterio;
        }

        /*
         * La descripción se utiliza solamente cuando
         * el proveedor no entregó ningún código.
         */
        $criterio['descripcionproveedor'] = trim(
            (string) $fila['descripcion_proveedor']
        );

        return $criterio;
    }

    /**
     * Convierte un valor numérico opcional en float.
     *
     * Devuelve NULL cuando el proveedor no informó el precio neto.
     */
    private function numeroNullable(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) $valor;
    }

    /**
     * Convierte cadenas vacías en NULL.
     */
    private function textoNullable(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === ''
            ? null
            : $valor;
    }
}