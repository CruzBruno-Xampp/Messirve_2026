<?php

namespace App\Http\Controllers;

use App\Models\CatalogoProveedor;
use App\Models\DetalleCatalogo;
use App\Models\Producto;
use App\Models\Stock;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    /**
     * Muestra los productos que ya comercializa Messirve.
     */
    public function index(Request $request)
    {
        // Mantiene actualizadas las listas que vencen o comienzan hoy.
        CatalogoProveedor::sincronizarVigencias();

        $busqueda = trim((string) $request->input('buscar', ''));
        $estado = (string) $request->input('estado', '1');

        $consulta = Producto::query()
            ->with([
                'marca',
                'subgrupo',
                'alicuotaIva',
            ]);

        if ($busqueda !== '') {
            $consulta->where(function ($query) use ($busqueda) {
                $query
                    ->where('codigointerno', 'like', "%{$busqueda}%")
                    ->orWhere('codigofabricante', 'like', "%{$busqueda}%")
                    ->orWhere('codigobarra', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%");
            });
        }

        if (in_array($estado, ['0', '1'], true)) {
            $consulta->where('estado', (int) $estado);
        }

        $productos = $consulta
            ->orderBy('descripcion')
            ->paginate(20)
            ->withQueryString();

        $cantidadPendientes = DetalleCatalogo::query()
            ->whereNull('idproducto')
            ->where('estado', 1)
            ->whereHas('catalogoProveedor', function ($query) {
                $query->where('estado', 1);
            })
            ->count();

        return view(
            'productos.index',
            compact(
                'productos',
                'cantidadPendientes',
                'busqueda',
                'estado'
            )
        );
    }



    /**
     * Compara las ofertas vigentes de proveedores tomando como referencia
     * una fila concreta del catálogo.
     *
     * La equivalencia se resuelve de forma segura:
     * - si existe producto interno, se compara por idproducto;
     * - si existe código de barras, también se incluyen coincidencias exactas;
     * - nunca se comparan productos solamente por una descripción parecida.
     */
    public function compararProveedores(DetalleCatalogo $detalle)
    {
        $detalle->load([
            'catalogoProveedor.proveedor',
            'producto.alicuotaIva',
            'alicuotaIva',
        ]);

        $catalogoReferencia = $detalle->catalogoProveedor;

        if ($catalogoReferencia !== null) {
            CatalogoProveedor::sincronizarVigencias(
                (int) $catalogoReferencia->idproveedor
            );

            $detalle->unsetRelations();
            $detalle->load([
                'catalogoProveedor.proveedor',
                'producto.alicuotaIva',
                'alicuotaIva',
            ]);

            $catalogoReferencia = $detalle->catalogoProveedor;
        }

        $proveedorReferencia = $catalogoReferencia?->proveedor;

        if (
            !$detalle->estado
            || $catalogoReferencia === null
            || !$catalogoReferencia->estado
            || $proveedorReferencia === null
            || !$proveedorReferencia->estado
        ) {
            return redirect()
                ->route('productos.catalogo')
                ->with(
                    'error',
                    'La comparación sólo está disponible para artículos de listas vigentes de proveedores activos.'
                );
        }

        $idProducto = $detalle->idproducto !== null
            ? (int) $detalle->idproducto
            : null;

        $codigoBarra = trim((string) ($detalle->codigobarra ?? ''));

        if ($idProducto === null && $codigoBarra === '') {
            return redirect()
                ->route(
                    'productos.catalogo',
                    [
                        'idproveedor' => $catalogoReferencia->idproveedor,
                        'idcatalogo' => $catalogoReferencia->idcatalogoproveedor,
                    ]
                )
                ->with(
                    'error',
                    'Este artículo todavía no puede compararse automáticamente porque no está vinculado a un producto MES y tampoco tiene código de barras.'
                );
        }

        if ($idProducto !== null && $codigoBarra !== '') {
            $criterioComparacion = 'producto_y_codigo';
        } elseif ($idProducto !== null) {
            $criterioComparacion = 'producto';
        } else {
            $criterioComparacion = 'codigo_barra';
        }

        $consulta = DetalleCatalogo::query()
            ->with([
                'catalogoProveedor.proveedor',
                'producto.alicuotaIva',
                'alicuotaIva',
            ])
            ->where('estado', 1)
            ->whereHas(
                'catalogoProveedor',
                function ($query): void {
                    $query->where('estado', 1);
                }
            )
            ->whereHas(
                'catalogoProveedor.proveedor',
                function ($query): void {
                    $query->where('estado', 1);
                }
            )
            ->where(function ($query) use ($idProducto, $codigoBarra): void {
                $agregoCriterio = false;

                if ($idProducto !== null) {
                    $query->where('idproducto', $idProducto);
                    $agregoCriterio = true;
                }

                if ($codigoBarra !== '') {
                    if ($agregoCriterio) {
                        $query->orWhere('codigobarra', $codigoBarra);
                    } else {
                        $query->where('codigobarra', $codigoBarra);
                    }
                }
            });

        $detalles = $consulta->get();

        $ofertas = $detalles
            ->map(function (DetalleCatalogo $oferta) use ($detalle): array {
                $catalogo = $oferta->catalogoProveedor;
                $proveedor = $catalogo?->proveedor;

                $precioLista = (float) $oferta->preciolista;
                $descuentoProducto = (float) $oferta->descuentoproducto;
                $descuentoGeneral = (float) ($catalogo?->descuento_total ?? 0);

                $precioNetoInformado = $oferta->precioneto !== null
                    ? (float) $oferta->precioneto
                    : null;

                if (
                    $precioNetoInformado !== null
                    && $precioNetoInformado > 0
                ) {
                    $costoAntesIva = $precioNetoInformado;
                    $origenCosto = 'informado';
                } else {
                    $factorProducto = 1 - ($descuentoProducto / 100);
                    $factorGeneral = 1 - ($descuentoGeneral / 100);

                    $costoAntesIva =
                        $precioLista
                        * $factorProducto
                        * $factorGeneral;

                    $origenCosto =
                        ($descuentoProducto > 0 || $descuentoGeneral > 0)
                            ? 'calculado'
                            : 'lista';
                }

                $preciosIncluyenIva =
                    (bool) ($catalogo?->precios_incluyen_iva ?? false);

                $ivaPorcentaje = null;

                if ($oferta->alicuotaIva !== null) {
                    $ivaPorcentaje =
                        (float) $oferta->alicuotaIva->porcentaje;
                } elseif ($oferta->producto?->alicuotaIva !== null) {
                    $ivaPorcentaje =
                        (float) $oferta->producto->alicuotaIva->porcentaje;
                } elseif ($detalle->alicuotaIva !== null) {
                    $ivaPorcentaje =
                        (float) $detalle->alicuotaIva->porcentaje;
                } elseif ($detalle->producto?->alicuotaIva !== null) {
                    $ivaPorcentaje =
                        (float) $detalle->producto->alicuotaIva->porcentaje;
                }

                $costoComparable = $costoAntesIva;
                $esComparable = true;
                $ivaPudoNormalizarse = true;

                if ($preciosIncluyenIva) {
                    if ($ivaPorcentaje !== null && $ivaPorcentaje > 0) {
                        $costoComparable =
                            $costoAntesIva
                            / (1 + ($ivaPorcentaje / 100));
                    } else {
                        /*
                         * No se recomienda esta oferta como la mejor hasta
                         * poder normalizar correctamente el IVA.
                         */
                        $esComparable = false;
                        $ivaPudoNormalizarse = false;
                    }
                }

                return [
                    'detalle' => $oferta,
                    'catalogo' => $catalogo,
                    'proveedor' => $proveedor,
                    'precio_lista' => $precioLista,
                    'descuento_producto' => $descuentoProducto,
                    'descuento_general' => $descuentoGeneral,
                    'precio_neto_informado' => $precioNetoInformado,
                    'costo_antes_iva' => round($costoAntesIva, 6),
                    'costo_comparable' => round($costoComparable, 6),
                    'origen_costo' => $origenCosto,
                    'precios_incluyen_iva' => $preciosIncluyenIva,
                    'iva_porcentaje' => $ivaPorcentaje,
                    'iva_pudo_normalizarse' => $ivaPudoNormalizarse,
                    'es_comparable' => $esComparable,
                    'es_referencia' =>
                        $oferta->iddetallecatalogo
                        === $detalle->iddetallecatalogo,
                ];
            })
            ->sortBy(function (array $oferta): array {
                return [
                    $oferta['es_comparable'] ? 0 : 1,
                    $oferta['costo_comparable'],
                ];
            })
            ->values();

        $ofertasComparables = $ofertas
            ->filter(
                fn (array $oferta): bool => $oferta['es_comparable']
            )
            ->values();

        $mejorOferta = $ofertasComparables->first();

        $proveedoresComparables = $ofertasComparables
            ->pluck('catalogo.idproveedor')
            ->filter()
            ->unique()
            ->count();

        $hayComparacionReal = $proveedoresComparables >= 2;

        return view(
            'productos.comparar-proveedores',
            compact(
                'detalle',
                'catalogoReferencia',
                'proveedorReferencia',
                'criterioComparacion',
                'ofertas',
                'ofertasComparables',
                'mejorOferta',
                'hayComparacionReal'
            )
        );
    }

    /**
     * Alta de un producto que no proviene de un catálogo.
     */
    public function crearManual()
    {
        $marcas = DB::table('marcas')->where('estado', 1)
            ->orderBy('nombre')->get(['idmarca', 'nombre']);
        $lineas = DB::table('lineas')->where('estado', 1)
            ->orderBy('nombre')->get(['idlinea', 'nombre']);
        $grupos = DB::table('grupos')->where('estado', 1)
            ->orderBy('nombre')->get(['idgrupo', 'idlinea', 'nombre']);
        $subgrupos = DB::table('subgrupos')->where('estado', 1)
            ->orderBy('nombre')->get(['idsubgrupo', 'idgrupo', 'nombre']);
        $alicuotasIva = DB::table('alicuotas_iva')->where('estado', 1)
            ->orderBy('porcentaje')->get(['idiva', 'nombre', 'porcentaje']);
        $unidadesMedida = DB::table('unidades_medida')->where('estado', 1)
            ->orderBy('nombre')->get(['idunidad', 'nombre', 'abreviatura']);

        return view('productos.crear-manual', compact(
            'marcas', 'lineas', 'grupos', 'subgrupos',
            'alicuotasIva', 'unidadesMedida'
        ));
    }

    public function guardarManual(Request $request)
    {
        $entrada = $request->all();

        // Los códigos siguen siendo texto: se conservan sus ceros iniciales.
        foreach ([
            'descripcion', 'codigofabricante', 'codigobarra', 'color',
            'nueva_marca', 'nueva_linea', 'nuevo_grupo', 'nuevo_subgrupo',
            'nueva_unidad', 'nueva_unidad_abreviatura',
            'contenido_neto', 'rentabilidadobjetivo', 'pvp',
        ] as $campo) {
            if (isset($entrada[$campo]) && is_string($entrada[$campo])) {
                $entrada[$campo] = trim($entrada[$campo]);
                if ($entrada[$campo] === '') {
                    $entrada[$campo] = null;
                }
            }
        }

        $decimal = 'regex:/\A[0-9]+(?:[.,][0-9]{1,2})?\z/';
        $conLetrasONumeros = 'regex:/[\p{L}\p{N}]/u';

        $validador = Validator::make($entrada, [
            'descripcion' => ['bail', 'required', 'string', 'max:255', $conLetrasONumeros],
            'codigofabricante' => ['bail', 'nullable', 'string', 'max:50'],
            'codigobarra' => [
                'bail', 'nullable', 'string', 'max:50', 'regex:/\A[0-9]+\z/',
                Rule::unique('productos', 'codigobarra'),
            ],
            'idmarca' => ['bail', 'required', 'string',
                $this->reglaSeleccionMaestra('marcas', 'idmarca', 'marca')],
            'idlinea' => ['bail', 'required', 'string',
                $this->reglaSeleccionMaestra('lineas', 'idlinea', 'línea')],
            'idgrupo' => ['bail', 'required', 'string',
                $this->reglaSeleccionMaestra('grupos', 'idgrupo', 'grupo')],
            'idsubgrupo' => ['bail', 'required', 'string',
                $this->reglaSeleccionMaestra('subgrupos', 'idsubgrupo', 'subgrupo')],
            'nueva_marca' => ['exclude_unless:idmarca,nuevo', 'bail', 'required',
                'string', 'max:80', $conLetrasONumeros],
            'nueva_linea' => ['exclude_unless:idlinea,nuevo', 'bail', 'required',
                'string', 'max:80', $conLetrasONumeros],
            'nuevo_grupo' => ['exclude_unless:idgrupo,nuevo', 'bail', 'required',
                'string', 'max:80', $conLetrasONumeros],
            'nuevo_subgrupo' => ['exclude_unless:idsubgrupo,nuevo', 'bail', 'required',
                'string', 'max:100', $conLetrasONumeros],
            'idiva' => ['bail', 'required', 'integer',
                Rule::exists('alicuotas_iva', 'idiva')->where('estado', 1)],
            'idunidad' => ['bail', 'required', 'string',
                $this->reglaSeleccionMaestra(
                    'unidades_medida', 'idunidad', 'unidad de medida'
                )],
            'nueva_unidad' => [
                'exclude_unless:idunidad,nuevo', 'bail', 'required',
                'string', 'max:50', $conLetrasONumeros,
            ],
            'nueva_unidad_abreviatura' => [
                'exclude_unless:idunidad,nuevo', 'bail', 'required',
                'string', 'max:10',
                'regex:/\A[\p{L}\p{N}.\/²³]+(?: [\p{L}\p{N}.\/²³]+)*\z/u',
            ],
            'color' => ['bail', 'nullable', 'string', 'max:80'],
            'contenido_neto' => [
                'bail', 'nullable', 'string', 'max:20',
                'regex:/\A(?:[0-9]+(?:[.,][0-9]{1,3})?|[0-9]+[ \t]*\/[ \t]*[0-9]+)\z/',
                function ($atributo, $valor, $fallar) {
                    $cantidad = $this->normalizarContenidoNeto($valor);
                    if (!is_numeric($cantidad)) {
                        $fallar('La fracción del contenido neto no puede dividirse por cero.');
                    } elseif ((float) $cantidad < 0.001 || (float) $cantidad > 9999999.999) {
                        $fallar('El contenido neto debe estar entre 0,001 y 9.999.999,999.');
                    }
                },
            ],
            'rentabilidadobjetivo' => [
                'bail', 'required', 'string', 'max:14', $decimal,
                function ($atributo, $valor, $fallar) {
                    if ((float) str_replace(',', '.', $valor) > 100) {
                        $fallar('La rentabilidad debe estar entre 0 y 100 %.');
                    }
                },
            ],
            'pvp' => [
                'bail', 'required', 'string', 'max:14', $decimal,
                function ($atributo, $valor, $fallar) {
                    if ((float) str_replace(',', '.', $valor) > 9999999999.99) {
                        $fallar('El precio no puede superar 9.999.999.999,99.');
                    }
                },
            ],
            'imagen' => ['bail', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'visibleweb' => ['required', 'boolean'],
            'destacado' => ['required', 'boolean'],
        ], [
            'required' => 'Completá el campo :attribute.',
            'string' => 'El campo :attribute debe contener texto válido.',
            'integer' => 'Seleccioná una opción válida para :attribute.',
            'exists' => 'La opción de :attribute no existe o está inactiva.',
            'boolean' => 'La opción de :attribute no es válida.',
            'max.string' => 'El campo :attribute admite hasta :max caracteres.',
            'regex' => 'El campo :attribute debe contener al menos una letra o un número.',
            'codigobarra.regex' => 'El código de barras admite solo números, sin espacios ni signos.',
            'codigobarra.unique' => 'Ya existe un producto con ese código de barras. Revisá también los inactivos.',
            'contenido_neto.regex' => 'Usá una cantidad como 4, 0,25 o 1/4, sin escribir la unidad.',
            'rentabilidadobjetivo.regex' => 'La rentabilidad admite números positivos o cero y hasta 2 decimales.',
            'pvp.regex' => 'El precio admite números positivos o cero y hasta 2 decimales, sin separador de miles.',
            'nueva_unidad_abreviatura.regex' =>
                'La abreviatura de la unidad admite letras, números, puntos, barras y espacios simples.',
            'imagen.image' => 'El archivo debe ser una imagen válida.',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max' => 'La imagen no puede superar los 2 MB.',
        ], [
            'descripcion' => 'descripción', 'codigofabricante' => 'código del fabricante',
            'codigobarra' => 'código de barras', 'idmarca' => 'marca', 'idlinea' => 'línea',
            'idgrupo' => 'grupo', 'idsubgrupo' => 'subgrupo', 'nueva_marca' => 'nueva marca',
            'nueva_linea' => 'nueva línea', 'nuevo_grupo' => 'nuevo grupo',
            'nuevo_subgrupo' => 'nuevo subgrupo', 'idiva' => 'IVA', 'idunidad' => 'unidad',
            'nueva_unidad' => 'nombre de la nueva unidad',
            'nueva_unidad_abreviatura' => 'abreviatura de la nueva unidad',
            'contenido_neto' => 'contenido neto', 'rentabilidadobjetivo' => 'rentabilidad',
            'pvp' => 'precio final de venta', 'visibleweb' => 'visible en la web',
            'destacado' => 'producto destacado',
        ]);

        $validador->after(function ($validador) use ($entrada) {
            foreach (['idlinea', 'idgrupo', 'idsubgrupo'] as $campo) {
                if ($validador->errors()->has($campo)) {
                    return;
                }
            }

            if ($entrada['idgrupo'] !== 'nuevo' && (
                $entrada['idlinea'] === 'nuevo'
                || !DB::table('grupos')->where('idgrupo', $entrada['idgrupo'])
                    ->where('idlinea', $entrada['idlinea'])->where('estado', 1)->exists()
            )) {
                $validador->errors()->add('idgrupo', 'Elegí un grupo que pertenezca a la línea seleccionada.');
            }
            if ($entrada['idsubgrupo'] !== 'nuevo' && (
                $entrada['idgrupo'] === 'nuevo'
                || !DB::table('subgrupos')->where('idsubgrupo', $entrada['idsubgrupo'])
                    ->where('idgrupo', $entrada['idgrupo'])->where('estado', 1)->exists()
            )) {
                $validador->errors()->add('idsubgrupo', 'Elegí un subgrupo que pertenezca al grupo seleccionado.');
            }
        });

        $datos = $validador->validate();
        $datos['contenido_neto'] = $this->normalizarContenidoNeto($datos['contenido_neto'] ?? null);
        $datos['rentabilidadobjetivo'] = str_replace(',', '.', $datos['rentabilidadobjetivo']);
        $datos['pvp'] = str_replace(',', '.', $datos['pvp']);
        $rutaImagenGuardada = null;

        try {
            $producto = DB::transaction(function () use ($datos, $request, &$rutaImagenGuardada) {
                $idMarca = $this->resolverSeleccionMaestra(
                    'marcas', 'idmarca', 'nombre', $datos['idmarca'], $datos['nueva_marca'] ?? null
                );
                $idLinea = $this->resolverSeleccionMaestra(
                    'lineas', 'idlinea', 'nombre', $datos['idlinea'], $datos['nueva_linea'] ?? null
                );
                $idGrupo = $this->resolverSeleccionMaestra(
                    'grupos', 'idgrupo', 'nombre', $datos['idgrupo'], $datos['nuevo_grupo'] ?? null,
                    'idlinea', $idLinea
                );
                $idSubgrupo = $this->resolverSeleccionMaestra(
                    'subgrupos', 'idsubgrupo', 'nombre', $datos['idsubgrupo'], $datos['nuevo_subgrupo'] ?? null,
                    'idgrupo', $idGrupo
                );
                $idUnidad = $this->resolverUnidadMedida(
                    (string) $datos['idunidad'],
                    $datos['nueva_unidad'] ?? null,
                    $datos['nueva_unidad_abreviatura'] ?? null
                );

                // Se comprueba incluso cuando se acaba de resolver una marca nueva.
                if (($datos['codigofabricante'] ?? null) !== null && Producto::query()
                    ->where('idmarca', $idMarca)
                    ->where('codigofabricante', $datos['codigofabricante'])->exists()) {
                    throw ValidationException::withMessages([
                        'codigofabricante' => 'Ya existe un producto de esa marca con el mismo código del fabricante.',
                    ]);
                }

                $producto = Producto::create([
                    'codigointerno' => $this->generarCodigoTemporal(),
                    'codigofabricante' => $datos['codigofabricante'] ?? null,
                    'codigobarra' => $datos['codigobarra'] ?? null,
                    'descripcion' => $datos['descripcion'],
                    'idmarca' => $idMarca, 'idsubgrupo' => $idSubgrupo,
                    'color' => $datos['color'] ?? null,
                    'contenido_neto' => $datos['contenido_neto'],
                    'idunidad' => $idUnidad, 'idiva' => (int) $datos['idiva'],
                    'rentabilidadobjetivo' => $datos['rentabilidadobjetivo'], 'pvp' => $datos['pvp'],
                    'rutaimagen' => null, 'visibleweb' => $request->boolean('visibleweb'),
                    'destacado' => $request->boolean('destacado'), 'estado' => 1,
                ]);
                $producto->update([
                    'codigointerno' => $this->generarCodigoInterno($producto->idproducto),
                ]);

                $imagen = $request->file('imagen');
                if ($imagen instanceof UploadedFile) {
                    $rutaImagenGuardada = $this->guardarImagenProducto($imagen, $producto->codigointerno);
                    $producto->update(['rutaimagen' => $rutaImagenGuardada]);
                }

                $this->crearStockInicialDeposito($producto->idproducto);

                return $producto;
            });
        } catch (\Throwable $excepcion) {
            $this->eliminarImagenProducto($rutaImagenGuardada);

            // La restricción única también cubre dos altas enviadas al mismo tiempo.
            if ($excepcion instanceof \Illuminate\Database\QueryException
                && (int) ($excepcion->errorInfo[1] ?? 0) === 1062) {
                $detalleError = (string) ($excepcion->errorInfo[2] ?? '');
                if (str_contains($detalleError, 'codigobarra')) {
                    throw ValidationException::withMessages([
                        'codigobarra' => 'Otro producto ya usa ese código de barras. Revisá también los inactivos.',
                    ]);
                }
                if (str_contains($detalleError, 'uq_productos_marca_fabricante')) {
                    throw ValidationException::withMessages([
                        'codigofabricante' => 'Ya existe un producto de esa marca con el mismo código del fabricante.',
                    ]);
                }
            }

            throw $excepcion;
        }

        $destino = $request->user('web')?->tienePermiso('productos.ver')
            ? redirect()->route('productos.index', ['buscar' => $producto->codigointerno, 'estado' => '1'])
            : redirect()->route('productos.manual.crear');

        return $destino->with('success',
            "Producto {$producto->codigointerno} creado. Su stock inicial en el depósito central es cero."
        );
    }


    /**
     * Muestra los datos actuales de un producto para corregirlos.
     */
    public function editar(Producto $producto)
    {
        $clasificacionActual = DB::table('subgrupos as s')
            ->join('grupos as g', 'g.idgrupo', '=', 's.idgrupo')
            ->where('s.idsubgrupo', $producto->idsubgrupo)
            ->first([
                's.idgrupo',
                'g.idlinea',
            ]);

        $idGrupoActual = $clasificacionActual
            ? (int) $clasificacionActual->idgrupo
            : null;

        $idLineaActual = $clasificacionActual
            ? (int) $clasificacionActual->idlinea
            : null;

        $marcas = DB::table('marcas')
            ->where(function ($query) use ($producto) {
                $query->where('estado', 1)
                    ->orWhere('idmarca', $producto->idmarca);
            })
            ->orderBy('nombre')
            ->get([
                'idmarca',
                'nombre',
                'estado',
            ]);

        $lineas = DB::table('lineas')
            ->where(function ($query) use ($idLineaActual) {
                $query->where('estado', 1);

                if ($idLineaActual !== null) {
                    $query->orWhere('idlinea', $idLineaActual);
                }
            })
            ->orderBy('nombre')
            ->get([
                'idlinea',
                'nombre',
                'estado',
            ]);

        $grupos = DB::table('grupos')
            ->where(function ($query) use ($idGrupoActual) {
                $query->where('estado', 1);

                if ($idGrupoActual !== null) {
                    $query->orWhere('idgrupo', $idGrupoActual);
                }
            })
            ->orderBy('nombre')
            ->get([
                'idgrupo',
                'idlinea',
                'nombre',
                'estado',
            ]);

        $subgrupos = DB::table('subgrupos')
            ->where(function ($query) use ($producto) {
                $query->where('estado', 1)
                    ->orWhere('idsubgrupo', $producto->idsubgrupo);
            })
            ->orderBy('nombre')
            ->get([
                'idsubgrupo',
                'idgrupo',
                'nombre',
                'estado',
            ]);

        $alicuotasIva = DB::table('alicuotas_iva')
            ->where(function ($query) use ($producto) {
                $query->where('estado', 1)
                    ->orWhere('idiva', $producto->idiva);
            })
            ->orderBy('porcentaje')
            ->get([
                'idiva',
                'nombre',
                'porcentaje',
                'estado',
            ]);

        $unidades = DB::table('unidades_medida')
            ->where(function ($query) use ($producto) {
                $query->where('estado', 1);

                if ($producto->idunidad !== null) {
                    $query->orWhere('idunidad', $producto->idunidad);
                }
            })
            ->orderBy('nombre')
            ->get([
                'idunidad',
                'nombre',
                'abreviatura',
                'estado',
            ]);

        return view(
            'productos.editar',
            compact(
                'producto',
                'marcas',
                'lineas',
                'grupos',
                'subgrupos',
                'alicuotasIva',
                'unidades',
                'idLineaActual',
                'idGrupoActual'
            )
        );
    }

    /**
     * Valida y guarda las correcciones realizadas sobre un producto.
     */
    public function actualizar(Request $request, Producto $producto)
    {
        $datosParaValidar = $request->all();

        $validador = Validator::make(
            $datosParaValidar,
            [
                'descripcion' => ['required', 'string', 'max:255'],
                'codigofabricante' => ['nullable', 'string', 'max:50'],
                'codigobarra' => [
                    'bail',
                    'nullable',
                    'string',
                    'max:50',
                    'regex:/\A[0-9]+\z/',
                ],
                'idmarca' => [
                    'required',
                    'integer',
                    Rule::exists('marcas', 'idmarca'),
                ],
                'idlinea' => [
                    'required',
                    'integer',
                    Rule::exists('lineas', 'idlinea'),
                ],
                'idgrupo' => [
                    'required',
                    'integer',
                    Rule::exists('grupos', 'idgrupo'),
                ],
                'idsubgrupo' => [
                    'required',
                    'integer',
                    Rule::exists('subgrupos', 'idsubgrupo'),
                ],
                'idiva' => [
                    'required',
                    'integer',
                    Rule::exists('alicuotas_iva', 'idiva'),
                ],
                'idunidad' => [
                    'required',
                    'integer',
                    Rule::exists('unidades_medida', 'idunidad'),
                ],
                'color' => ['nullable', 'string', 'max:80'],
                'contenido_neto' => [
                    'bail',
                    'nullable',
                    'string',
                    'max:20',
                    'regex:/\A(?:[0-9]+(?:[.,][0-9]{1,3})?|[0-9]+[ \t]*\/[ \t]*[0-9]+)\z/',
                    function ($atributo, $valor, $fallar) {
                        $cantidad = $this->normalizarContenidoNeto($valor);

                        if (!is_numeric($cantidad)) {
                            $fallar('El denominador de la fracción no puede ser cero.');
                            return;
                        }

                        if ((float) $cantidad < 0.001) {
                            $fallar('El contenido neto debe ser mayor que cero y representar al menos 0,001 de la unidad seleccionada.');
                            return;
                        }

                        if ((float) $cantidad > 9999999.999) {
                            $fallar('El contenido neto no puede superar 9999999,999.');
                        }
                    },
                ],
                'rentabilidadobjetivo' => [
                    'required',
                    'numeric',
                    'between:0,100',
                ],
                'pvp' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:9999999999.99',
                ],
                'imagen' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
                ],
                'eliminar_imagen' => ['required', 'boolean'],
                'visibleweb' => ['required', 'boolean'],
                'destacado' => ['required', 'boolean'],
                'estado' => [
                    'required',
                    Rule::in(['0', '1']),
                ],
            ],
            [
                'descripcion.required' =>
                    'La descripción interna es obligatoria.',
                'descripcion.max' =>
                    'La descripción no puede superar los 255 caracteres.',
                'codigobarra.string' =>
                    'El código de barras debe contener solamente números.',
                'codigobarra.regex' =>
                    'El código de barras debe contener solamente números, sin espacios ni símbolos.',
                'codigobarra.max' =>
                    'El código de barras no puede superar los 50 dígitos.',
                'idmarca.required' => 'Debés seleccionar una marca.',
                'idmarca.exists' => 'La marca seleccionada no existe.',
                'idlinea.required' => 'Debés seleccionar una línea.',
                'idlinea.exists' => 'La línea seleccionada no existe.',
                'idgrupo.required' => 'Debés seleccionar un grupo.',
                'idgrupo.exists' => 'El grupo seleccionado no existe.',
                'idsubgrupo.required' =>
                    'Debés seleccionar un subgrupo.',
                'idsubgrupo.exists' =>
                    'El subgrupo seleccionado no existe.',
                'idiva.required' =>
                    'Debés seleccionar una alícuota de IVA.',
                'idiva.exists' =>
                    'La alícuota seleccionada no existe.',
                'idunidad.required' =>
                    'Debés seleccionar una unidad de medida.',
                'idunidad.exists' =>
                    'La unidad de medida seleccionada no existe.',
                'contenido_neto.string' =>
                    'Ingresá una cantidad, por ejemplo 4, 0,25 o 1/4.',
                'contenido_neto.regex' =>
                    'Usá un número con hasta 3 decimales o una fracción como 1/4. No ingreses letras, signos ni separadores de miles.',
                'contenido_neto.max' =>
                    'El contenido neto no puede superar los 20 caracteres.',
                'rentabilidadobjetivo.required' =>
                    'La rentabilidad objetivo es obligatoria.',
                'rentabilidadobjetivo.between' =>
                    'La rentabilidad debe estar entre 0 y 100.',
                'pvp.required' =>
                    'El precio de venta es obligatorio.',
                'pvp.numeric' =>
                    'El precio de venta debe ser numérico.',
                'imagen.image' =>
                    'El archivo seleccionado debe ser una imagen válida.',
                'imagen.mimes' =>
                    'La imagen debe estar en formato JPG, JPEG, PNG o WEBP.',
                'imagen.max' =>
                    'La imagen no puede superar los 2 MB.',
                'eliminar_imagen.required' =>
                    'No se pudo determinar qué hacer con la imagen actual.',
                'eliminar_imagen.boolean' =>
                    'La opción para eliminar la imagen no es válida.',
                'estado.required' =>
                    'Debés indicar el estado del producto.',
                'estado.in' =>
                    'El estado seleccionado no es válido.',
            ]
        );

        $validador->after(function ($validador) use (
            $datosParaValidar,
            $producto
        ) {
            if (
                !$validador->errors()->has('idlinea')
                && !$validador->errors()->has('idgrupo')
                && !DB::table('grupos')
                    ->where('idgrupo', (int) $datosParaValidar['idgrupo'])
                    ->where('idlinea', (int) $datosParaValidar['idlinea'])
                    ->exists()
            ) {
                $validador->errors()->add(
                    'idgrupo',
                    'El grupo seleccionado no pertenece a la línea indicada.'
                );
            }

            if (
                !$validador->errors()->has('idgrupo')
                && !$validador->errors()->has('idsubgrupo')
                && !DB::table('subgrupos')
                    ->where(
                        'idsubgrupo',
                        (int) $datosParaValidar['idsubgrupo']
                    )
                    ->where('idgrupo', (int) $datosParaValidar['idgrupo'])
                    ->exists()
            ) {
                $validador->errors()->add(
                    'idsubgrupo',
                    'El subgrupo seleccionado no pertenece al grupo indicado.'
                );
            }

            $codigoBarra = $validador->errors()->has('codigobarra')
                ? ''
                : trim((string) ($datosParaValidar['codigobarra'] ?? ''));

            if (
                $codigoBarra !== ''
                && Producto::query()
                    ->where('codigobarra', $codigoBarra)
                    ->where('idproducto', '<>', $producto->idproducto)
                    ->exists()
            ) {
                $validador->errors()->add(
                    'codigobarra',
                    'El código de barras ya pertenece a otro producto.'
                );
            }

            $codigoFabricante = trim(
                (string) ($datosParaValidar['codigofabricante'] ?? '')
            );

            if (
                $codigoFabricante !== ''
                && !$validador->errors()->has('idmarca')
                && Producto::query()
                    ->where(
                        'idmarca',
                        (int) $datosParaValidar['idmarca']
                    )
                    ->where('codigofabricante', $codigoFabricante)
                    ->where('idproducto', '<>', $producto->idproducto)
                    ->exists()
            ) {
                $validador->errors()->add(
                    'codigofabricante',
                    'Ese código del fabricante ya existe para la marca seleccionada.'
                );
            }
        });

        $datos = $validador->validate();

        // Convertimos la cantidad solamente después de validar su formato.
        $datos['contenido_neto'] = $this->normalizarContenidoNeto(
            $datos['contenido_neto'] ?? null
        );

        $rutaImagenNueva = null;
        $imagen = $request->file('imagen');

        if ($imagen instanceof UploadedFile) {
            $rutaImagenNueva = $this->guardarImagenProducto(
                $imagen,
                $producto->codigointerno
            );
        }

        $rutaImagenAnterior = null;

        try {
            DB::transaction(function () use (
                $datos,
                $producto,
                $rutaImagenNueva,
                &$rutaImagenAnterior
            ) {
                $productoBloqueado = Producto::query()
                    ->lockForUpdate()
                    ->findOrFail($producto->idproducto);

                $rutaImagenAnterior = $productoBloqueado->rutaimagen;

                $rutaImagenFinal = $rutaImagenNueva
                    ?? (
                        (bool) $datos['eliminar_imagen']
                        ? null
                        : $rutaImagenAnterior
                    );

                $productoBloqueado->update([
                    'descripcion' => $datos['descripcion'],
                    'codigofabricante' =>
                        $datos['codigofabricante'] ?? null,
                    'codigobarra' => $datos['codigobarra'] ?? null,
                    'idmarca' => (int) $datos['idmarca'],
                    'idsubgrupo' => (int) $datos['idsubgrupo'],
                    'color' => $datos['color'] ?? null,
                    'contenido_neto' =>
                        $datos['contenido_neto'] ?? null,
                    'idunidad' => (int) $datos['idunidad'],
                    'rutaimagen' => $rutaImagenFinal,
                    'idiva' => (int) $datos['idiva'],
                    'rentabilidadobjetivo' =>
                        $datos['rentabilidadobjetivo'],
                    'pvp' => $datos['pvp'],
                    'visibleweb' => (bool) $datos['visibleweb'],
                    'destacado' => (bool) $datos['destacado'],
                    'estado' => (int) $datos['estado'],
                ]);

                DetalleCatalogo::query()
                    ->where('idproducto', $productoBloqueado->idproducto)
                    ->update([
                        'idmarca' => $productoBloqueado->idmarca,
                        'idsubgrupo' => $productoBloqueado->idsubgrupo,
                        'idiva' => $productoBloqueado->idiva,
                    ]);
            });
        } catch (\Throwable $excepcion) {
            $this->eliminarImagenProducto($rutaImagenNueva);

            throw $excepcion;
        }

        if (
            $rutaImagenAnterior !== null
            && $rutaImagenAnterior !== $rutaImagenNueva
            && (
                $rutaImagenNueva !== null
                || (bool) $datos['eliminar_imagen']
            )
        ) {
            $this->eliminarImagenProducto($rutaImagenAnterior);
        }

        return redirect()
            ->route(
                'productos.index',
                [
                    'buscar' => $producto->codigointerno,
                    'estado' => 'todos',
                ]
            )
            ->with(
                'success',
                'El producto fue actualizado correctamente.'
            );
    }

    /**
     * Consulta los artículos importados.
     *
     * - pendientes: solamente filas vigentes que todavía no tienen producto interno.
     * - completo: permite consultar todas las listas guardadas de un proveedor,
     *   incluyendo artículos ya vinculados con Productos de Messirve.
     */
    /**
     * Consulta en una sola pantalla los catálogos de proveedores.
     *
     * Permite ver listas vigentes e históricas y filtrar los artículos
     * por su relación con Messirve sin duplicar la interfaz.
     */
    public function catalogo(Request $request)
    {
        $busqueda = trim((string) $request->input('buscar', ''));

        $estadoSolicitado = (string) $request->input(
            'estado_articulo',
            'todos'
        );

        $estadoArticulo = in_array(
            $estadoSolicitado,
            ['todos', 'pendientes', 'vinculados'],
            true
        )
            ? $estadoSolicitado
            : 'todos';

        $idProveedor = $request->input('idproveedor');
        $idProveedorNumero =
            is_numeric($idProveedor) && (int) $idProveedor > 0
                ? (int) $idProveedor
                : null;

        $idCatalogo = $request->input('idcatalogo');
        $idCatalogoNumero =
            is_numeric($idCatalogo) && (int) $idCatalogo > 0
                ? (int) $idCatalogo
                : null;

        /*
         * Recalcula las vigencias antes de armar filtros, tarjetas y acciones.
         * Si hay un proveedor elegido se actualiza sólo ese; si se están
         * consultando todos, se sincronizan todos los proveedores.
         */
        CatalogoProveedor::sincronizarVigencias($idProveedorNumero);

        /*
         * Cuando se selecciona un proveedor recuperamos todas sus listas:
         * la vigente y las históricas.
         */
        $catalogosProveedor = collect();
        $catalogoSeleccionado = null;

        if ($idProveedorNumero !== null) {
            $catalogosProveedor = CatalogoProveedor::query()
                ->where('idproveedor', $idProveedorNumero)
                ->orderByDesc('estado')
                ->orderByRaw('fecha_vigencia IS NULL ASC')
                ->orderByDesc('fecha_vigencia')
                ->orderByDesc('idcatalogoproveedor')
                ->get([
                    'idcatalogoproveedor',
                    'idproveedor',
                    'nombre_lista',
                    'fecha_vigencia',
                    'archivo_original',
                    'descuento_total',
                    'estado',
                    'updated_at',
                ]);

            if ($idCatalogoNumero !== null) {
                $catalogoSeleccionado =
                    $catalogosProveedor->firstWhere(
                        'idcatalogoproveedor',
                        $idCatalogoNumero
                    );

                /*
                 * Evita consultar por URL una lista perteneciente
                 * a otro proveedor.
                 */
                if ($catalogoSeleccionado === null) {
                    $idCatalogoNumero = null;
                }
            }
        } else {
            /*
             * Una lista concreta sólo puede elegirse después de seleccionar
             * el proveedor al que pertenece.
             */
            $idCatalogoNumero = null;
        }

        /*
         * Consulta base. Se conservan tanto artículos vinculados como
         * artículos todavía sin producto interno.
         */
        $consultaBase = DetalleCatalogo::query()
            ->with([
                'catalogoProveedor.proveedor',
                'producto',
            ])
            ->where('estado', 1);

        if ($idCatalogoNumero !== null) {
            $consultaBase->where(
                'idcatalogoproveedor',
                $idCatalogoNumero
            );
        }

        if ($busqueda !== '') {
            $consultaBase->where(
                function ($query) use ($busqueda) {
                    $query
                        ->where(
                            'codigoproveedor',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'codigofabricante',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'codigobarra',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhere(
                            'descripcionproveedor',
                            'like',
                            "%{$busqueda}%"
                        )
                        ->orWhereHas(
                            'producto',
                            function ($producto) use ($busqueda) {
                                $producto
                                    ->where(
                                        'codigointerno',
                                        'like',
                                        "%{$busqueda}%"
                                    )
                                    ->orWhere(
                                        'descripcion',
                                        'like',
                                        "%{$busqueda}%"
                                    );
                            }
                        );
                }
            );
        }

        if ($idProveedorNumero !== null) {
            $consultaBase->whereHas(
                'catalogoProveedor',
                function ($query) use ($idProveedorNumero) {
                    $query->where(
                        'idproveedor',
                        $idProveedorNumero
                    );
                }
            );
        }

        /*
         * Los contadores se calculan antes del filtro de estado para que
         * siempre indiquen la composición real del proveedor/lista elegidos.
         */
        $cantidadPendientesResultado =
            (clone $consultaBase)
                ->whereNull('idproducto')
                ->count();

        $cantidadVinculadosResultado =
            (clone $consultaBase)
                ->whereNotNull('idproducto')
                ->count();

        $consulta = clone $consultaBase;

        if ($estadoArticulo === 'pendientes') {
            /*
             * "Pendientes de revisión" mantiene el comportamiento anterior:
             * sólo son accionables los artículos sin producto interno que
             * pertenecen a la lista vigente.
             */
            $consulta
                ->whereNull('idproducto')
                ->whereHas(
                    'catalogoProveedor',
                    function ($query) {
                        $query->where('estado', 1);
                    }
                );
        } elseif ($estadoArticulo === 'vinculados') {
            $consulta->whereNotNull('idproducto');
        }

        /*
         * Agrupa primero el mismo código del proveedor y después las
         * versiones de lista. De esta forma comparar precios históricos
         * resulta más directo cuando se muestran "Todas las listas".
         */
        $consulta
            ->orderByRaw('codigoproveedor IS NULL ASC')
            ->orderBy('codigoproveedor')
            ->orderByDesc('idcatalogoproveedor')
            ->orderBy('descripcionproveedor');

        $detalles = $consulta
            ->paginate(20)
            ->withQueryString();

        /*
         * Se incluyen proveedores inactivos porque su historial comercial
         * continúa siendo válido para consulta.
         */
        $proveedores = DB::table('proveedores')
            ->orderBy('razonsocial')
            ->get([
                'idproveedor',
                'razonsocial',
                'estado',
            ]);

        $proveedorSeleccionado =
            $idProveedorNumero !== null
                ? $proveedores->firstWhere(
                    'idproveedor',
                    $idProveedorNumero
                )
                : null;

        $cantidadListas =
            $idProveedorNumero !== null
                ? $catalogosProveedor->count()
                : CatalogoProveedor::query()->count();

        return view(
            'productos.catalogo',
            compact(
                'detalles',
                'proveedores',
                'busqueda',
                'estadoArticulo',
                'idProveedor',
                'idCatalogoNumero',
                'catalogosProveedor',
                'catalogoSeleccionado',
                'proveedorSeleccionado',
                'cantidadPendientesResultado',
                'cantidadVinculadosResultado',
                'cantidadListas'
            )
        );
    }

    /**
     * Muestra el formulario para crear o vincular un producto
     * a partir de una fila importada del catálogo.
     */
    public function crearDesdeCatalogo(DetalleCatalogo $detalle)
    {
        if ($detalle->idproducto !== null || !$detalle->estado) {
            return redirect()
                ->route('productos.catalogo')
                ->with(
                    'error',
                    'El artículo seleccionado ya fue procesado o está inactivo.'
                );
        }

        $detalle->load('catalogoProveedor.proveedor');

        if ($detalle->catalogoProveedor !== null) {
            CatalogoProveedor::sincronizarVigencias(
                (int) $detalle->catalogoProveedor->idproveedor
            );

            $detalle->unsetRelations();
            $detalle->load('catalogoProveedor.proveedor');
        }

        if (
            $detalle->catalogoProveedor === null
            || !$detalle->catalogoProveedor->estado
        ) {
            return redirect()
                ->route('productos.catalogo')
                ->with(
                    'error',
                    'El catálogo al que pertenece el artículo no está activo.'
                );
        }

        $marcas = DB::table('marcas')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get(['idmarca', 'nombre']);

        $lineas = DB::table('lineas')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get(['idlinea', 'nombre']);

        $grupos = DB::table('grupos')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get([
                'idgrupo',
                'idlinea',
                'nombre',
            ]);

        $subgrupos = DB::table('subgrupos')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get([
                'idsubgrupo',
                'idgrupo',
                'nombre',
            ]);

        $alicuotasIva = DB::table('alicuotas_iva')
            ->where('estado', 1)
            ->orderBy('porcentaje')
            ->get([
                'idiva',
                'nombre',
                'porcentaje',
            ]);

        $unidades = DB::table('unidades_medida')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get([
                'idunidad',
                'nombre',
                'abreviatura',
            ]);

        $productosExistentes = Producto::query()
            ->where('estado', 1)
            ->orderBy('descripcion')
            ->get([
                'idproducto',
                'codigointerno',
                'codigobarra',
                'descripcion',
            ]);

        /*
         * Las clasificaciones del Excel se utilizan únicamente como
         * sugerencias. No se insertan registros maestros al abrir la vista.
         */
        $nombreMarcaSugerida =
            $detalle->catalogoProveedor?->proveedor?->razonsocial;

        $idMarcaSugerida = $detalle->idmarca
            ?: $this->buscarIdCoincidente(
                $marcas,
                'idmarca',
                'nombre',
                $nombreMarcaSugerida,
                true
            );

        $idLineaSugerida = $this->buscarIdCoincidente(
            $lineas,
            'idlinea',
            'nombre',
            $detalle->linea_proveedor
        );

        $idGrupoSugerido = $this->buscarIdCoincidente(
            $grupos,
            'idgrupo',
            'nombre',
            $detalle->grupo_proveedor,
            false,
            'idlinea',
            $idLineaSugerida
        );

        $subgrupoAsignado = $detalle->idsubgrupo
            ? $subgrupos->firstWhere(
                'idsubgrupo',
                (int) $detalle->idsubgrupo
            )
            : null;

        if ($subgrupoAsignado !== null) {
            $idSubgrupoSugerido =
                (int) $subgrupoAsignado->idsubgrupo;

            $idGrupoSugerido =
                (int) $subgrupoAsignado->idgrupo;

            $grupoAsignado = $grupos->firstWhere(
                'idgrupo',
                $idGrupoSugerido
            );

            if ($grupoAsignado !== null) {
                $idLineaSugerida =
                    (int) $grupoAsignado->idlinea;
            }
        } else {
            $idSubgrupoSugerido = $this->buscarIdCoincidente(
                $subgrupos,
                'idsubgrupo',
                'nombre',
                $detalle->subgrupo_proveedor,
                false,
                'idgrupo',
                $idGrupoSugerido
            );
        }

        $sugerencias = [
            'marca_nombre' => $nombreMarcaSugerida,
            'idmarca' => $idMarcaSugerida,
            'linea_nombre' => $detalle->linea_proveedor,
            'idlinea' => $idLineaSugerida,
            'grupo_nombre' => $detalle->grupo_proveedor,
            'idgrupo' => $idGrupoSugerido,
            'subgrupo_nombre' => $detalle->subgrupo_proveedor,
            'idsubgrupo' => $idSubgrupoSugerido,
        ];

        return view(
            'productos.crear-desde-catalogo',
            compact(
                'detalle',
                'marcas',
                'lineas',
                'grupos',
                'subgrupos',
                'alicuotasIva',
                'unidades',
                'productosExistentes',
                'sugerencias'
            )
        );
    }

    /**
     * Crea un producto nuevo o vincula el artículo del catálogo
     * con uno que ya existe.
     */
    public function guardarDesdeCatalogo(
        Request $request,
        DetalleCatalogo $detalle
    ) {
        $detalle->load('catalogoProveedor');

        if ($detalle->catalogoProveedor !== null) {
            CatalogoProveedor::sincronizarVigencias(
                (int) $detalle->catalogoProveedor->idproveedor
            );

            $detalle->unsetRelations();
            $detalle->load('catalogoProveedor');
        }

        if (
            $detalle->catalogoProveedor === null
            || !$detalle->catalogoProveedor->estado
        ) {
            return redirect()
                ->route('productos.catalogo')
                ->with(
                    'error',
                    'El artículo ya no pertenece a la lista vigente del proveedor.'
                );
        }

        $accion = $request->validate(
            [
                'accion' => [
                    'required',
                    Rule::in(['nuevo', 'existente']),
                ],
            ],
            [
                'accion.required' =>
                    'Debés indicar qué querés hacer con el artículo.',

                'accion.in' =>
                    'La acción seleccionada no es válida.',
            ]
        )['accion'];

        if ($accion === 'existente') {
            return $this->vincularProductoExistente(
                $request,
                $detalle
            );
        }

        return $this->crearProductoNuevo(
            $request,
            $detalle
        );
    }

    /**
     * Relaciona la fila del proveedor con un producto ya creado.
     */
    private function vincularProductoExistente(
        Request $request,
        DetalleCatalogo $detalle
    ) {
        $datos = $request->validate(
            [
                'idproducto_existente' => [
                    'required',
                    'integer',
                    Rule::exists('productos', 'idproducto')
                        ->where('estado', 1),
                ],
            ],
            [
                'idproducto_existente.required' =>
                    'Debés seleccionar un producto existente.',

                'idproducto_existente.exists' =>
                    'El producto seleccionado no existe o está inactivo.',
            ]
        );

        DB::transaction(function () use ($datos, $detalle) {
            $detalleBloqueado = DetalleCatalogo::query()
                ->lockForUpdate()
                ->findOrFail($detalle->getKey());

            if ($detalleBloqueado->idproducto !== null) {
                throw ValidationException::withMessages([
                    'idproducto_existente' =>
                        'Este artículo ya fue relacionado con un producto.',
                ]);
            }

            $producto = Producto::query()
                ->where('estado', 1)
                ->findOrFail((int) $datos['idproducto_existente']);

            $this->vincularDetallesDelProveedor(
                $detalleBloqueado,
                $producto
            );

            $this->crearStockInicialDeposito($producto->idproducto);
        });

        return redirect()
            ->route('productos.index')
            ->with(
                'success',
                'El artículo fue vinculado con el producto existente.'
            );
    }

    /**
     * Crea el producto interno, genera su código, inicializa el
     * stock de cada sucursal y vincula la fila del catálogo.
     */
    private function crearProductoNuevo(
        Request $request,
        DetalleCatalogo $detalle
    ) {

    $datosParaValidar = $request->all();

    $datosParaValidar['contenido_neto'] =
        $this->normalizarContenidoNeto(
            $request->input('contenido_neto')
    );
        $validador = Validator::make(
        $datosParaValidar,
            [
                'descripcion' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'codigofabricante' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'codigobarra' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'idmarca' => [
                    'required',
                    $this->reglaSeleccionMaestra(
                        'marcas',
                        'idmarca',
                        'marca'
                    ),
                ],

                'nueva_marca' => [
                    'nullable',
                    'required_if:idmarca,nuevo',
                    'string',
                    'max:80',
                ],

                'idlinea' => [
                    'required',
                    $this->reglaSeleccionMaestra(
                        'lineas',
                        'idlinea',
                        'línea'
                    ),
                ],

                'nueva_linea' => [
                    'nullable',
                    'required_if:idlinea,nuevo',
                    'string',
                    'max:80',
                ],

                'idgrupo' => [
                    'required',
                    $this->reglaSeleccionMaestra(
                        'grupos',
                        'idgrupo',
                        'grupo'
                    ),
                ],

                'nuevo_grupo' => [
                    'nullable',
                    'required_if:idgrupo,nuevo',
                    'string',
                    'max:80',
                ],

                'idsubgrupo' => [
                    'required',
                    $this->reglaSeleccionMaestra(
                        'subgrupos',
                        'idsubgrupo',
                        'subgrupo'
                    ),
                ],

                'nuevo_subgrupo' => [
                    'nullable',
                    'required_if:idsubgrupo,nuevo',
                    'string',
                    'max:100',
                ],

                'idiva' => [
                    'required',
                    'integer',
                    Rule::exists('alicuotas_iva', 'idiva')
                        ->where('estado', 1),
                ],

                'idunidad' => [
                    'required',
                    'integer',
                    Rule::exists('unidades_medida', 'idunidad')
                        ->where('estado', 1),
                ],

                'color' => [
                    'nullable',
                    'string',
                    'max:80',
                ],

                'contenido_neto' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:9999999.999',
                ],

                'rentabilidadobjetivo' => [
                    'required',
                    'numeric',
                    'between:0,100',
                ],

                'pvp' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:9999999999.99',
                ],

                'imagen' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
                ],

                'visibleweb' => [
                    'required',
                    'boolean',
                ],

                'destacado' => [
                    'required',
                    'boolean',
                ],
            ],
            [
                'descripcion.required' =>
                    'La descripción interna es obligatoria.',

                'descripcion.max' =>
                    'La descripción no puede superar los 255 caracteres.',

                'idmarca.required' =>
                    'Debés seleccionar una marca.',

                'idmarca.exists' =>
                    'La marca seleccionada no existe o está inactiva.',

                'nueva_marca.required_if' =>
                    'Debés escribir el nombre de la nueva marca.',

                'idlinea.required' =>
                    'Debés seleccionar o crear una línea.',

                'nueva_linea.required_if' =>
                    'Debés escribir el nombre de la nueva línea.',

                'idgrupo.required' =>
                    'Debés seleccionar o crear un grupo.',

                'nuevo_grupo.required_if' =>
                    'Debés escribir el nombre del nuevo grupo.',

                'idsubgrupo.required' =>
                    'Debés seleccionar un subgrupo.',

                'idsubgrupo.exists' =>
                    'El subgrupo seleccionado no existe o está inactivo.',

                'nuevo_subgrupo.required_if' =>
                    'Debés escribir el nombre del nuevo subgrupo.',

                'idiva.required' =>
                    'Debés seleccionar una alícuota de IVA.',

                'idiva.exists' =>
                    'La alícuota seleccionada no existe o está inactiva.',

                'idunidad.required' =>
                    'Debés seleccionar una unidad de medida.',

                'idunidad.exists' =>
                    'La unidad seleccionada no existe o está inactiva.',

                'contenido_neto.numeric' =>
                'El contenido neto debe ser un número o una fracción válida, por ejemplo 1/4.',

                'rentabilidadobjetivo.required' =>
                    'La rentabilidad objetivo es obligatoria.',

                'rentabilidadobjetivo.between' =>
                    'La rentabilidad debe estar entre 0 y 100.',

                'pvp.required' =>
                    'El precio de venta es obligatorio.',

                'pvp.numeric' =>
                    'El precio de venta debe ser numérico.',

                'imagen.image' =>
                    'El archivo seleccionado debe ser una imagen válida.',

                'imagen.mimes' =>
                    'La imagen debe estar en formato JPG, JPEG, PNG o WEBP.',

                'imagen.max' =>
                    'La imagen no puede superar los 2 MB.',
            ]
        );

        $validador->after(function ($validador) use ($request) {
            if (
                $validador->errors()->has('idlinea')
                || $validador->errors()->has('idgrupo')
                || $validador->errors()->has('idsubgrupo')
            ) {
                return;
            }

            $idLinea = (string) $request->input('idlinea');
            $idGrupo = (string) $request->input('idgrupo');
            $idSubgrupo = (string) $request->input('idsubgrupo');

            if ($idGrupo !== 'nuevo') {
                if ($idLinea === 'nuevo') {
                    $validador->errors()->add(
                        'idgrupo',
                        'Un grupo existente no puede pertenecer a una línea nueva.'
                    );
                } elseif (!DB::table('grupos')
                    ->where('idgrupo', (int) $idGrupo)
                    ->where('idlinea', (int) $idLinea)
                    ->where('estado', 1)
                    ->exists()) {
                    $validador->errors()->add(
                        'idgrupo',
                        'El grupo seleccionado no pertenece a la línea indicada.'
                    );
                }
            }

            if ($idSubgrupo !== 'nuevo') {
                if ($idGrupo === 'nuevo') {
                    $validador->errors()->add(
                        'idsubgrupo',
                        'Un subgrupo existente no puede pertenecer a un grupo nuevo.'
                    );
                } elseif (!DB::table('subgrupos')
                    ->where('idsubgrupo', (int) $idSubgrupo)
                    ->where('idgrupo', (int) $idGrupo)
                    ->where('estado', 1)
                    ->exists()) {
                    $validador->errors()->add(
                        'idsubgrupo',
                        'El subgrupo seleccionado no pertenece al grupo indicado.'
                    );
                }
            }
        });

        $datos = $validador->validate();

        $rutaImagenGuardada = null;

        try {
            $idProducto = DB::transaction(
                function () use (
                    $datos,
                    $detalle,
                    $request,
                    &$rutaImagenGuardada
                ) {
                $detalleBloqueado = DetalleCatalogo::query()
                    ->lockForUpdate()
                    ->findOrFail($detalle->getKey());

                if ($detalleBloqueado->idproducto !== null) {
                    throw ValidationException::withMessages([
                        'descripcion' =>
                            'Este artículo ya fue relacionado con un producto.',
                    ]);
                }

                $idMarca = $this->resolverSeleccionMaestra(
                    'marcas',
                    'idmarca',
                    'nombre',
                    (string) $datos['idmarca'],
                    $datos['nueva_marca'] ?? null
                );

                $idLinea = $this->resolverSeleccionMaestra(
                    'lineas',
                    'idlinea',
                    'nombre',
                    (string) $datos['idlinea'],
                    $datos['nueva_linea'] ?? null
                );

                $idGrupo = $this->resolverSeleccionMaestra(
                    'grupos',
                    'idgrupo',
                    'nombre',
                    (string) $datos['idgrupo'],
                    $datos['nuevo_grupo'] ?? null,
                    'idlinea',
                    $idLinea
                );

                $idSubgrupo = $this->resolverSeleccionMaestra(
                    'subgrupos',
                    'idsubgrupo',
                    'nombre',
                    (string) $datos['idsubgrupo'],
                    $datos['nuevo_subgrupo'] ?? null,
                    'idgrupo',
                    $idGrupo
                );

                $datosProducto = [
                    'codigointerno' =>
                        $this->generarCodigoTemporal(),

                    'codigofabricante' =>
                        $datos['codigofabricante']
                        ?? $detalleBloqueado->codigofabricante,

                    'codigobarra' =>
                        $datos['codigobarra']
                        ?? $detalleBloqueado->codigobarra,

                    'descripcion' => $datos['descripcion'],
                    'idmarca' => $idMarca,
                    'idsubgrupo' => $idSubgrupo,
                    'color' => $datos['color'] ?? null,
                    'contenido_neto' =>
                        $datos['contenido_neto'] ?? null,
                    'idunidad' => (int) $datos['idunidad'],
                    'rutaimagen' => null,
                    'idiva' => (int) $datos['idiva'],
                    'rentabilidadobjetivo' =>
                        $datos['rentabilidadobjetivo'],
                    'pvp' => $datos['pvp'],
                    'visibleweb' => $request->boolean('visibleweb'),
                    'destacado' => $request->boolean('destacado'),
                    'estado' => 1,
                ];

                $producto = Producto::create($datosProducto);

                $producto->update([
                    'codigointerno' =>
                        $this->generarCodigoInterno(
                            $producto->idproducto
                        ),
                ]);

                $imagen = $request->file('imagen');

                if ($imagen instanceof UploadedFile) {
                    $rutaImagenGuardada =
                        $this->guardarImagenProducto(
                            $imagen,
                            $producto->codigointerno
                        );

                    $producto->update([
                        'rutaimagen' => $rutaImagenGuardada,
                    ]);
                }

                $this->crearStockInicialDeposito($producto->idproducto);

                $this->vincularDetallesDelProveedor(
                    $detalleBloqueado,
                    $producto
                );

                return $producto->idproducto;
                }
            );
        } catch (\Throwable $excepcion) {
            $this->eliminarImagenProducto($rutaImagenGuardada);

            throw $excepcion;
        }

        return redirect()
            ->route('productos.index')
            ->with(
                'success',
                "El producto MES fue creado correctamente con el identificador {$idProducto}."
            );
    }

    /**
     * Busca una coincidencia normalizada entre el texto recibido
     * y los registros maestros activos.
     */
    private function buscarIdCoincidente(
        iterable $registros,
        string $campoId,
        string $campoNombre,
        ?string $valorBuscado,
        bool $permitirCoincidenciaParcial = false,
        ?string $campoPadre = null,
        ?int $idPadre = null
    ): ?int {
        $buscado = $this->normalizarNombre($valorBuscado);

        if ($buscado === '') {
            return null;
        }

        $candidatos = [];

        foreach ($registros as $registro) {
            if (
                $campoPadre !== null
                && (
                    $idPadre === null
                    || (int) data_get($registro, $campoPadre) !== $idPadre
                )
            ) {
                continue;
            }

            $nombre = $this->normalizarNombre(
                (string) data_get($registro, $campoNombre)
            );

            if ($nombre === $buscado) {
                return (int) data_get($registro, $campoId);
            }

            $candidatos[] = [$registro, $nombre];
        }

        if (!$permitirCoincidenciaParcial) {
            return null;
        }

        foreach ($candidatos as [$registro, $nombre]) {
            if (
                strlen($nombre) >= 4
                && (
                    str_contains($buscado, $nombre)
                    || str_contains($nombre, $buscado)
                )
            ) {
                return (int) data_get($registro, $campoId);
            }
        }

        return null;
    }

    /**
     * Permite seleccionar un registro existente o solicitar uno nuevo.
     */
    private function reglaSeleccionMaestra(
        string $tabla,
        string $campoId,
        string $etiqueta
    ): \Closure {
        return function ($atributo, $valor, $fallar) use (
            $tabla,
            $campoId,
            $etiqueta
        ) {
            if ($valor === 'nuevo') {
                return;
            }

            if (
                !ctype_digit((string) $valor)
                || !DB::table($tabla)
                    ->where($campoId, (int) $valor)
                    ->where('estado', 1)
                    ->exists()
            ) {
                $fallar(
                    "El registro de {$etiqueta} seleccionado no existe o está inactivo."
                );
            }
        };
    }

    /**
     * Devuelve el identificador seleccionado o crea/reactiva el
     * registro confirmado por el dueño dentro de la transacción.
     */
    private function resolverSeleccionMaestra(
        string $tabla,
        string $campoId,
        string $campoNombre,
        string $seleccion,
        ?string $nombreNuevo,
        ?string $campoPadre = null,
        ?int $idPadre = null
    ): int {
        if ($seleccion !== 'nuevo') {
            return (int) $seleccion;
        }

        $nombreNuevo = trim((string) $nombreNuevo);
        $normalizado = $this->normalizarNombre($nombreNuevo);

        $consulta = DB::table($tabla);

        if ($campoPadre !== null) {
            $consulta->where($campoPadre, $idPadre);
        }

        $registros = $consulta
            ->lockForUpdate()
            ->get([
                $campoId,
                $campoNombre,
                'estado',
            ]);

        foreach ($registros as $registro) {
            if (
                $this->normalizarNombre(
                    (string) data_get($registro, $campoNombre)
                ) === $normalizado
            ) {
                $idEncontrado =
                    (int) data_get($registro, $campoId);

                if (!(bool) $registro->estado) {
                    DB::table($tabla)
                        ->where($campoId, $idEncontrado)
                        ->update([
                            'estado' => 1,
                            'updated_at' => now(),
                        ]);
                }

                return $idEncontrado;
            }
        }

        $nuevoRegistro = [
            $campoNombre => $nombreNuevo,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($campoPadre !== null) {
            $nuevoRegistro[$campoPadre] = $idPadre;
        }

        return (int) DB::table($tabla)
            ->insertGetId($nuevoRegistro);
    }

    /**
     * Devuelve la unidad elegida o crea/reactiva una unidad nueva.
     */
    private function resolverUnidadMedida(
        string $seleccion,
        ?string $nombreNuevo,
        ?string $abreviaturaNueva
    ): int {
        if ($seleccion !== 'nuevo') {
            return (int) $seleccion;
        }

        $nombreNuevo = trim((string) $nombreNuevo);
        $abreviaturaNueva = trim((string) $abreviaturaNueva);
        $nombreNormalizado = $this->normalizarNombre($nombreNuevo);
        $abreviaturaNormalizada = Str::lower(
            Str::ascii($abreviaturaNueva)
        );

        $unidades = DB::table('unidades_medida')
            ->lockForUpdate()
            ->get(['idunidad', 'nombre', 'abreviatura', 'estado']);

        foreach ($unidades as $unidad) {
            $mismoNombre = $this->normalizarNombre(
                (string) $unidad->nombre
            ) === $nombreNormalizado;

            $mismaAbreviatura = Str::lower(
                Str::ascii(trim((string) $unidad->abreviatura))
            ) === $abreviaturaNormalizada;

            if (!$mismoNombre && !$mismaAbreviatura) {
                continue;
            }

            if ($mismoNombre && !$mismaAbreviatura) {
                throw ValidationException::withMessages([
                    'nueva_unidad' =>
                        "La unidad {$unidad->nombre} ya existe con la abreviatura {$unidad->abreviatura}.",
                ]);
            }

            if (!$mismoNombre && $mismaAbreviatura) {
                throw ValidationException::withMessages([
                    'nueva_unidad_abreviatura' =>
                        "La abreviatura {$abreviaturaNueva} ya pertenece a la unidad {$unidad->nombre}.",
                ]);
            }

            $idUnidad = (int) $unidad->idunidad;

            if (!(bool) $unidad->estado) {
                DB::table('unidades_medida')
                    ->where('idunidad', $idUnidad)
                    ->update([
                        'estado' => 1,
                    ]);
            }

            return $idUnidad;
        }

        return (int) DB::table('unidades_medida')->insertGetId([
            'nombre' => $nombreNuevo,
            'abreviatura' => $abreviaturaNueva,
            'estado' => 1,
        ]);
    }

    /**
     * Uniforma mayúsculas, tildes, espacios y signos para comparar
     * nombres sin generar duplicados como "Arquitec" y "Arquitec.".
     */
    private function normalizarNombre(?string $valor): string
    {
        $valor = Str::lower(
            Str::ascii(trim((string) $valor))
        );

        $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor) ?? '';

        return trim(
            preg_replace('/\s+/', ' ', $valor) ?? ''
        );
    }

    /**
     * Crea un registro de stock en cero para cada sucursal activa.
     */
    /**
 * Vincula todas las apariciones históricas del mismo artículo
 * del proveedor con un único producto interno de Messirve.
 */

