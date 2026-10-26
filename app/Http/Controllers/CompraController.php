<?php

namespace App\Http\Controllers;

use App\Models\CatalogoProveedor;
use App\Models\Compra;
use App\Models\DetalleCatalogo;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompraController extends Controller
{
    /**
     * Muestra el listado general de compras a proveedores.
     */
    public function index(Request $request): View
    {
        $usuario = auth('web')->user();

        $busqueda = trim((string) $request->query('buscar', ''));
        $estado = strtolower(trim((string) $request->query('estado', 'todos')));
        $idProveedor = (int) $request->query('idproveedor', 0);
        $idSucursal = (int) $request->query('idsucursal', 0);
        $fechaDesde = trim((string) $request->query('fecha_desde', ''));
        $fechaHasta = trim((string) $request->query('fecha_hasta', ''));

        $estadosPermitidos = ['todos', 'pendiente', 'parcial', 'recibida', 'anulada'];
        if (!in_array($estado, $estadosPermitidos, true)) {
            $estado = 'todos';
        }

        $fechaDesde = $this->normalizarFecha($fechaDesde);
        $fechaHasta = $this->normalizarFecha($fechaHasta);

        $esDueno = $usuario?->rol?->nombre === 'DUEÑO';

        $consulta = Compra::query()
            ->with([
                'proveedor:idproveedor,razonsocial',
                'sucursal:idsucursal,nombre',
                'usuario:id,nombre,apellido',
            ]);

        $this->aplicarAlcanceSucursal($consulta, $esDueno, $usuario?->idsucursal);

        if ($busqueda !== '') {
            $consulta->where(function (Builder $subconsulta) use ($busqueda) {
                $numeroBuscado = preg_replace('/\D+/', '', $busqueda) ?? '';

                $subconsulta
                    ->where('numerocomprobante', 'like', '%' . $busqueda . '%')
                    ->orWhere('tipocomprobante', 'like', '%' . $busqueda . '%')
                    ->orWhereHas('proveedor', function (Builder $proveedor) use ($busqueda) {
                        $proveedor->where('razonsocial', 'like', '%' . $busqueda . '%');
                    });

                if ($numeroBuscado !== '') {
                    $subconsulta->orWhere('idcompra', (int) $numeroBuscado);
                }
            });
        }

        if ($estado !== 'todos') {
            $consulta->where('estado', strtoupper($estado));
        }

        if ($idProveedor > 0) {
            $consulta->where('idproveedor', $idProveedor);
        }

        if ($esDueno && $idSucursal > 0) {
            $consulta->where('idsucursal', $idSucursal);
        }

        if ($fechaDesde !== '') {
            $consulta->whereDate('fecha', '>=', $fechaDesde);
        }

        if ($fechaHasta !== '') {
            $consulta->whereDate('fecha', '<=', $fechaHasta);
        }

        $consultaResumen = clone $consulta;

        $resumen = $consultaResumen
            ->reorder()
            ->selectRaw("COUNT(*) AS total")
            ->selectRaw("SUM(CASE WHEN estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendientes")
            ->selectRaw("SUM(CASE WHEN estado = 'PARCIAL' THEN 1 ELSE 0 END) AS parciales")
            ->selectRaw("SUM(CASE WHEN estado = 'RECIBIDA' THEN 1 ELSE 0 END) AS recibidas")
            ->selectRaw("SUM(CASE WHEN estado = 'ANULADA' THEN 1 ELSE 0 END) AS anuladas")
            ->first();

        $compras = $consulta
            ->orderByDesc('fecha')
            ->orderByDesc('idcompra')
            ->paginate(12)
            ->withQueryString();

        $proveedores = Proveedor::query()
            ->orderBy('razonsocial')
            ->get(['idproveedor', 'razonsocial', 'estado']);

        $sucursales = $esDueno
            ? Sucursal::query()
                ->where('estado', 1)
                ->orderBy('nombre')
                ->get(['idsucursal', 'nombre', 'tipo'])
            : Sucursal::query()
                ->where('idsucursal', (int) ($usuario?->idsucursal ?? 0))
                ->get(['idsucursal', 'nombre', 'tipo']);

        return view('compras.index', compact(
            'compras',
            'proveedores',
            'sucursales',
            'resumen',
            'busqueda',
            'estado',
            'idProveedor',
            'idSucursal',
            'fechaDesde',
            'fechaHasta',
            'esDueno'
        ));
    }

    /**
     * Primera etapa de Nueva compra.
     *
     * Todavía no persiste compras. Permite seleccionar la sucursal,
     * buscar artículos vigentes, comparar proveedores y armar la lista.
     */
    public function crear(): View
    {
        CatalogoProveedor::sincronizarVigencias();

        $usuario = auth('web')->user();
        $esDueno = $usuario?->rol?->nombre === 'DUEÑO';

        $sucursales = $esDueno
            ? Sucursal::query()
                ->where('estado', 1)
                ->orderByRaw("CASE WHEN tipo = 'DEPOSITO' THEN 0 ELSE 1 END")
                ->orderBy('nombre')
                ->get(['idsucursal', 'nombre', 'tipo'])
            : Sucursal::query()
                ->where('estado', 1)
                ->where('idsucursal', (int) ($usuario?->idsucursal ?? 0))
                ->get(['idsucursal', 'nombre', 'tipo']);

        return view('compras.crear', compact(
            'sucursales',
            'esDueno'
        ));
    }

    /**
     * Busca artículos dentro de las listas vigentes y devuelve sus ofertas.
     *
     * La equivalencia usa el mismo criterio seguro del comparador:
     * - mismo producto interno, cuando ya existe;
     * - código de barras exacto;
     * - si no existe ninguno de los dos, la fila queda como oferta individual.
     */
    public function buscarArticulos(Request $request): JsonResponse
    {
        $datos = $request->validate(
            [
                'buscar' => ['required', 'string', 'min:2', 'max:255'],
            ],
            [
                'buscar.required' => 'Ingresá un producto para buscar.',
                'buscar.min' => 'Escribí al menos 2 caracteres.',
                'buscar.max' => 'La búsqueda es demasiado larga.',
            ]
        );

        CatalogoProveedor::sincronizarVigencias();

        $busqueda = trim((string) $datos['buscar']);
        $like = '%' . $busqueda . '%';

        $referencias = DetalleCatalogo::query()
            ->with([
                'catalogoProveedor.proveedor',
                'producto.alicuotaIva',
                'alicuotaIva',
            ])
            ->where('estado', 1)
            ->whereHas('catalogoProveedor', function (Builder $query): void {
                $query->where('estado', 1);
            })
            ->whereHas('catalogoProveedor.proveedor', function (Builder $query): void {
                $query->where('estado', 1);
            })
            ->where(function (Builder $query) use ($like): void {
                $query
                    ->where('descripcionproveedor', 'like', $like)
                    ->orWhere('codigoproveedor', 'like', $like)
                    ->orWhere('codigofabricante', 'like', $like)
                    ->orWhere('codigobarra', 'like', $like)
                    ->orWhereHas('producto', function (Builder $producto) use ($like): void {
                        $producto
                            ->where('codigointerno', 'like', $like)
                            ->orWhere('descripcion', 'like', $like)
                            ->orWhere('codigofabricante', 'like', $like)
                            ->orWhere('codigobarra', 'like', $like);
                    });
            })
            ->orderBy('descripcionproveedor')
            ->limit(20)
            ->get();

        $grupos = [];
        $firmasProcesadas = [];

        foreach ($referencias as $referencia) {
            $idProducto = $referencia->idproducto !== null
                ? (int) $referencia->idproducto
                : null;

            $codigoBarra = trim((string) ($referencia->codigobarra ?? ''));

            $consultaEquivalentes = DetalleCatalogo::query()
                ->with([
                    'catalogoProveedor.proveedor',
                    'producto.alicuotaIva',
                    'alicuotaIva',
                ])
                ->where('estado', 1)
                ->whereHas('catalogoProveedor', function (Builder $query): void {
                    $query->where('estado', 1);
                })
                ->whereHas('catalogoProveedor.proveedor', function (Builder $query): void {
                    $query->where('estado', 1);
                });

            if ($idProducto !== null || $codigoBarra !== '') {
                $consultaEquivalentes->where(
                    function (Builder $query) use ($idProducto, $codigoBarra): void {
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
                    }
                );
            } else {
                $consultaEquivalentes->whereKey($referencia->iddetallecatalogo);
            }

            $equivalentes = $consultaEquivalentes->get();

            $firma = $equivalentes
                ->pluck('iddetallecatalogo')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->implode('-');

            if ($firma === '' || isset($firmasProcesadas[$firma])) {
                continue;
            }

            $firmasProcesadas[$firma] = true;

            $ofertas = $equivalentes
                ->map(
                    fn (DetalleCatalogo $oferta): array =>
                        $this->calcularOferta($oferta, $referencia)
                )
                ->sortBy(function (array $oferta): array {
                    return [
                        $oferta['es_comparable'] ? 0 : 1,
                        $oferta['costo_comparable'] ?? PHP_FLOAT_MAX,
                        mb_strtolower((string) $oferta['proveedor']['nombre']),
                    ];
                })
                ->values();

            /*
             * Una lista vigente por proveedor debería producir una sola oferta.
             * Si por datos importados existieran filas repetidas, se conserva la
             * alternativa más económica de cada proveedor.
             */
            $ofertas = $ofertas
                ->groupBy('proveedor.id')
                ->map(function ($ofertasProveedor) {
                    return $ofertasProveedor
                        ->sortBy(function (array $oferta): array {
                            return [
                                $oferta['es_comparable'] ? 0 : 1,
                                $oferta['costo_comparable'] ?? PHP_FLOAT_MAX,
                            ];
                        })
                        ->first();
                })
                ->values();

            $comparables = $ofertas
                ->filter(fn (array $oferta): bool => $oferta['es_comparable'])
                ->sortBy('costo_comparable')
                ->values();

            $idMejor = $comparables->first()['iddetallecatalogo'] ?? null;

            $ofertas = $ofertas
                ->map(function (array $oferta) use ($idMejor): array {
                    $oferta['es_mejor'] =
                        $idMejor !== null
                        && $oferta['iddetallecatalogo'] === $idMejor;

                    return $oferta;
                })
                ->sortBy(function (array $oferta): array {
                    return [
                        $oferta['es_comparable'] ? 0 : 1,
                        $oferta['costo_comparable'] ?? PHP_FLOAT_MAX,
                    ];
                })
                ->values();

            $producto = $referencia->producto;

            /*
             * La clave del grupo debe ser estable entre búsquedas.
             * Antes se usaba grupo-0, grupo-1..., por lo que una búsqueda nueva
             * podía pisar un artículo que ya estaba agregado al pedido.
             */
            if ($idProducto !== null) {
                $claveGrupo = 'producto-' . $idProducto;
            } elseif ($codigoBarra !== '') {
                $claveGrupo = 'barra-' . $codigoBarra;
            } else {
                $claveGrupo = 'detalle-' . (int) $referencia->iddetallecatalogo;
            }

            $grupos[] = [
                'grupo' => $claveGrupo,
                'descripcion' => $producto?->descripcion
                    ?: $referencia->descripcionproveedor,
                'codigo_mes' => $producto?->codigointerno,
                'codigo_barra' => $codigoBarra !== '' ? $codigoBarra : null,
                'idproducto' => $idProducto,
                'requiere_producto_mes' => $idProducto === null,
                'criterio' => $idProducto !== null
                    ? ($codigoBarra !== '' ? 'producto_y_codigo' : 'producto')
                    : ($codigoBarra !== '' ? 'codigo_barra' : 'fila_individual'),
                'ofertas' => $ofertas->all(),
            ];

            if (count($grupos) >= 12) {
                break;
            }
        }

        return response()->json([
            'buscar' => $busqueda,
            'cantidad' => count($grupos),
            'resultados' => $grupos,
        ]);
    }

    /**
     * Devuelve los datos necesarios para preparar un producto Messirve
     * que todavía no existe. En esta etapa no guarda nada.
     */
    public function prepararProducto(DetalleCatalogo $detalle): JsonResponse
    {
        $detalle->load([
            'catalogoProveedor.proveedor',
            'alicuotaIva',
            'producto.alicuotaIva',
        ]);

        if ($detalle->catalogoProveedor !== null) {
            CatalogoProveedor::sincronizarVigencias(
                (int) $detalle->catalogoProveedor->idproveedor
            );

            $detalle->unsetRelations();
            $detalle->load([
                'catalogoProveedor.proveedor',
                'alicuotaIva',
                'producto.alicuotaIva',
            ]);
        }

        if (
            !$detalle->estado
            || $detalle->idproducto !== null
            || $detalle->catalogoProveedor === null
            || !$detalle->catalogoProveedor->estado
            || !$detalle->catalogoProveedor->proveedor
            || !$detalle->catalogoProveedor->proveedor->estado
        ) {
            return response()->json(
                [
                    'message' =>
                        'El artículo ya fue vinculado o dejó de pertenecer a una lista vigente.',
                ],
                409
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
            ->get(['idgrupo', 'idlinea', 'nombre']);

        $subgrupos = DB::table('subgrupos')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get(['idsubgrupo', 'idgrupo', 'nombre']);

        $alicuotasIva = DB::table('alicuotas_iva')
            ->where('estado', 1)
            ->orderBy('porcentaje')
            ->get(['idiva', 'nombre', 'porcentaje']);

        $unidades = DB::table('unidades_medida')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get(['idunidad', 'nombre', 'abreviatura']);

        $proveedor = $detalle->catalogoProveedor->proveedor;
        $nombreMarcaSugerida = $proveedor->razonsocial;

        $idMarcaSugerida = $detalle->idmarca
            ?: $this->buscarIdCoincidenteCompra(
                $marcas,
                'idmarca',
                'nombre',
                $nombreMarcaSugerida,
                true
            );

        $idLineaSugerida = $this->buscarIdCoincidenteCompra(
            $lineas,
            'idlinea',
            'nombre',
            $detalle->linea_proveedor
        );

        $idGrupoSugerido = $this->buscarIdCoincidenteCompra(
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
            $idSubgrupoSugerido = (int) $subgrupoAsignado->idsubgrupo;
            $idGrupoSugerido = (int) $subgrupoAsignado->idgrupo;

            $grupoAsignado = $grupos->firstWhere(
                'idgrupo',
                $idGrupoSugerido
            );

            if ($grupoAsignado !== null) {
                $idLineaSugerida = (int) $grupoAsignado->idlinea;
            }
        } else {
            $idSubgrupoSugerido = $this->buscarIdCoincidenteCompra(
                $subgrupos,
                'idsubgrupo',
                'nombre',
                $detalle->subgrupo_proveedor,
                false,
                'idgrupo',
                $idGrupoSugerido
            );
        }

        /*
         * Reutilizamos exactamente la misma lógica de costo del comparador.
         */
        $precioOferta = $this->calcularOferta($detalle, $detalle);

        return response()->json([
            'detalle' => [
                'iddetallecatalogo' => (int) $detalle->iddetallecatalogo,
                'descripcion' => $detalle->descripcionproveedor,
                'codigo_proveedor' => $detalle->codigoproveedor,
                'codigo_fabricante' => $detalle->codigofabricante,
                'codigo_barra' => $detalle->codigobarra,
                'proveedor' => $proveedor->razonsocial,
                'lista' => $detalle->catalogoProveedor->nombre_lista,
                'clasificacion' => collect([
                    $detalle->linea_proveedor,
                    $detalle->tipo_producto_proveedor,
                    $detalle->grupo_proveedor,
                    $detalle->subgrupo_proveedor,
                ])->filter()->implode(' · '),
            ],
            'precio' => [
                'precio_lista' => $precioOferta['precio_lista'],
                'descuento_producto' => $precioOferta['descuento_producto'],
                'descuento_general' => $precioOferta['descuento_general'],
                'precio_neto_informado' => $precioOferta['precio_neto_informado'],
                'costo_proveedor' => $precioOferta['costo_proveedor'],
                'origen_costo' => $precioOferta['origen_costo'],
                'precios_incluyen_iva' => $precioOferta['precios_incluyen_iva'],
            ],
            'opciones' => [
                'marcas' => $marcas,
                'lineas' => $lineas,
                'grupos' => $grupos,
                'subgrupos' => $subgrupos,
                'alicuotas' => $alicuotasIva,
                'unidades' => $unidades,
            ],
            'sugerencias' => [
                'idmarca' => $idMarcaSugerida,
                'marca_nombre' => $nombreMarcaSugerida,
                'idlinea' => $idLineaSugerida,
                'linea_nombre' => $detalle->linea_proveedor,
                'idgrupo' => $idGrupoSugerido,
                'grupo_nombre' => $detalle->grupo_proveedor,
                'idsubgrupo' => $idSubgrupoSugerido,
                'subgrupo_nombre' => $detalle->subgrupo_proveedor,
                'idiva' => $detalle->idiva !== null
                    ? (int) $detalle->idiva
                    : null,
                'rentabilidad' =>
                    (float) ($detalle->catalogoProveedor->rentabilidad_predeterminada ?? 0),
            ],
        ]);
    }

    private function buscarIdCoincidenteCompra(
        iterable $registros,
        string $campoId,
        string $campoNombre,
        ?string $valorBuscado,
        bool $permitirCoincidenciaParcial = false,
        ?string $campoPadre = null,
        ?int $idPadre = null
    ): ?int {
        $buscado = $this->normalizarNombreCompra($valorBuscado);

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

            $nombre = $this->normalizarNombreCompra(
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

    private function normalizarNombreCompra(?string $valor): string
    {
        return Str::of((string) $valor)
            ->ascii()
            ->lower()
            ->replaceMatches('/\\s+/', ' ')
            ->trim()
            ->value();
    }

    /**
     * Calcula el costo de una oferta con la misma regla usada por el
     * comparador de proveedores actual.
     */
    private function calcularOferta(
        DetalleCatalogo $oferta,
        DetalleCatalogo $referencia
    ): array {
        $catalogo = $oferta->catalogoProveedor;
        $proveedor = $catalogo?->proveedor;

        $precioLista = (float) $oferta->preciolista;
        $descuentoProducto = (float) $oferta->descuentoproducto;
        $descuentoGeneral = (float) ($catalogo?->descuento_total ?? 0);

        $precioNetoInformado = $oferta->precioneto !== null
            ? (float) $oferta->precioneto
            : null;

        if ($precioNetoInformado !== null && $precioNetoInformado > 0) {
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
            $ivaPorcentaje = (float) $oferta->alicuotaIva->porcentaje;
        } elseif ($oferta->producto?->alicuotaIva !== null) {
            $ivaPorcentaje = (float) $oferta->producto->alicuotaIva->porcentaje;
        } elseif ($referencia->alicuotaIva !== null) {
            $ivaPorcentaje = (float) $referencia->alicuotaIva->porcentaje;
        } elseif ($referencia->producto?->alicuotaIva !== null) {
            $ivaPorcentaje = (float) $referencia->producto->alicuotaIva->porcentaje;
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
                $esComparable = false;
                $ivaPudoNormalizarse = false;
            }
        }

        return [
            'iddetallecatalogo' => (int) $oferta->iddetallecatalogo,
            'idproducto' => $oferta->idproducto !== null
                ? (int) $oferta->idproducto
                : null,
            'descripcion' => $oferta->descripcionproveedor,
            'codigo_proveedor' => $oferta->codigoproveedor,
            'codigo_barra' => $oferta->codigobarra,
            'proveedor' => [
                'id' => (int) ($proveedor?->idproveedor ?? 0),
                'nombre' => $proveedor?->razonsocial ?? 'Proveedor sin nombre',
            ],
            'catalogo' => [
                'id' => (int) ($catalogo?->idcatalogoproveedor ?? 0),
                'nombre' => $catalogo?->nombre_lista ?? 'Lista vigente',
                'vigencia' => $catalogo?->fecha_vigencia?->format('d/m/Y'),
            ],
            'precio_lista' => round($precioLista, 6),
            'descuento_producto' => round($descuentoProducto, 2),
            'descuento_general' => round($descuentoGeneral, 4),
            'precio_neto_informado' => $precioNetoInformado,
            'costo_proveedor' => round($costoAntesIva, 6),
            'costo_comparable' => $esComparable
                ? round($costoComparable, 6)
                : null,
            'origen_costo' => $origenCosto,
            'precios_incluyen_iva' => $preciosIncluyenIva,
            'iva_porcentaje' => $ivaPorcentaje,
            'iva_pudo_normalizarse' => $ivaPudoNormalizarse,
            'es_comparable' => $esComparable,
            'es_mejor' => false,
        ];
    }

    /**
     * Confirma la lista armada en Nueva compra.
     *
     * Regla principal:
     * - revalida catálogo, proveedor, sucursal y precios en el servidor;
     * - crea productos MES sólo cuando todavía no existen;
     * - crea stock inicial en cero únicamente en la sucursal destino;
     * - separa automáticamente la lista en una compra por proveedor;
     * - NO aumenta stock y NO genera movimientos todavía.
     */
    public function guardar(Request $request): JsonResponse
    {
        $validador = Validator::make(
            $request->all(),
            [
                'idsucursal' => [
                    'required',
                    'integer',
                    Rule::exists('sucursales', 'idsucursal')->where('estado', 1),
                ],
                'productos' => ['required', 'array', 'min:1', 'max:100'],
                'productos.*.iddetallecatalogo' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('detalle_catalogo', 'iddetallecatalogo'),
                ],
                'productos.*.cantidad' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:999999',
                ],
                'productos.*.preparacion' => ['nullable', 'array'],
            ],
            [
                'idsucursal.required' => 'Seleccioná la sucursal o depósito que recibirá la mercadería.',
                'idsucursal.exists' => 'La sucursal seleccionada ya no está disponible.',
                'productos.required' => 'Agregá al menos un producto al pedido.',
                'productos.min' => 'Agregá al menos un producto al pedido.',
                'productos.max' => 'El pedido no puede superar los 100 productos distintos.',
                'productos.*.iddetallecatalogo.distinct' => 'El mismo artículo no puede repetirse dos veces en el pedido.',
                'productos.*.cantidad.min' => 'La cantidad pedida debe ser mayor a cero.',
            ]
        );

        $datos = $validador->validate();
        $usuario = auth('web')->user();

        if ($usuario === null) {
            return response()->json([
                'message' => 'La sesión administrativa venció. Volvé a iniciar sesión.',
            ], 401);
        }

        $idSucursal = (int) $datos['idsucursal'];
        $this->comprobarSucursalDestino($idSucursal, $usuario);

        // Antes de bloquear filas sincronizamos qué listas son realmente vigentes.
        CatalogoProveedor::sincronizarVigencias();

        $resultado = DB::transaction(function () use (
            $datos,
            $usuario,
            $idSucursal
        ): array {
            $lineasPorProveedor = [];
            $productosCreados = [];
            $firmas = [];

            foreach ($datos['productos'] as $indice => $solicitado) {
                $detalle = DetalleCatalogo::query()
                    ->with([
                        'catalogoProveedor.proveedor',
                        'alicuotaIva',
                        'producto.alicuotaIva',
                    ])
                    ->lockForUpdate()
                    ->findOrFail((int) $solicitado['iddetallecatalogo']);

                $catalogo = $detalle->catalogoProveedor;
                $proveedor = $catalogo?->proveedor;

                if (
                    !$detalle->estado
                    || $catalogo === null
                    || !$catalogo->estado
                    || $proveedor === null
                    || !$proveedor->estado
                ) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.iddetallecatalogo" =>
                            'Una de las ofertas dejó de estar vigente. Volvé a buscar el artículo antes de confirmar.',
                    ]);
                }

                $oferta = $this->calcularOferta($detalle, $detalle);

                if (
                    !array_key_exists('costo_proveedor', $oferta)
                    || (float) $oferta['costo_proveedor'] < 0
                ) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.iddetallecatalogo" =>
                            'No se pudo calcular el costo de una de las ofertas.',
                    ]);
                }

                // Protección contra dos filas del mismo artículo físico dentro del mismo pedido.
                $firma = $detalle->idproducto !== null
                    ? 'producto-' . (int) $detalle->idproducto
                    : (
                        trim((string) $detalle->codigobarra) !== ''
                            ? 'barra-' . trim((string) $detalle->codigobarra)
                            : 'detalle-' . (int) $detalle->iddetallecatalogo
                    );

                if (isset($firmas[$firma])) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.iddetallecatalogo" =>
                            'El mismo artículo aparece más de una vez en la lista. Dejalo en una sola línea y ajustá la cantidad.',
                    ]);
                }
                $firmas[$firma] = true;

                $producto = $this->resolverProductoCompra(
                    $detalle,
                    is_array($solicitado['preparacion'] ?? null)
                        ? $solicitado['preparacion']
                        : null,
                    $idSucursal,
                    $indice,
                    $productosCreados
                );

                $alicuota = DB::table('alicuotas_iva')
                    ->where('idiva', (int) $producto->idiva)
                    ->where('estado', 1)
                    ->first(['idiva', 'porcentaje']);

                if ($alicuota === null) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.preparacion.idiva" =>
                            'El producto no posee una alícuota de IVA activa.',
                    ]);
                }

                $porcentajeIva = (float) $alicuota->porcentaje;
                if ($porcentajeIva < 0 || $porcentajeIva > 100) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.preparacion.idiva" =>
                            'El porcentaje de IVA del producto no es válido.',
                    ]);
                }

                $cantidad = (int) $solicitado['cantidad'];
                $costoProveedor = (float) $oferta['costo_proveedor'];

                // detalle_compra guarda el costo neto sin IVA. Si el archivo del
                // proveedor ya lo incluía, lo retiramos usando el IVA confirmado
                // del producto MES; si no lo incluía, el costo ya estaba neto.
                $costoUnitario = (bool) $oferta['precios_incluyen_iva']
                    ? round($costoProveedor / (1 + ($porcentajeIva / 100)), 6)
                    : round($costoProveedor, 6);

                $subtotal = round($costoUnitario * $cantidad, 6);
                $importeIva = round($subtotal * ($porcentajeIva / 100), 6);
                $totalLinea = round($subtotal + $importeIva, 6);
                $idProveedor = (int) $proveedor->idproveedor;

                $lineasPorProveedor[$idProveedor] ??= [
                    'proveedor' => $proveedor,
                    'lineas' => [],
                ];

                $lineasPorProveedor[$idProveedor]['lineas'][] = [
                    'idproducto' => (int) $producto->idproducto,
                    'iddetallecatalogo' => (int) $detalle->iddetallecatalogo,
                    'idiva' => (int) $producto->idiva,
                    'cantidad' => $cantidad,
                    'cantidadrecibida' => 0,
                    'costounitario' => $costoUnitario,
                    'subtotal' => $subtotal,
                    'importeiva' => $importeIva,
                    'totallinea' => $totalLinea,
                ];
            }

            $comprasCreadas = [];

            foreach ($lineasPorProveedor as $idProveedor => $grupo) {
                $totalCompra = round(
                    collect($grupo['lineas'])->sum('totallinea'),
                    2
                );

                $compra = Compra::create([
                    'idproveedor' => (int) $idProveedor,
                    'idsucursal' => $idSucursal,
                    'idusuario' => (int) $usuario->id,
                    'fecha' => now('UTC'),
                    'tipocomprobante' => null,
                    'numerocomprobante' => null,
                    'total' => $totalCompra,
                    'estado' => 'PENDIENTE',
                    'observacion' => 'Pedido generado desde comparación de proveedores.',
                ]);

                foreach ($grupo['lineas'] as $linea) {
                    $compra->detalles()->create($linea);
                }

                $comprasCreadas[] = [
                    'idcompra' => (int) $compra->idcompra,
                    'codigo' => $this->codigoCompra((int) $compra->idcompra),
                    'proveedor' => (string) $grupo['proveedor']->razonsocial,
                    'cantidad_productos' => count($grupo['lineas']),
                    'total' => $totalCompra,
                    'estado' => 'PENDIENTE',
                ];
            }

            return [
                'compras' => $comprasCreadas,
                'productos_creados' => $productosCreados,
            ];
        }, 3);

        return response()->json([
            'message' => count($resultado['compras']) === 1
                ? 'El pedido fue registrado correctamente.'
                : 'Los pedidos fueron registrados correctamente.',
            'compras' => $resultado['compras'],
            'productos_creados' => $resultado['productos_creados'],
            'stock_actualizado' => false,
        ], 201);
    }

    /**
     * Comprueba que el usuario pueda preparar la compra para la ubicación elegida.
     */
    private function comprobarSucursalDestino(int $idSucursal, $usuario): void
    {
        $sucursal = Sucursal::query()
            ->where('estado', 1)
            ->find($idSucursal);

        if ($sucursal === null) {
            throw ValidationException::withMessages([
                'idsucursal' => 'La sucursal o depósito seleccionado no está activo.',
            ]);
        }

        $esDueno = $usuario?->rol?->nombre === 'DUEÑO';

        if (!$esDueno && (int) ($usuario?->idsucursal ?? 0) !== $idSucursal) {
            throw ValidationException::withMessages([
                'idsucursal' => 'Tu usuario no puede registrar compras para esa sucursal.',
            ]);
        }
    }

    /**
     * Devuelve el producto ya existente o crea el producto MES dentro de la
     * misma transacción que registra la compra.
     */
    private function resolverProductoCompra(
        DetalleCatalogo $detalle,
        ?array $preparacion,
        int $idSucursal,
        int $indice,
        array &$productosCreados
    ): Producto {
        if ($detalle->idproducto !== null) {
            $producto = Producto::query()
                ->where('estado', 1)
                ->lockForUpdate()
                ->find((int) $detalle->idproducto);

            if ($producto === null) {
                throw ValidationException::withMessages([
                    "productos.{$indice}.iddetallecatalogo" =>
                        'El producto MES vinculado ya no está activo.',
                ]);
            }

            $this->crearStockDestino($producto->idproducto, $idSucursal);
            return $producto;
        }

        $codigoBarraDetalle = trim((string) ($detalle->codigobarra ?? ''));

        // Si otro catálogo ya dio de alta exactamente este código de barras,
        // se reutiliza el producto en lugar de generar un MES duplicado.
        if ($codigoBarraDetalle !== '') {
            $productoPorBarra = Producto::query()
                ->where('codigobarra', $codigoBarraDetalle)
                ->lockForUpdate()
                ->first();

            if ($productoPorBarra !== null) {
                if (!$productoPorBarra->estado) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.iddetallecatalogo" =>
                            "El código de barras {$codigoBarraDetalle} pertenece a un producto MES inactivo. Reactivalo o corregí la vinculación antes de comprar.",
                    ]);
                }

                $this->vincularDetallesEquivalentesCompra($detalle, $productoPorBarra);
                $this->crearStockDestino($productoPorBarra->idproducto, $idSucursal);
                return $productoPorBarra;
            }
        }

        if ($preparacion === null) {
            throw ValidationException::withMessages([
                "productos.{$indice}.preparacion" =>
                    'Completá los datos MES del producto antes de confirmar el pedido.',
            ]);
        }

        $datos = $this->validarPreparacionProductoCompra(
            $preparacion,
            $detalle,
            $indice
        );

        $idMarca = $this->resolverSeleccionMaestraCompra(
            'marcas',
            'idmarca',
            'nombre',
            (string) $datos['idmarca'],
            $datos['nueva_marca'] ?? null
        );

        $idLinea = $this->resolverSeleccionMaestraCompra(
            'lineas',
            'idlinea',
            'nombre',
            (string) $datos['idlinea'],
            $datos['nueva_linea'] ?? null
        );

        $idGrupo = $this->resolverSeleccionMaestraCompra(
            'grupos',
            'idgrupo',
            'nombre',
            (string) $datos['idgrupo'],
            $datos['nuevo_grupo'] ?? null,
            'idlinea',
            $idLinea
        );

        $idSubgrupo = $this->resolverSeleccionMaestraCompra(
            'subgrupos',
            'idsubgrupo',
            'nombre',
            (string) $datos['idsubgrupo'],
            $datos['nuevo_subgrupo'] ?? null,
            'idgrupo',
            $idGrupo
        );

        $idUnidad = $this->resolverUnidadMedidaCompra(
            (string) $datos['idunidad'],
            $datos['nueva_unidad'] ?? null,
            $datos['nueva_unidad_abreviatura'] ?? null
        );

        $producto = Producto::create([
            'codigointerno' => $this->generarCodigoTemporalCompra(),
            'codigofabricante' => $datos['codigofabricante']
                ?? $detalle->codigofabricante,
            'codigobarra' => $datos['codigobarra']
                ?? ($codigoBarraDetalle !== '' ? $codigoBarraDetalle : null),
            'descripcion' => $datos['descripcion'],
            'idmarca' => $idMarca,
            'idsubgrupo' => $idSubgrupo,
            'color' => $datos['color'] ?? null,
            'contenido_neto' => $datos['contenido_neto'] ?? null,
            'idunidad' => $idUnidad,
            'rutaimagen' => null,
            'idiva' => (int) $datos['idiva'],
            'rentabilidadobjetivo' => $datos['rentabilidadobjetivo'],
            'pvp' => $datos['pvp'],
            'visibleweb' => false,
            'destacado' => false,
            'estado' => true,
        ]);

        $producto->update([
            'codigointerno' => $this->generarCodigoInternoCompra(
                (int) $producto->idproducto
            ),
        ]);

        $this->vincularDetallesEquivalentesCompra($detalle, $producto);
        $this->crearStockDestino($producto->idproducto, $idSucursal);

        $productosCreados[] = [
            'idproducto' => (int) $producto->idproducto,
            'codigo' => (string) $producto->codigointerno,
            'descripcion' => (string) $producto->descripcion,
        ];

        return $producto;
    }

    /**
     * Valida nuevamente en backend todos los datos preparados por el modal.
     */
    private function validarPreparacionProductoCompra(
        array $entrada,
        DetalleCatalogo $detalle,
        int $indice
    ): array {
        foreach ([
            'descripcion',
            'codigofabricante',
            'codigobarra',
            'color',
            'nueva_marca',
            'nueva_linea',
            'nuevo_grupo',
            'nuevo_subgrupo',
            'nueva_unidad',
            'nueva_unidad_abreviatura',
            'contenido_neto',
        ] as $campo) {
            if (array_key_exists($campo, $entrada) && is_string($entrada[$campo])) {
                $entrada[$campo] = trim($entrada[$campo]);
                if ($entrada[$campo] === '') {
                    $entrada[$campo] = null;
                }
            }
        }

        if (($entrada['codigobarra'] ?? null) === null) {
            $barra = trim((string) ($detalle->codigobarra ?? ''));
            $entrada['codigobarra'] = $barra !== '' ? $barra : null;
        }

        if (($entrada['codigofabricante'] ?? null) === null) {
            $codigoFabricante = trim((string) ($detalle->codigofabricante ?? ''));
            $entrada['codigofabricante'] = $codigoFabricante !== ''
                ? $codigoFabricante
                : null;
        }

        $entrada['contenido_neto'] = $this->normalizarContenidoNetoCompra(
            $entrada['contenido_neto'] ?? null
        );

        $seleccion = function (string $tabla, string $campoId, string $etiqueta): \Closure {
            return function ($atributo, $valor, $fallar) use ($tabla, $campoId, $etiqueta): void {
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
                    $fallar("El registro de {$etiqueta} seleccionado no existe o está inactivo.");
                }
            };
        };

        $validador = Validator::make(
            $entrada,
            [
                'descripcion' => ['required', 'string', 'max:255'],
                'codigofabricante' => ['nullable', 'string', 'max:50'],
                'codigobarra' => [
                    'nullable',
                    'string',
                    'max:50',
                    'regex:/\\A[0-9]+\\z/',
                    Rule::unique('productos', 'codigobarra'),
                ],
                'idmarca' => ['required', 'string', $seleccion('marcas', 'idmarca', 'marca')],
                'idlinea' => ['required', 'string', $seleccion('lineas', 'idlinea', 'línea')],
                'idgrupo' => ['required', 'string', $seleccion('grupos', 'idgrupo', 'grupo')],
                'idsubgrupo' => ['required', 'string', $seleccion('subgrupos', 'idsubgrupo', 'subgrupo')],
                'nueva_marca' => ['exclude_unless:idmarca,nuevo', 'required', 'string', 'max:80'],
                'nueva_linea' => ['exclude_unless:idlinea,nuevo', 'required', 'string', 'max:80'],
                'nuevo_grupo' => ['exclude_unless:idgrupo,nuevo', 'required', 'string', 'max:80'],
                'nuevo_subgrupo' => ['exclude_unless:idsubgrupo,nuevo', 'required', 'string', 'max:100'],
                'idiva' => [
                    'required',
                    'integer',
                    Rule::exists('alicuotas_iva', 'idiva')->where('estado', 1),
                ],
                'idunidad' => ['required', 'string', $seleccion('unidades_medida', 'idunidad', 'unidad')],
                'nueva_unidad' => ['exclude_unless:idunidad,nuevo', 'required', 'string', 'max:50'],
                'nueva_unidad_abreviatura' => ['exclude_unless:idunidad,nuevo', 'required', 'string', 'max:10'],
                'color' => ['nullable', 'string', 'max:80'],
                'contenido_neto' => ['nullable', 'numeric', 'min:0', 'max:9999999.999'],
                'rentabilidadobjetivo' => ['required', 'numeric', 'between:0,100'],
                'pvp' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            ],
            [
                'descripcion.required' => 'La descripción interna es obligatoria.',
                'codigobarra.regex' => 'El código de barras admite solamente números.',
                'codigobarra.unique' => 'Ese código de barras ya pertenece a otro producto MES.',
                'idiva.required' => 'Seleccioná una alícuota de IVA.',
                'idiva.exists' => 'La alícuota de IVA seleccionada ya no está activa.',
                'contenido_neto.numeric' => 'El contenido neto debe ser una cantidad válida.',
                'rentabilidadobjetivo.between' => 'La rentabilidad debe estar entre 0% y 100%.',
                'pvp.required' => 'El precio final de venta es obligatorio.',
            ]
        );

        $validador->after(function ($validador) use ($entrada): void {
            if (
                ($entrada['idgrupo'] ?? null) !== 'nuevo'
                && (
                    ($entrada['idlinea'] ?? null) === 'nuevo'
                    || !DB::table('grupos')
                        ->where('idgrupo', (int) ($entrada['idgrupo'] ?? 0))
                        ->where('idlinea', (int) ($entrada['idlinea'] ?? 0))
                        ->where('estado', 1)
                        ->exists()
                )
            ) {
                $validador->errors()->add(
                    'idgrupo',
                    'Elegí un grupo que pertenezca a la línea seleccionada.'
                );
            }

            if (
                ($entrada['idsubgrupo'] ?? null) !== 'nuevo'
                && (
                    ($entrada['idgrupo'] ?? null) === 'nuevo'
                    || !DB::table('subgrupos')
                        ->where('idsubgrupo', (int) ($entrada['idsubgrupo'] ?? 0))
                        ->where('idgrupo', (int) ($entrada['idgrupo'] ?? 0))
                        ->where('estado', 1)
                        ->exists()
                )
            ) {
                $validador->errors()->add(
                    'idsubgrupo',
                    'Elegí un subgrupo que pertenezca al grupo seleccionado.'
                );
            }
        });

        try {
            return $validador->validate();
        } catch (ValidationException $exception) {
            $errores = [];
            foreach ($exception->errors() as $campo => $mensajes) {
                $errores["productos.{$indice}.preparacion.{$campo}"] = $mensajes;
            }

            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Crea o reactiva marca, línea, grupo o subgrupo evitando duplicados por nombre.
     */
    private function resolverSeleccionMaestraCompra(
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
        $normalizado = $this->normalizarNombreMaestroCompra($nombreNuevo);
        $consulta = DB::table($tabla);

        if ($campoPadre !== null) {
            $consulta->where($campoPadre, $idPadre);
        }

        $registros = $consulta
            ->lockForUpdate()
            ->get([$campoId, $campoNombre, 'estado']);

        foreach ($registros as $registro) {
            if (
                $this->normalizarNombreMaestroCompra(
                    (string) data_get($registro, $campoNombre)
                ) !== $normalizado
            ) {
                continue;
            }

            $idEncontrado = (int) data_get($registro, $campoId);

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

        $nuevo = [
            $campoNombre => $nombreNuevo,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($campoPadre !== null) {
            $nuevo[$campoPadre] = $idPadre;
        }

        return (int) DB::table($tabla)->insertGetId($nuevo);
    }

    private function resolverUnidadMedidaCompra(
        string $seleccion,
        ?string $nombreNuevo,
        ?string $abreviaturaNueva
    ): int {
        if ($seleccion !== 'nuevo') {
            return (int) $seleccion;
        }

        $nombreNuevo = trim((string) $nombreNuevo);
        $abreviaturaNueva = trim((string) $abreviaturaNueva);
        $nombreNormalizado = $this->normalizarNombreMaestroCompra($nombreNuevo);
        $abreviaturaNormalizada = Str::lower(Str::ascii($abreviaturaNueva));

        $unidades = DB::table('unidades_medida')
            ->lockForUpdate()
            ->get(['idunidad', 'nombre', 'abreviatura', 'estado']);

        foreach ($unidades as $unidad) {
            $mismoNombre = $this->normalizarNombreMaestroCompra(
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
                    'unidad' =>
                        "La unidad {$unidad->nombre} ya existe con la abreviatura {$unidad->abreviatura}.",
                ]);
            }

            if (!$mismoNombre && $mismaAbreviatura) {
                throw ValidationException::withMessages([
                    'unidad' =>
                        "La abreviatura {$abreviaturaNueva} ya pertenece a la unidad {$unidad->nombre}.",
                ]);
            }

            $idUnidad = (int) $unidad->idunidad;

            if (!(bool) $unidad->estado) {
                DB::table('unidades_medida')
                    ->where('idunidad', $idUnidad)
                    ->update(['estado' => 1]);
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
     * Vincula el mismo código de barras en cualquier proveedor y también el
     * historial del mismo código interno del proveedor seleccionado.
     */
    private function vincularDetallesEquivalentesCompra(
        DetalleCatalogo $detalle,
        Producto $producto
    ): void {
        $idProveedor = DB::table('catalogo_proveedor')
            ->where('idcatalogoproveedor', $detalle->idcatalogoproveedor)
            ->value('idproveedor');

        if ($idProveedor === null) {
            throw ValidationException::withMessages([
                'producto' => 'No se pudo identificar el proveedor del catálogo.',
            ]);
        }

        $codigoProveedor = trim((string) ($detalle->codigoproveedor ?? ''));
        $codigoBarra = trim((string) ($detalle->codigobarra ?? ''));

        $consulta = DetalleCatalogo::query()
            ->where(function (Builder $query) use (
                $codigoBarra,
                $codigoProveedor,
                $idProveedor,
                $detalle
            ): void {
                $agrego = false;

                if ($codigoBarra !== '') {
                    $query->where('codigobarra', $codigoBarra);
                    $agrego = true;
                }

                if ($codigoProveedor !== '') {
                    $metodo = $agrego ? 'orWhere' : 'where';
                    $query->{$metodo}(function (Builder $subconsulta) use (
                        $codigoProveedor,
                        $idProveedor
                    ): void {
                        $subconsulta
                            ->where('codigoproveedor', $codigoProveedor)
                            ->whereHas('catalogoProveedor', function (Builder $catalogo) use ($idProveedor): void {
                                $catalogo->where('idproveedor', (int) $idProveedor);
                            });
                    });
                    $agrego = true;
                }

                if (!$agrego) {
                    $query->whereKey($detalle->iddetallecatalogo);
                }
            })
            ->lockForUpdate()
            ->get(['iddetallecatalogo', 'idproducto']);

        $conflicto = $consulta->first(function ($fila) use ($producto) {
            return $fila->idproducto !== null
                && (int) $fila->idproducto !== (int) $producto->idproducto;
        });

        if ($conflicto !== null) {
            throw ValidationException::withMessages([
                'producto' =>
                    'Encontramos una coincidencia de catálogo ya vinculada con otro producto MES. Revisá ese artículo antes de confirmar la compra.',
            ]);
        }

        DetalleCatalogo::query()
            ->whereIn('iddetallecatalogo', $consulta->pluck('iddetallecatalogo'))
            ->update([
                'idproducto' => $producto->idproducto,
                'idmarca' => $producto->idmarca,
                'idsubgrupo' => $producto->idsubgrupo,
                'idiva' => $producto->idiva,
            ]);
    }

    /**
     * Crea el registro de stock en cero para el destino del pedido.
     * Si el producto ya tenía stock allí, no altera su cantidad actual.
     */
    private function crearStockDestino(int $idProducto, int $idSucursal): void
    {
        $stock = Stock::firstOrCreate(
            [
                'idproducto' => $idProducto,
                'idsucursal' => $idSucursal,
            ],
            [
                'stockactual' => 0,
                'stockminimo' => 0,
                'stockmaximo' => 0,
                'estado' => 1,
            ]
        );

        if (!$stock->estado) {
            $stock->estado = 1;
            $stock->save();
        }
    }

    private function normalizarNombreMaestroCompra(?string $valor): string
    {
        $valor = Str::lower(Str::ascii(trim((string) $valor)));
        $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor) ?? '';

        return trim(preg_replace('/\\s+/', ' ', $valor) ?? '');
    }

    private function normalizarContenidoNetoCompra(mixed $valor): mixed
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $texto = str_replace(',', '.', $texto);

        if (preg_match('/^(\\d+)\\s*\\/\\s*(\\d+)$/', $texto, $coincidencias)) {
            $numerador = (int) $coincidencias[1];
            $denominador = (int) $coincidencias[2];

            if ($denominador === 0) {
                return $texto;
            }

            return round($numerador / $denominador, 3);
        }

        return $texto;
    }

    private function generarCodigoTemporalCompra(): string
    {
        do {
            $codigo = 'TMP-' . Str::upper(Str::random(16));
        } while (Producto::where('codigointerno', $codigo)->exists());

        return $codigo;
    }

    private function generarCodigoInternoCompra(int $idProducto): string
    {
        return 'MES-'
            . str_pad((string) $idProducto, 6, '0', STR_PAD_LEFT);
    }

    private function codigoCompra(int $idCompra): string
    {
        return 'COM-'
            . str_pad((string) $idCompra, 6, '0', STR_PAD_LEFT);
    }

    private function aplicarAlcanceSucursal(
        Builder $consulta,
        bool $esDueno,
        mixed $idSucursalUsuario
    ): void {
        if ($esDueno) {
            return;
        }

        $idSucursalUsuario = (int) ($idSucursalUsuario ?? 0);

        if ($idSucursalUsuario > 0) {
            $consulta->where('idsucursal', $idSucursalUsuario);
            return;
        }

        $consulta->whereRaw('1 = 0');
    }

    private function normalizarFecha(string $fecha): string
    {
        if ($fecha === '') {
            return '';
        }

        $resultado = \DateTime::createFromFormat('Y-m-d', $fecha);

        if (!$resultado || $resultado->format('Y-m-d') !== $fecha) {
            return '';
        }

        return $fecha;
    }
}
