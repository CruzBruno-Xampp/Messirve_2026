<?php

namespace App\Http\Controllers;

use App\Imports\DetalleCatalogoImport;
use App\Models\CatalogoProveedor;
use App\Models\Proveedor;
use App\Services\AnalizadorArchivoCatalogo;
use App\Services\NormalizadorCsvCatalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as TipoArchivoExcel;
use RuntimeException;
use Throwable;

class CatalogoImportController extends Controller
{
    /*
     * Nombre utilizado para guardar temporalmente
     * la información de la importación en la sesión.
     */
    private const CLAVE_SESION = 'importacion_catalogo';

    /**
     * Muestra la pantalla inicial para subir el Excel.
     */
    public function create(Request $request): View
    {
        $proveedores = Proveedor::query()
            ->where('estado', 1)
            ->orderBy('razonsocial')
            ->get();

        $idProveedorPreseleccionado = $request->integer('idproveedor');

        if (
            $idProveedorPreseleccionado <= 0
            || !$proveedores->contains(
                'idproveedor',
                $idProveedorPreseleccionado
            )
        ) {
            $idProveedorPreseleccionado = null;
        }

        return view(
            'catalogos.importar',
            compact(
                'proveedores',
                'idProveedorPreseleccionado'
            )
        );
    }

    /**
     * Recibe el Excel, lo guarda temporalmente
     * y analiza todas sus hojas.
     */
    public function analizar(
        Request $request,
        AnalizadorArchivoCatalogo $analizador
    ): RedirectResponse {
        $datos = $request->validate(
            [
                'idproveedor' => [
                    'required',
                    'integer',

                    // Además de existir, el proveedor debe estar activo.
                    Rule::exists(
                        'proveedores',
                        'idproveedor'
                    )->where(
                        fn ($consulta) =>
                            $consulta->where('estado', 1)
                    ),
                ],

                'nombre_lista' => [
                    'required',
                    'string',
                    'max:100',

                    /*
                     * Permite nombres reales como:
                     * "Lista Tersuave 234 - Marzo 2026".
                     * Se aceptan letras Unicode, números, espacios
                     * y signos habituales en nombres de catálogos.
                     */
                    'regex:/^[\pL\pN\s.,()\/_&+\-º°#]+$/u',
                ],

                'fecha_vigencia' => [
                    'nullable',
                    'date',
                ],

                'descuento_caja' => [
                    'nullable',
                    'numeric',
                    'decimal:0,2',
                    'between:0,100',
                ],

                'descuento_volumen' => [
                    'nullable',
                    'numeric',
                    'decimal:0,2',
                    'between:0,100',
                ],

                'descuento_especial' => [
                    'nullable',
                    'numeric',
                    'decimal:0,2',
                    'between:0,100',
                ],

                'rentabilidad_predeterminada' => [
                    'nullable',
                    'numeric',
                    'decimal:0,2',
                    'between:0,100',
                ],

                'precios_incluyen_iva' => [
                    'sometimes',
                    'boolean',
                ],

                'archivo' => [
                    'required',
                    'file',
                    'mimes:xls,xlsx',
                    'max:20480',
                ],
            ],
            [
                'idproveedor.required' =>
                    'Debe seleccionar un proveedor.',

                'idproveedor.integer' =>
                    'El proveedor seleccionado no es válido.',

                'idproveedor.exists' =>
                    'El proveedor seleccionado no existe o está inactivo.',

                'nombre_lista.required' =>
                    'Debe escribir un nombre para la lista.',

                'nombre_lista.string' =>
                    'El nombre de la lista debe ser un texto.',

                'nombre_lista.max' =>
                    'El nombre de la lista no puede superar los 100 caracteres.',

                'nombre_lista.regex' =>
                    'El nombre de la lista contiene caracteres no permitidos.',

                'fecha_vigencia.date' =>
                    'La fecha de vigencia no es válida.',

                'descuento_caja.numeric' =>
                    'El descuento por caja debe ser numérico.',

                'descuento_caja.decimal' =>
                    'El descuento por caja puede tener como máximo 2 decimales.',

                'descuento_caja.between' =>
                    'El descuento por caja debe estar entre 0 y 100.',

                'descuento_volumen.numeric' =>
                    'El descuento por volumen debe ser numérico.',

                'descuento_volumen.decimal' =>
                    'El descuento por volumen puede tener como máximo 2 decimales.',

                'descuento_volumen.between' =>
                    'El descuento por volumen debe estar entre 0 y 100.',

                'descuento_especial.numeric' =>
                    'El descuento especial debe ser numérico.',

                'descuento_especial.decimal' =>
                    'El descuento especial puede tener como máximo 2 decimales.',

                'descuento_especial.between' =>
                    'El descuento especial debe estar entre 0 y 100.',

                'rentabilidad_predeterminada.numeric' =>
                    'La rentabilidad debe ser numérica.',

                'rentabilidad_predeterminada.decimal' =>
                    'La rentabilidad puede tener como máximo 2 decimales.',

                'rentabilidad_predeterminada.between' =>
                    'La rentabilidad debe estar entre 0 y 100.',

                'precios_incluyen_iva.boolean' =>
                    'La opción de IVA seleccionada no es válida.',

                'archivo.required' =>
                    'Debe seleccionar un archivo Excel.',

                'archivo.file' =>
                    'El archivo seleccionado no es válido.',

                'archivo.mimes' =>
                    'El archivo debe tener formato XLS o XLSX.',

                'archivo.max' =>
                    'El archivo no puede superar los 20 MB.',
            ]
        );

        /*
         * El catálogo solamente puede ser importado
         * por un usuario interno autenticado.
         */
        if (auth()->id() === null) {
            abort(
                403,
                'Debe iniciar sesión como usuario del sistema.'
            );
        }

        $archivoSubido = $request->file('archivo');

        $extension = strtolower(
            $archivoSubido->getClientOriginalExtension()
        );

        $nombreGuardado = Str::uuid() . '.' . $extension;

        /*
         * El archivo original se guarda temporalmente
         * en storage/app/importaciones_catalogos/originales.
         */
        $rutaRelativa = $archivoSubido->storeAs(
            'importaciones_catalogos/originales',
            $nombreGuardado,
            'local'
        );

        if ($rutaRelativa === false) {
            return back()
                ->withInput()
                ->withErrors([
                    'archivo' => 'No se pudo guardar el archivo.',
                ]);
        }

        $rutaCompleta = storage_path(
            'app/' . $rutaRelativa
        );

        try {
            // Examina las hojas y busca encabezados conocidos.
            $resultadoAnalisis = $analizador->analizar(
                $rutaCompleta
            );
        } catch (Throwable $excepcion) {
            Storage::disk('local')->delete($rutaRelativa);

            report($excepcion);

            return back()
                ->withInput()
                ->withErrors([
                    'archivo' =>
                        'No se pudo analizar el archivo: '
                        . $excepcion->getMessage(),
                ]);
        }

        $descuentoCaja = (float) (
            $datos['descuento_caja'] ?? 0
        );

        $descuentoVolumen = (float) (
            $datos['descuento_volumen'] ?? 0
        );

        $descuentoEspecial = (float) (
            $datos['descuento_especial'] ?? 0
        );

        /*
         * El descuento total se calcula de forma acumulativa,
         * no sumando directamente los porcentajes.
         *
         * Ejemplo:
         * caja 4% y volumen 35,93% = 38,4928%.
         */
        $descuentoTotal = $this->calcularDescuentoTotal(
            $descuentoCaja,
            $descuentoVolumen,
            $descuentoEspecial
        );

        /*
         * Guardamos en sesión solamente la información necesaria
         * para continuar en la pantalla de mapeo.
         */
        session()->put(self::CLAVE_SESION, [
            'token' => (string) Str::uuid(),
            'ruta_relativa' => $rutaRelativa,
            'nombre_original' =>
                $archivoSubido->getClientOriginalName(),

            'idproveedor' => (int) $datos['idproveedor'],
            'idusuario_importa' => (int) auth()->id(),
            'nombre_lista' => trim($datos['nombre_lista']),
            'fecha_vigencia' =>
                $datos['fecha_vigencia'] ?? null,

            'descuento_caja' => $descuentoCaja,
            'descuento_volumen' => $descuentoVolumen,
            'descuento_especial' => $descuentoEspecial,
            'descuento_total' => $descuentoTotal,

            'rentabilidad_predeterminada' => (float) (
                $datos['rentabilidad_predeterminada'] ?? 0
            ),

            'precios_incluyen_iva' =>
                $request->boolean('precios_incluyen_iva'),

            'analisis' => $resultadoAnalisis,
        ]);

        return redirect()->route(
            'catalogos.importar.mapear'
        );
    }