private function vincularDetallesDelProveedor(
    DetalleCatalogo $detalle,
    Producto $producto
): void {
    $idProveedor = DB::table('catalogo_proveedor')
        ->where(
            'idcatalogoproveedor',
            $detalle->idcatalogoproveedor
        )
        ->value('idproveedor');

    if ($idProveedor === null) {
        throw ValidationException::withMessages([
            'producto' =>
                'No se pudo identificar el proveedor del catálogo.',
        ]);
    }

    $codigoProveedor = trim(
        (string) $detalle->codigoproveedor
    );

    /*
     * Es una protección adicional. Actualmente el código
     * del proveedor ya es obligatorio durante la importación.
     */
    if ($codigoProveedor === '') {
        $detalle->update([
            'idproducto' => $producto->idproducto,
            'idmarca' => $producto->idmarca,
            'idsubgrupo' => $producto->idsubgrupo,
            'idiva' => $producto->idiva,
        ]);

        return;
    }

    /*
     * Busca el mismo código en todas las listas, vigentes
     * e históricas, pertenecientes al mismo proveedor.
     */
    $detallesEquivalentes = DetalleCatalogo::query()
        ->where(
            'codigoproveedor',
            $codigoProveedor
        )
        ->whereHas(
            'catalogoProveedor',
            function ($query) use ($idProveedor) {
                $query->where(
                    'idproveedor',
                    (int) $idProveedor
                );
            }
        )
        ->lockForUpdate()
        ->get([
            'iddetallecatalogo',
            'idproducto',
        ]);

    /*
     * Evita relacionar accidentalmente el mismo código del
     * proveedor con dos productos internos diferentes.
     */
    $existeConflicto = $detallesEquivalentes->contains(
        function ($detalleEquivalente) use ($producto) {
            return $detalleEquivalente->idproducto !== null
                && (int) $detalleEquivalente->idproducto
                    !== (int) $producto->idproducto;
        }
    );

    if ($existeConflicto) {
        throw ValidationException::withMessages([
            'producto' =>
                'Este código del proveedor ya está vinculado con otro producto interno.',
        ]);
    }

    DetalleCatalogo::query()
        ->whereIn(
            'iddetallecatalogo',
            $detallesEquivalentes->pluck(
                'iddetallecatalogo'
            )
        )
        ->update([
            'idproducto' => $producto->idproducto,
            'idmarca' => $producto->idmarca,
            'idsubgrupo' => $producto->idsubgrupo,
            'idiva' => $producto->idiva,
        ]);
}
    /**
     * Guarda la imagen física y devuelve la ruta relativa para MySQL.
     */
    private function guardarImagenProducto(
        UploadedFile $imagen,
        string $codigoInterno
    ): string {
        $baseNombre = Str::slug($codigoInterno);

        if ($baseNombre === '') {
            $baseNombre = 'producto';
        }

        $nombreArchivo = $baseNombre
            . '-'
            . Str::lower((string) Str::uuid())
            . '.'
            . Str::lower($imagen->extension());

        $ruta = $imagen->storeAs(
            'productos',
            $nombreArchivo,
            'public'
        );

        if (!is_string($ruta) || $ruta === '') {
            throw ValidationException::withMessages([
                'imagen' =>
                    'No se pudo guardar la imagen. Intentá nuevamente.',
            ]);
        }

        return $ruta;
    }

    /**
     * Elimina únicamente imágenes administradas por este módulo.
     */
    private function eliminarImagenProducto(?string $ruta): void
    {
        if (
            $ruta === null
            || !Str::startsWith($ruta, 'productos/')
        ) {
            return;
        }

        try {
            Storage::disk('public')->delete($ruta);
        } catch (\Throwable) {
            // La base ya quedó consistente; el archivo puede limpiarse luego.
        }
    }

    /**
     * Convierte fracciones a la cantidad decimal almacenada en MySQL.
     */
private function normalizarContenidoNeto(mixed $valor): mixed
{
    if ($valor === null) {
        return null;
    }

    $texto = trim((string) $valor);

    if ($texto === '') {
        return null;
    }

    /*
     * También permite decimales escritos con coma.
     */
    $texto = str_replace(',', '.', $texto);

    /*
     * Convierte valores como 1/4, 1/2 o 3/4.
     */
    if (preg_match(
        '/^(\d+)\s*\/\s*(\d+)$/',
        $texto,
        $coincidencias
    )) {
        $numerador = (int) $coincidencias[1];
        $denominador = (int) $coincidencias[2];

        if ($denominador === 0) {
            return $texto;
        }

        return round(
            $numerador / $denominador,
            3
        );
    }

    return $texto;
}
   /**
 * Crea el stock inicial en cero únicamente en el depósito central.
 */
    private function crearStockInicialDeposito(int $idProducto): void
    {
        $idDeposito = DB::table('sucursales')
            ->where('tipo', 'DEPOSITO')
            ->where('estado', 1)
            ->orderBy('idsucursal')
            ->value('idsucursal');

        if ($idDeposito === null) {
            throw ValidationException::withMessages([
                'producto' =>
                    'No existe un depósito central activo para inicializar el stock.',
            ]);
        }

        Stock::firstOrCreate(
            [
                'idproducto' => $idProducto,
                'idsucursal' => (int) $idDeposito,
            ],
            [
                'stockactual' => 0,
                'stockminimo' => 0,
                'stockmaximo' => 0,
                'estado' => 1,
            ]
        );
    }

    /**
     * Código provisorio único, utilizado hasta conocer el idproducto.
     */
    private function generarCodigoTemporal(): string
    {
        do {
            $codigo = 'TMP-' . Str::upper(Str::random(16));
        } while (
            Producto::where('codigointerno', $codigo)->exists()
        );

        return $codigo;
    }

    /**
     * Genera códigos internos como MES-000001.
     */
    private function generarCodigoInterno(int $idProducto): string
    {
        return 'MES-'
            . str_pad(
                (string) $idProducto,
                6,
                '0',
                STR_PAD_LEFT
            );
    }
}