    /**
     * Muestra las hojas detectadas y permite confirmar
     * qué columna corresponde a cada campo.
     */
    public function mapear(): View|RedirectResponse
    {
        $importacion = session(self::CLAVE_SESION);

        if ($importacion === null) {
            return redirect()
                ->route('catalogos.importar')
                ->withErrors([
                    'archivo' =>
                        'Primero debe seleccionar un archivo Excel.',
                ]);
        }

        $rutaRelativa = $importacion['ruta_relativa'];

        if (!Storage::disk('local')->exists($rutaRelativa)) {
            session()->forget(self::CLAVE_SESION);

            return redirect()
                ->route('catalogos.importar')
                ->withErrors([
                    'archivo' =>
                        'El archivo temporal ya no se encuentra disponible.',
                ]);
        }

        return view('catalogos.mapear', [
            'importacion' => $importacion,
            'analisis' => $importacion['analisis'],
            'camposImportables' =>
                $importacion['analisis']['campos_importables'],
        ]);
    }

    /**
     * Convierte las hojas seleccionadas a CSV
     * y guarda los artículos en la base de datos.
     */
    public function importar(
        Request $request,
        NormalizadorCsvCatalogo $normalizador
    ): RedirectResponse {
        $importacion = session(self::CLAVE_SESION);

        if ($importacion === null) {
            return redirect()
                ->route('catalogos.importar')
                ->withErrors([
                    'archivo' =>
                        'La sesión de importación venció.',
                ]);
        }

        $datos = $request->validate([
            'token' => [
                'required',
                'string',
                'uuid',
            ],

            'hojas' => [
                'required',
                'array',
                'min:1',
            ],

            'hojas.*.seleccionada' => [
                'sometimes',
                'boolean',
            ],

            'hojas.*.nombre' => [
                'required',
                'string',
                'max:31',
            ],

            'hojas.*.fila_encabezado' => [
                'nullable',
                'integer',
                'min:1',
                'max:1048576',
            ],

            'hojas.*.nombre_lista' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[\pL\pN\s.,()\/_&+\-º°#]+$/u',
            ],

            'hojas.*.fecha_vigencia' => [
                'nullable',
                'date',
            ],

            'hojas.*.rentabilidad_predeterminada' => [
                'nullable',
                'numeric',
                'decimal:0,2',
                'between:0,100',
            ],

            'hojas.*.precios_incluyen_iva' => [
                'nullable',
                'boolean',
            ],

            'hojas.*.mapeo' => [
                'nullable',
                'array:codigo_proveedor,codigo_fabricante,codigo_barra,descripcion_proveedor,linea_proveedor,tipo_producto_proveedor,grupo_proveedor,subgrupo_proveedor,precio_lista,descuento_producto,precio_neto',
            ],

            /*
             * Las columnas de Excel solo pueden recibirse como letras:
             * A, B, Z, AA, AB, etc. Esto bloquea valores manipulados.
             */
            'hojas.*.mapeo.*' => [
                'nullable',
                'string',
                'regex:/^[A-Z]{1,3}$/i',
            ],
        ], [
            'token.required' =>
                'La identificación de la importación es obligatoria.',

            'token.uuid' =>
                'La identificación de la importación no es válida.',

            'hojas.required' =>
                'No se recibieron hojas para importar.',

            'hojas.array' =>
                'La configuración de las hojas no es válida.',

            'hojas.min' =>
                'El archivo debe contener al menos una hoja.',

            'hojas.*.seleccionada.boolean' =>
                'La selección de una hoja no es válida.',

            'hojas.*.nombre.required' =>
                'No se pudo identificar el nombre de una hoja.',

            'hojas.*.nombre.max' =>
                'El nombre de una hoja no puede superar los 31 caracteres.',

            'hojas.*.fila_encabezado.integer' =>
                'La fila de encabezado debe ser un número entero.',

            'hojas.*.fila_encabezado.min' =>
                'La fila de encabezado debe ser mayor o igual a 1.',

            'hojas.*.fila_encabezado.max' =>
                'La fila de encabezado supera el límite permitido por Excel.',

            'hojas.*.nombre_lista.max' =>
                'El nombre de la lista no puede superar los 100 caracteres.',

            'hojas.*.nombre_lista.regex' =>
                'El nombre de una lista contiene caracteres no permitidos.',

            'hojas.*.fecha_vigencia.date' =>
                'La fecha de vigencia de una hoja no es válida.',

            'hojas.*.rentabilidad_predeterminada.numeric' =>
                'La rentabilidad de una hoja debe ser numérica.',

            'hojas.*.rentabilidad_predeterminada.decimal' =>
                'La rentabilidad de una hoja puede tener como máximo 2 decimales.',

            'hojas.*.rentabilidad_predeterminada.between' =>
                'La rentabilidad de una hoja debe estar entre 0 y 100.',

            'hojas.*.precios_incluyen_iva.boolean' =>
                'La opción de IVA de una hoja no es válida.',

            'hojas.*.mapeo.array' =>
                'El mapeo de columnas contiene campos no permitidos.',

            'hojas.*.mapeo.*.regex' =>
                'Las columnas deben identificarse con letras, por ejemplo A, B o AA.',
        ]);

        /*
         * Comprueba que el formulario corresponda
         * a la importación guardada en la sesión.
         */
        if (!hash_equals(
            $importacion['token'],
            $datos['token']
        )) {
            abort(419, 'La importación ya no es válida.');
        }

        $hojasSeleccionadas = collect($datos['hojas'])
            ->filter(
                fn (array $hoja): bool =>
                    !empty($hoja['seleccionada'])
            )
            ->values();

        if ($hojasSeleccionadas->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'hojas' =>
                        'Debe seleccionar al menos una hoja.',
                ]);
        }

        /*
         * Hojas que realmente existen en el archivo analizado.
         * Esto impide que se envíe un nombre inventado.
         */
        $hojasPermitidas = collect(
            $importacion['analisis']['hojas']
        )->keyBy('nombre');

        $configuraciones = [];

        foreach ($hojasSeleccionadas as $hojaSeleccionada) {
            $nombreHoja = $hojaSeleccionada['nombre'];

            if (!$hojasPermitidas->has($nombreHoja)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'hojas' =>
                            "La hoja {$nombreHoja} no pertenece al archivo.",
                    ]);
            }

            $hojaAnalizada = $hojasPermitidas->get(
                $nombreHoja
            );

            $filaEncabezado = (int) (
                $hojaSeleccionada['fila_encabezado'] ?? 0
            );

            if (
                $filaEncabezado < 1
                || $filaEncabezado >
                    (int) $hojaAnalizada['total_filas']
            ) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'hojas' =>
                            "La fila de encabezado de {$nombreHoja} no es válida.",
                    ]);
            }

            /*
             * Normaliza las letras de las columnas para que valores
             * como "a" y " A " se utilicen internamente como "A".
             */
            $mapeo = collect(
                $hojaSeleccionada['mapeo'] ?? []
            )->map(
                fn ($columna) => filled($columna)
                    ? strtoupper(trim((string) $columna))
                    : null
            )->all();

            if (empty($mapeo['codigo_proveedor'])) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'hojas' =>
                            "Debe indicar el código del proveedor en {$nombreHoja}.",
                    ]);
            }

            if (empty($mapeo['precio_lista'])) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'hojas' =>
                            "Debe indicar el precio en {$nombreHoja}.",
                    ]);
            }

            $nombreLista = trim(
                (string) (
                    $hojaSeleccionada['nombre_lista']
                    ?? ''
                )
            );

            if ($nombreLista === '') {
                $nombreLista =
                    $importacion['nombre_lista']
                    . ' - '
                    . $nombreHoja;
            }

            $configuraciones[] = [
                'nombre_hoja' => $nombreHoja,
                'fila_encabezado' => $filaEncabezado,
                'mapeo' => $mapeo,
                'nombre_lista' => $nombreLista,

                'fecha_vigencia' =>
                    $hojaSeleccionada['fecha_vigencia']
                    ?? $importacion['fecha_vigencia'],

                'rentabilidad_predeterminada' => (float) (
                    $hojaSeleccionada[
                        'rentabilidad_predeterminada'
                    ]
                    ?? $importacion[
                        'rentabilidad_predeterminada'
                    ]
                ),

                /*
                 * Si la hoja trae su propia selección de IVA,
                 * se utiliza esa. Si el campo no fue enviado,
                 * se conserva el valor general del primer paso.
                 */
                'precios_incluyen_iva' => array_key_exists(
                    'precios_incluyen_iva',
                    $hojaSeleccionada
                )
                    ? !empty(
                        $hojaSeleccionada[
                            'precios_incluyen_iva'
                        ]
                    )
                    : (bool) $importacion[
                        'precios_incluyen_iva'
                    ],
            ];
        }

        $rutaExcel = storage_path(
            'app/' . $importacion['ruta_relativa']
        );
        $rutasCsv = [];
        $resultados = [];

        try {
            /*
             * Si ocurre un error grave en alguna hoja,
             * se revierten todos los registros creados.
             */
            DB::transaction(function () use (
                $configuraciones,
                $normalizador,
                $rutaExcel,
                $importacion,
                &$rutasCsv,
                &$resultados
            ): void {
                /*
                 * Existe un solo catálogo vigente por proveedor.
                 *
                 * La primera importación lo crea. Las siguientes
                 * actualizan la misma cabecera sin generar versiones
                 * mensuales duplicadas.
                 */
               $configuracionPrincipal = $configuraciones[0];

$idProveedor = (int) $importacion['idproveedor'];

$nombreLista = trim(
    (string) $importacion['nombre_lista']
);

/*
 * Bloquea temporalmente los catálogos del proveedor
 * mientras se guarda la nueva versión.
 */
CatalogoProveedor::query()
    ->where('idproveedor', $idProveedor)
    ->lockForUpdate()
    ->get();

/*
 * Si ya existe esta misma lista, se actualiza.
 * Si el nombre es diferente, se crea una nueva versión.
 */
$catalogo = CatalogoProveedor::query()
    ->firstOrNew([
        'idproveedor' => $idProveedor,
        'nombre_lista' => $nombreLista,
    ]);

$catalogo->fill([
    'idusuario_importa' =>
        $importacion['idusuario_importa'],

    'archivo_original' =>
        $importacion['nombre_original'],

    'fecha_vigencia' =>
        $configuracionPrincipal['fecha_vigencia'],

    'descuento_caja' =>
        $importacion['descuento_caja'],

    'descuento_volumen' =>
        $importacion['descuento_volumen'],

    'descuento_especial' =>
        $importacion['descuento_especial'],

    'descuento_total' =>
        $importacion['descuento_total'],

    'precios_incluyen_iva' =>
        $configuracionPrincipal[
            'precios_incluyen_iva'
        ],

    'rentabilidad_predeterminada' =>
        $configuracionPrincipal[
            'rentabilidad_predeterminada'
        ],

    /*
     * Primero se guarda inactiva. Después se determina
     * cuál es la lista más reciente del proveedor.
     */
    'estado' => 0,
]);

$catalogo->save();

/*
 * Al reimportar exactamente la misma lista, sus detalles
 * anteriores quedan inactivos y los encontrados en el
 * nuevo Excel volverán a estado 1.
 */
$catalogo->detalles()->update([
    'estado' => 0,
]);

/*
 * Recalcula la vigencia real del proveedor usando la fecha de hoy.
 *
 * Una lista futura queda programada y no reemplaza a la vigente
 * hasta que llegue su fecha. Cuando ese día llegue, la próxima
 * consulta del sistema la dejará vigente automáticamente.
 */
CatalogoProveedor::sincronizarVigencias($idProveedor);
                foreach ($configuraciones as $configuracion) {
                    /*
                     * Primero transforma la hoja seleccionada
                     * en un CSV estándar.
                     */
                    $resultadoCsv = $normalizador->generarCsv(
                        $rutaExcel,
                        $configuracion['nombre_hoja'],
                        $configuracion['fila_encabezado'],
                        $configuracion['mapeo']
                    );

                    $rutasCsv[] = $resultadoCsv['ruta_csv'];

                    if ($resultadoCsv['filas_exportadas'] === 0) {
                        throw new RuntimeException(
                            'La hoja '
                            . $configuracion['nombre_hoja']
                            . ' no contiene productos válidos.'
                        );
                    }

                    /*
                     * Todas las hojas seleccionadas se importan
                     * utilizando la misma cabecera del proveedor.
                     */
                   $importador = new DetalleCatalogoImport(
                        $catalogo->idcatalogoproveedor,
                        $idProveedor
                    );

                    $importador->import(
                        $resultadoCsv['ruta_csv'],
                        null,
                        TipoArchivoExcel::CSV
                    );

                    $cantidadGuardados =
                        $importador->cantidadCreados()
                        + $importador->cantidadActualizados();

                    if ($cantidadGuardados === 0) {
                        throw new RuntimeException(
                            'No se pudo guardar ningún producto de la hoja '
                            . $configuracion['nombre_hoja']
                            . '.'
                        );
                    }

                    /*
                     * Convierte los errores en arreglos simples
                     * para poder guardarlos en la sesión.
                     */
                    $fallosValidacion = collect(
                        $importador->failures()
                    )->map(
                        fn ($fallo): array => [
                            'fila' => $fallo->row(),
                            'campo' => $fallo->attribute(),
                            'mensajes' => $fallo->errors(),
                        ]
                    )->values()->all();

                    $erroresBaseDatos = collect(
                        $importador->errors()
                    )->map(
                        fn (Throwable $error): array => [
                            'mensaje' => $error->getMessage(),
                        ]
                    )->values()->all();

                    /*
                     * La actualización de un catálogo debe ser atómica.
                     * Si alguna fila válida no pudo procesarse, se lanza
                     * una excepción para que la transacción restaure el
                     * catálogo anterior y no deje artículos inactivos
                     * por un error de formato o de base de datos.
                     */
                    if (
                        !empty($resultadoCsv['errores'])
                        || !empty($fallosValidacion)
                        || !empty($erroresBaseDatos)
                    ) {
                        throw new RuntimeException(
                            'La hoja '
                            . $configuracion['nombre_hoja']
                            . ' contiene filas que no pudieron procesarse. '
                            . 'No se modificó el catálogo anterior.'
                        );
                    }

                    $resultados[] = [
                        'idcatalogoproveedor' =>
                            $catalogo->idcatalogoproveedor,

                        'nombre_lista' =>
                            $catalogo->nombre_lista,

                        'hoja' =>
                            $configuracion['nombre_hoja'],

                        'filas_leidas' =>
                            $resultadoCsv['filas_leidas'],

                        'filas_normalizadas' =>
                            $resultadoCsv['filas_exportadas'],

                        'filas_omitidas' =>
                            $resultadoCsv['filas_omitidas'],

                        'registros_creados' =>
                            $importador->cantidadCreados(),

                        'registros_actualizados' =>
                            $importador->cantidadActualizados(),

                        'errores_normalizacion' =>
                            $resultadoCsv['errores'],

                        'fallos_validacion' =>
                            $fallosValidacion,

                        'errores_base_datos' =>
                            $erroresBaseDatos,
                    ];
                }
            });
        } catch (Throwable $excepcion) {
            /*
             * Los CSV temporales se eliminan aunque
             * la importación haya fallado.
             */
            foreach ($rutasCsv as $rutaCsv) {
                if (is_file($rutaCsv)) {
                    File::delete($rutaCsv);
                }
            }

            report($excepcion);

            /*
             * El Excel original se conserva temporalmente
             * para que el usuario pueda corregir el mapeo.
             */
            return back()
                ->withInput()
                ->withErrors([
                    'importacion' =>
                        'No se pudo completar la importación: '
                        . $excepcion->getMessage(),
                ]);
        }

        // Elimina los CSV que ya fueron importados.
        foreach ($rutasCsv as $rutaCsv) {
            if (is_file($rutaCsv)) {
                File::delete($rutaCsv);
            }
        }

        // Elimina el Excel original temporal.
        Storage::disk('local')->delete(
            $importacion['ruta_relativa']
        );

        // Finaliza la sesión temporal de importación.
        session()->forget(self::CLAVE_SESION);

        /*
         * Guarda el resumen para mostrarlo una sola vez
         * en la pantalla de resultado.
         */
        session()->flash(
            'resultado_importacion_catalogo',
            $resultados
        );

        return redirect()->route(
            'catalogos.importar.resultado'
        );
    }

    /**
     * Muestra el resumen de la última importación.
     */
    public function resultado(): View|RedirectResponse
    {
        $resultados = session(
            'resultado_importacion_catalogo'
        );

        if ($resultados === null) {
            return redirect()->route(
                'catalogos.importar'
            );
        }

        return view(
            'catalogos.resultado',
            compact('resultados')
        );
    }

    /**
     * Cancela la importación y elimina el archivo temporal.
     */
    public function cancelar(): RedirectResponse
    {
        $importacion = session(self::CLAVE_SESION);

        if (
            $importacion !== null
            && isset($importacion['ruta_relativa'])
        ) {
            Storage::disk('local')->delete(
                $importacion['ruta_relativa']
            );
        }

        session()->forget(self::CLAVE_SESION);

        return redirect()
            ->route('catalogos.importar')
            ->with(
                'success',
                'La importación fue cancelada.'
            );
    }

    /**
     * Calcula descuentos acumulativos.
     *
     * Ejemplo:
     * 4% y 35,93% no se suman directamente.
     */
    private function calcularDescuentoTotal(
        float $descuentoCaja,
        float $descuentoVolumen,
        float $descuentoEspecial
    ): float {
        $factorCaja = 1 - ($descuentoCaja / 100);
        $factorVolumen = 1 - ($descuentoVolumen / 100);
        $factorEspecial = 1 - ($descuentoEspecial / 100);

        $factorFinal =
            $factorCaja
            * $factorVolumen
            * $factorEspecial;

        return round(
            (1 - $factorFinal) * 100,
            4
        );
    }
}
    