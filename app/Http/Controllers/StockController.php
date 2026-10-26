<?php

namespace App\Http\Controllers;

use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Muestra las existencias de los productos por sucursal.
     */
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:255'],
            'idsucursal' => [
                'nullable',
                'integer',
                Rule::exists('sucursales', 'idsucursal'),
            ],
            'disponibilidad' => [
                'nullable',
                Rule::in(['todos', 'con_stock', 'sin_stock', 'bajo_minimo']),
            ],
        ]);

        $busqueda = trim((string) ($filtros['buscar'] ?? ''));
        $idSucursal = $filtros['idsucursal'] ?? null;
        $disponibilidad = (string) ($filtros['disponibilidad'] ?? 'todos');

        $consultaBase = Stock::query()
            ->select('stock.*')
            ->join('productos', 'productos.idproducto', '=', 'stock.idproducto')
            ->join('sucursales', 'sucursales.idsucursal', '=', 'stock.idsucursal')
            ->where('stock.estado', 1)
            ->where('productos.estado', 1)
            ->where('sucursales.estado', 1);

        $resumen = [
            'registros' => (clone $consultaBase)->count(),
            'unidades' => (int) (clone $consultaBase)->sum('stock.stockactual'),
            'sinStock' => (clone $consultaBase)
                ->where('stock.stockactual', 0)
                ->count(),
            'bajoMinimo' => (clone $consultaBase)
                ->where('stock.stockminimo', '>', 0)
                ->whereColumn('stock.stockactual', '<=', 'stock.stockminimo')
                ->count(),
        ];

        if ($busqueda !== '') {
            $consultaBase->where(function ($consulta) use ($busqueda) {
                $consulta
                    ->where('productos.codigointerno', 'like', "%{$busqueda}%")
                    ->orWhere('productos.codigofabricante', 'like', "%{$busqueda}%")
                    ->orWhere('productos.codigobarra', 'like', "%{$busqueda}%")
                    ->orWhere('productos.descripcion', 'like', "%{$busqueda}%");
            });
        }

        if ($idSucursal) {
            $consultaBase->where('stock.idsucursal', (int) $idSucursal);
        }

        match ($disponibilidad) {
            'con_stock' => $consultaBase->where('stock.stockactual', '>', 0),
            'sin_stock' => $consultaBase->where('stock.stockactual', 0),
            'bajo_minimo' => $consultaBase
                ->where('stock.stockminimo', '>', 0)
                ->whereColumn('stock.stockactual', '<=', 'stock.stockminimo'),
            default => null,
        };

        $stocks = $consultaBase
            ->with(['producto.marca', 'producto.subgrupo', 'sucursal'])
            ->orderBy('productos.descripcion')
            ->orderBy('sucursales.nombre')
            ->paginate(20)
            ->withQueryString();

        $sucursales = Sucursal::query()
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get(['idsucursal', 'nombre', 'tipo']);

        // Avisos generados cuando una venta de otra sucursal utilizó stock
        // de esta ubicación. El descuento ya se realizó dentro de la venta;
        // el aviso sirve para que el local prepare o coordine la mercadería.
        $usuario = auth('web')->user();

        $consultaNotificaciones = DB::table('movimientos as m')
            ->join(
                'tipos_movimiento as tm',
                'tm.idtipomovimiento',
                '=',
                'm.idtipomovimiento'
            )
            ->leftJoin(
                'sucursales as origen',
                'origen.idsucursal',
                '=',
                'm.idsucursalorigen'
            )
            ->leftJoin(
                'sucursales as destino',
                'destino.idsucursal',
                '=',
                'm.idsucursaldestino'
            )
            ->where('tm.codigo', 'VENTA_STOCK')
            ->where('m.estado', 1)
            ->whereNotNull('m.idsucursaldestino')
            ->whereColumn('m.idsucursalorigen', '<>', 'm.idsucursaldestino')
            ->when(
                $usuario?->idsucursal,
                fn ($consulta, $idUsuarioSucursal) => $consulta->where(
                    'm.idsucursalorigen',
                    (int) $idUsuarioSucursal
                )
            )
            ->orderByDesc('m.idmovimiento')
            ->limit(8)
            ->get([
                'm.idmovimiento',
                'm.idventa',
                'm.fecha',
                'm.observacion',
                'origen.nombre as sucursal_origen',
                'destino.nombre as sucursal_venta',
            ]);

        $detallesNotificaciones = collect();

        if ($consultaNotificaciones->isNotEmpty()) {
            $detallesNotificaciones = DB::table('detalle_movimiento as dm')
                ->join(
                    'productos as p',
                    'p.idproducto',
                    '=',
                    'dm.idproducto'
                )
                ->whereIn(
                    'dm.idmovimiento',
                    $consultaNotificaciones->pluck('idmovimiento')
                )
                ->orderBy('p.descripcion')
                ->get([
                    'dm.idmovimiento',
                    'dm.cantidad',
                    'p.codigointerno',
                    'p.descripcion',
                ])
                ->groupBy(fn ($detalle) => (int) $detalle->idmovimiento);
        }

        $notificacionesVenta = $consultaNotificaciones
            ->map(function ($notificacion) use ($detallesNotificaciones) {
                $notificacion->productos = $detallesNotificaciones
                    ->get((int) $notificacion->idmovimiento, collect())
                    ->map(fn ($detalle) => sprintf(
                        '%s x%d',
                        $detalle->codigointerno ?: $detalle->descripcion,
                        (int) $detalle->cantidad
                    ))
                    ->implode(' · ');

                return $notificacion;
            });

        /*
         * Paso 6.2: unidades buenas, unidades con problema y pendientes de entrega.
         */
        $esDueno = $usuario?->rol?->nombre === 'DUEÑO';

        $consultaRecepcionBase = DB::table('detalle_compra as dc')
            ->join('compras as c', 'c.idcompra', '=', 'dc.idcompra')
            ->join('proveedores as pr', 'pr.idproveedor', '=', 'c.idproveedor')
            ->join('productos as p', 'p.idproducto', '=', 'dc.idproducto')
            ->join('sucursales as s', 's.idsucursal', '=', 'c.idsucursal')
            ->leftJoin('stock as st', function ($join) {
                $join
                    ->on('st.idproducto', '=', 'dc.idproducto')
                    ->on('st.idsucursal', '=', 'c.idsucursal')
                    ->where('st.estado', 1);
            })
            ->whereIn('c.estado', ['PENDIENTE', 'PARCIAL'])
            ->where('p.estado', 1)
            ->where('pr.estado', 1)
            ->where('s.estado', 1)
            ->when(
                !$esDueno && $usuario?->idsucursal,
                fn ($consulta, $idUsuarioSucursal) => $consulta->where(
                    'c.idsucursal',
                    (int) $idUsuarioSucursal
                )
            );

        // Los filtros superiores también deben afectar Recepciones pendientes
        // y Productos con problemas para que toda la pantalla responda igual.
        if ($busqueda !== '') {
            $consultaRecepcionBase->where(function ($consulta) use ($busqueda) {
                $consulta
                    ->where('p.codigointerno', 'like', "%{$busqueda}%")
                    ->orWhere('p.codigofabricante', 'like', "%{$busqueda}%")
                    ->orWhere('p.codigobarra', 'like', "%{$busqueda}%")
                    ->orWhere('p.descripcion', 'like', "%{$busqueda}%");
            });
        }

        if ($idSucursal) {
            $consultaRecepcionBase->where('c.idsucursal', (int) $idSucursal);
        }

        match ($disponibilidad) {
            'con_stock' => $consultaRecepcionBase
                ->whereRaw('COALESCE(st.stockactual, 0) > 0'),
            'sin_stock' => $consultaRecepcionBase
                ->whereRaw('COALESCE(st.stockactual, 0) = 0'),
            'bajo_minimo' => $consultaRecepcionBase
                ->whereRaw('COALESCE(st.stockminimo, 0) > 0')
                ->whereRaw('COALESCE(st.stockactual, 0) <= COALESCE(st.stockminimo, 0)'),
            default => null,
        };

        $recepcionesPendientes = (clone $consultaRecepcionBase)
            ->whereRaw('(COALESCE(dc.cantidadrecibida, 0) + COALESCE(dc.cantidadproblema, 0)) < dc.cantidad')
            ->orderBy('c.fecha')
            ->orderBy('c.idcompra')
            ->orderBy('p.descripcion')
            ->limit(30)
            ->get([
                'dc.iddetallecompra', 'dc.idcompra', 'dc.idproducto',
                'dc.cantidad', 'dc.cantidadrecibida', 'dc.cantidadproblema',
                'dc.estadoincidencia', 'c.estado as estado_compra', 'c.fecha',
                'pr.razonsocial as proveedor', 'p.codigointerno', 'p.codigobarra',
                'p.descripcion', 's.idsucursal', 's.nombre as sucursal',
                's.tipo as tipo_sucursal',
                DB::raw('COALESCE(st.stockactual, 0) as stockactual'),
                DB::raw('COALESCE(st.stockminimo, 0) as stockminimo'),
                'st.stockmaximo',
                DB::raw('(dc.cantidad - COALESCE(dc.cantidadrecibida, 0) - COALESCE(dc.cantidadproblema, 0)) as cantidadpendienteentrega'),
            ]);

        $problemasPendientes = (clone $consultaRecepcionBase)
            ->where('dc.cantidadproblema', '>', 0)
            ->where('dc.estadoincidencia', 'PENDIENTE')
            ->orderBy('c.fecha')
            ->orderBy('c.idcompra')
            ->orderBy('p.descripcion')
            ->limit(30)
            ->get([
                'dc.iddetallecompra', 'dc.idcompra', 'dc.idproducto',
                'dc.cantidad', 'dc.cantidadrecibida', 'dc.cantidadproblema',
                'dc.observacionrecepcion', 'dc.estadoincidencia',
                'c.estado as estado_compra', 'c.fecha',
                'pr.razonsocial as proveedor', 'p.codigointerno', 'p.codigobarra',
                'p.descripcion', 's.idsucursal', 's.nombre as sucursal',
                's.tipo as tipo_sucursal',
                DB::raw('COALESCE(st.stockactual, 0) as stockactual'),
            ]);

        return view('stock.index', compact(
            'stocks',
            'sucursales',
            'resumen',
            'busqueda',
            'idSucursal',
            'disponibilidad',
            'notificacionesVenta',
            'recepcionesPendientes',
            'problemasPendientes'
        ));
    }

    public function recibirCompra(Request $request, DetalleCompra $detalleCompra): RedirectResponse
    {
        $datos = $request->validate([
            'cantidad_llegada' => ['required', 'integer', 'min:1', 'max:999999'],
            'cantidad_buena' => ['required', 'integer', 'min:0', 'max:999999'],
            'cantidad_problema' => ['required', 'integer', 'min:0', 'max:999999'],
            'observacion_recepcion' => ['nullable', 'string', 'max:1500'],
            'stockminimo_recepcion' => ['required', 'integer', 'min:0', 'max:999999'],
            'stockmaximo_recepcion' => ['nullable', 'integer', 'min:0', 'max:999999', 'gte:stockminimo_recepcion'],
        ]);

        $llegada = (int) $datos['cantidad_llegada'];
        $buenas = (int) $datos['cantidad_buena'];
        $problemas = (int) $datos['cantidad_problema'];
        $observacion = trim((string) ($datos['observacion_recepcion'] ?? ''));

        if (($buenas + $problemas) !== $llegada) {
            throw ValidationException::withMessages(['cantidad_llegada' => 'Las unidades buenas y las unidades con problemas deben sumar la cantidad que llegó.']);
        }
        if ($problemas > 0 && $observacion === '') {
            throw ValidationException::withMessages(['observacion_recepcion' => 'Escribí una observación para las unidades con problemas.']);
        }

        DB::transaction(function () use ($detalleCompra, $datos, $llegada, $buenas, $problemas, $observacion) {
            $detalle = DetalleCompra::query()->whereKey($detalleCompra->getKey())->lockForUpdate()->firstOrFail();
            $compra = DB::table('compras')->where('idcompra', $detalle->idcompra)->lockForUpdate()->first();

            if (!$compra || !in_array($compra->estado, ['PENDIENTE', 'PARCIAL'], true)) {
                throw ValidationException::withMessages(['compra' => 'La compra ya no está disponible para recibir mercadería.']);
            }
            $this->comprobarSucursalCompra((int) $compra->idsucursal);

            $pendienteEntrega = max(0, (int) $detalle->cantidad - (int) $detalle->cantidadrecibida - (int) $detalle->cantidadproblema);
            if ($llegada > $pendienteEntrega) {
                throw ValidationException::withMessages(['cantidad_llegada' => "Sólo quedan {$pendienteEntrega} unidades pendientes de entrega."]);
            }

            $nuevoProblema = (int) $detalle->cantidadproblema + $problemas;
            $detalle->update([
                'cantidadrecibida' => (int) $detalle->cantidadrecibida + $buenas,
                'cantidadproblema' => $nuevoProblema,
                'estadoincidencia' => $nuevoProblema > 0 ? 'PENDIENTE' : ($detalle->estadoincidencia === 'RESUELTA' ? 'RESUELTA' : 'SIN_PROBLEMA'),
                'observacionrecepcion' => $this->agregarObservacionRecepcion(
                    $detalle->observacionrecepcion,
                    $problemas > 0
                        ? sprintf('Recepción: %d correcta(s), %d con problema. %s', $buenas, $problemas, $observacion)
                        : ($observacion !== '' ? sprintf('Recepción: %d correcta(s). %s', $buenas, $observacion) : null)
                ),
            ]);

            if ($buenas > 0) {
                $this->sumarStockCompra(
                    $detalle, (int) $compra->idsucursal, $buenas,
                    (int) $datos['stockminimo_recepcion'],
                    filled($datos['stockmaximo_recepcion'] ?? null) ? (int) $datos['stockmaximo_recepcion'] : null,
                    (int) $compra->idcompra,
                    sprintf('Recepción de compra. %d unidad(es) aceptada(s).', $buenas)
                );
            } else {
                // Si toda la entrega llegó observada no hay movimiento de stock,
                // pero conservamos los límites definidos para esa ubicación.
                $stock = Stock::query()
                    ->where('idproducto', $detalle->idproducto)
                    ->where('idsucursal', (int) $compra->idsucursal)
                    ->lockForUpdate()
                    ->first();

                $valores = [
                    'stockminimo' => (int) $datos['stockminimo_recepcion'],
                    'stockmaximo' => filled($datos['stockmaximo_recepcion'] ?? null)
                        ? (int) $datos['stockmaximo_recepcion']
                        : null,
                    'estado' => 1,
                ];

                if ($stock) {
                    $stock->update($valores);
                } else {
                    Stock::create([
                        'idproducto' => $detalle->idproducto,
                        'idsucursal' => (int) $compra->idsucursal,
                        'stockactual' => 0,
                        ...$valores,
                    ]);
                }
            }

            $this->actualizarEstadoCompra((int) $compra->idcompra);
        });

        return redirect()->route('stock.index')->with('success',
            $problemas > 0
                ? 'La recepción se registró. Las unidades buenas ingresaron al stock y las observadas quedaron pendientes de reemplazo.'
                : 'La recepción se registró y las unidades buenas ingresaron al stock.'
        );
    }

    public function registrarReemplazo(Request $request, DetalleCompra $detalleCompra): RedirectResponse
    {
        $datos = $request->validate([
            'cantidad_reemplazo' => ['required', 'integer', 'min:1', 'max:999999'],
            'observacion_reemplazo' => ['nullable', 'string', 'max:1500'],
        ]);
        $cantidad = (int) $datos['cantidad_reemplazo'];
        $observacion = trim((string) ($datos['observacion_reemplazo'] ?? ''));

        DB::transaction(function () use ($detalleCompra, $cantidad, $observacion) {
            $detalle = DetalleCompra::query()->whereKey($detalleCompra->getKey())->lockForUpdate()->firstOrFail();
            $compra = DB::table('compras')->where('idcompra', $detalle->idcompra)->lockForUpdate()->first();
            if (!$compra || $compra->estado === 'ANULADA') {
                throw ValidationException::withMessages(['compra' => 'La compra no está disponible para registrar reemplazos.']);
            }
            $this->comprobarSucursalCompra((int) $compra->idsucursal);

            $pendientes = (int) $detalle->cantidadproblema;
            if ($pendientes <= 0 || $detalle->estadoincidencia !== 'PENDIENTE') {
                throw ValidationException::withMessages(['cantidad_reemplazo' => 'Este producto ya no tiene reemplazos pendientes.']);
            }
            if ($cantidad > $pendientes) {
                throw ValidationException::withMessages(['cantidad_reemplazo' => "Sólo hay {$pendientes} unidades pendientes de reemplazo."]);
            }

            $nuevoProblema = $pendientes - $cantidad;
            $nuevoRecibido = (int) $detalle->cantidadrecibida + $cantidad;
            if ($nuevoRecibido > (int) $detalle->cantidad) {
                throw ValidationException::withMessages(['cantidad_reemplazo' => 'El reemplazo superaría la cantidad pedida.']);
            }

            $detalle->update([
                'cantidadrecibida' => $nuevoRecibido,
                'cantidadproblema' => $nuevoProblema,
                'estadoincidencia' => $nuevoProblema === 0 ? 'RESUELTA' : 'PENDIENTE',
                'observacionrecepcion' => $this->agregarObservacionRecepcion(
                    $detalle->observacionrecepcion,
                    sprintf('Reemplazo recibido: %d unidad(es)%s.', $cantidad, $observacion !== '' ? ' — ' . rtrim($observacion, '.') : '')
                ),
            ]);

            $stock = Stock::query()->where('idproducto', $detalle->idproducto)->where('idsucursal', (int) $compra->idsucursal)->first();
            $this->sumarStockCompra(
                $detalle, (int) $compra->idsucursal, $cantidad,
                (int) ($stock?->stockminimo ?? 0),
                $stock?->stockmaximo === null ? null : (int) $stock->stockmaximo,
                (int) $compra->idcompra,
                sprintf('Reemplazo de incidencia de compra. %d unidad(es) aceptada(s).', $cantidad)
            );
            $this->actualizarEstadoCompra((int) $compra->idcompra);
        });

        return redirect()->route('stock.index')->with('success', 'El reemplazo se registró y las unidades aceptadas se sumaron al stock.');
    }

    private function sumarStockCompra(DetalleCompra $detalle, int $idSucursal, int $cantidad, int $stockMinimo, ?int $stockMaximo, int $idCompra, string $observacion): void
    {
        $stock = Stock::query()->where('idproducto', $detalle->idproducto)->where('idsucursal', $idSucursal)->lockForUpdate()->first();
        $anterior = (int) ($stock?->stockactual ?? 0);
        $posterior = $anterior + $cantidad;
        if ($posterior > 999999999) {
            throw ValidationException::withMessages(['stock' => 'La recepción supera el límite permitido de stock.']);
        }

        if ($stock) {
            $stock->update(['stockactual' => $posterior, 'stockminimo' => $stockMinimo, 'stockmaximo' => $stockMaximo, 'estado' => 1]);
        } else {
            Stock::create(['idproducto' => $detalle->idproducto, 'idsucursal' => $idSucursal, 'stockactual' => $posterior, 'stockminimo' => $stockMinimo, 'stockmaximo' => $stockMaximo, 'estado' => 1]);
        }

        $idMovimiento = DB::table('movimientos')->insertGetId([
            'idtipomovimiento' => $this->obtenerTipoMovimiento('RECEPCION_COMPRA'),
            'idusuario' => auth('web')->id(), 'idsucursalorigen' => null,
            'idsucursaldestino' => $idSucursal, 'idcompra' => $idCompra,
            'fecha' => now(), 'referencia' => null,
            'observacion' => sprintf('%s Stock anterior: %d. Stock posterior: %d.', $observacion, $anterior, $posterior),
            'estado' => 1, 'created_at' => now(),
        ], 'idmovimiento');
        DB::table('movimientos')->where('idmovimiento', $idMovimiento)->update(['referencia' => sprintf('REC-%06d', $idMovimiento)]);

        $costo = $detalle->costounitario === null ? null : round((float) $detalle->costounitario, 2);
        DB::table('detalle_movimiento')->insert([
            'idmovimiento' => $idMovimiento, 'idproducto' => $detalle->idproducto,
            'cantidad' => $cantidad, 'costounitario' => $costo,
            'subtotal' => $costo === null ? null : round($costo * $cantidad, 2),
        ]);
    }

    private function actualizarEstadoCompra(int $idCompra): void
    {
        $detalles = DB::table('detalle_compra')->where('idcompra', $idCompra)->lockForUpdate()->get(['cantidad', 'cantidadrecibida', 'cantidadproblema']);
        $completa = $detalles->isNotEmpty() && $detalles->every(fn ($d) => (int) $d->cantidadrecibida >= (int) $d->cantidad && (int) $d->cantidadproblema === 0);
        $huboRecepcion = $detalles->contains(fn ($d) => (int) $d->cantidadrecibida > 0 || (int) $d->cantidadproblema > 0);
        DB::table('compras')->where('idcompra', $idCompra)->update([
            'estado' => $completa ? 'RECIBIDA' : ($huboRecepcion ? 'PARCIAL' : 'PENDIENTE'),
            'updated_at' => now(),
        ]);
    }

    private function comprobarSucursalCompra(int $idSucursal): void
    {
        $usuario = auth('web')->user();
        abort_unless($usuario, 403);
        if ($usuario?->rol?->nombre === 'DUEÑO') return;
        abort_unless((int) ($usuario->idsucursal ?? 0) === $idSucursal, 403);
    }

    private function agregarObservacionRecepcion(?string $existente, ?string $nueva): ?string
    {
        $nueva = trim((string) $nueva);
        if ($nueva === '') return $existente;
        $linea = sprintf('[%s] %s', now()->format('d/m/Y H:i'), $nueva);
        $existente = trim((string) $existente);
        return $existente === '' ? $linea : $existente . PHP_EOL . $linea;
    }

    /**
     * Devuelve la existencia de un producto en una sucursal.
     *
     * Si todavía no existe el registro, informa stock cero para que la
     * pantalla pueda preparar un ingreso o una transferencia.
     */
    public function consultarUbicacion(
        Producto $producto,
        Sucursal $sucursal
    ): JsonResponse {
        abort_unless($producto->estado && $sucursal->estado, 404);

        $stock = Stock::query()
            ->where('idproducto', $producto->idproducto)
            ->where('idsucursal', $sucursal->idsucursal)
            ->first();

        return response()->json([
            'existe' => $stock !== null,
            'stockactual' => (int) ($stock?->stockactual ?? 0),
            'stockminimo' => (int) ($stock?->stockminimo ?? 0),
            'stockmaximo' => $stock?->stockmaximo === null
                ? null
                : (int) $stock->stockmaximo,
        ]);
    }

    /**
     * Suma mercadería recibida en la sucursal elegida y define sus límites.
     */
    public function registrarIngreso(
        Request $request,
        Stock $stock
    ): RedirectResponse {
        $datos = $request->validate(
            [
                'operacion_stock' => ['required', Rule::in(['ingreso'])],
                'idsucursal_destino' => [
                    'required',
                    'integer',
                    Rule::exists('sucursales', 'idsucursal')
                        ->where(fn ($consulta) => $consulta->where('estado', 1)),
                ],
                'cantidad_ingreso' => ['required', 'integer', 'min:1', 'max:999999'],
                'stockminimo' => ['required', 'integer', 'min:0', 'max:999999'],
                'stockmaximo' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:999999',
                    'gte:stockminimo',
                ],
                'buscar' => ['nullable', 'string', 'max:255'],
                'idsucursal_filtro' => ['nullable', 'integer'],
                'disponibilidad' => [
                    'nullable',
                    Rule::in(['todos', 'con_stock', 'sin_stock', 'bajo_minimo']),
                ],
            ],
            [
                'operacion_stock.required' =>
                    'No se pudo identificar la operación de stock. Recargá la página e intentá nuevamente.',
                'operacion_stock.in' =>
                    'La operación recibida no corresponde a un ingreso de mercadería.',
                'idsucursal_destino.required' => 'Seleccioná la sucursal donde quedará la mercadería.',
                'idsucursal_destino.integer' => 'La sucursal seleccionada no es válida.',
                'idsucursal_destino.exists' => 'La sucursal seleccionada no existe o está inactiva.',
                'cantidad_ingreso.required' => 'Ingresá la cantidad recibida.',
                'cantidad_ingreso.integer' => 'La cantidad recibida debe ser un número entero.',
                'cantidad_ingreso.min' => 'La cantidad recibida debe ser mayor que cero.',
                'cantidad_ingreso.max' => 'La cantidad recibida es demasiado grande.',
                'stockminimo.required' => 'Ingresá el stock mínimo.',
                'stockminimo.integer' => 'El stock mínimo debe ser un número entero.',
                'stockminimo.min' => 'El stock mínimo no puede ser negativo.',
                'stockmaximo.integer' => 'El stock máximo debe ser un número entero.',
                'stockmaximo.min' => 'El stock máximo no puede ser negativo.',
                'stockmaximo.gte' => 'El stock máximo debe ser igual o mayor que el mínimo.',
            ]
        );

        DB::transaction(function () use ($stock, $datos) {
            $stockReferencia = Stock::query()
                ->whereKey($stock->getKey())
                ->where('estado', 1)
                ->lockForUpdate()
                ->firstOrFail();

            // Un ingreso siempre se registra en la ubicación de la fila
            // seleccionada. Para mover unidades a otra sucursal se debe usar
            // exclusivamente la operación de transferencia.
            if ((int) $datos['idsucursal_destino'] !== (int) $stockReferencia->idsucursal) {
                throw ValidationException::withMessages([
                    'idsucursal_destino' =>
                        'Registrar ingreso solamente suma mercadería en la sucursal seleccionada. Para enviarla a otra sucursal usá el botón Transferir.',
                ]);
            }

            $stockDestino = Stock::query()
                ->where('idproducto', $stockReferencia->idproducto)
                ->where('idsucursal', (int) $datos['idsucursal_destino'])
                ->lockForUpdate()
                ->first();

            $stockAnterior = (int) ($stockDestino?->stockactual ?? 0);
            $nuevoStock = $stockAnterior
                + (int) $datos['cantidad_ingreso'];

            if ($nuevoStock > 999999999) {
                throw ValidationException::withMessages([
                    'cantidad_ingreso' =>
                        'La cantidad supera el límite permitido para este producto.',
                ]);
            }

            $valoresStock = [
                'stockactual' => $nuevoStock,
                'stockminimo' => (int) $datos['stockminimo'],
                'stockmaximo' => filled($datos['stockmaximo'] ?? null)
                    ? (int) $datos['stockmaximo']
                    : null,
                'estado' => 1,
            ];

            if ($stockDestino) {
                $stockDestino->update($valoresStock);
            } else {
                $stockDestino = Stock::create([
                    'idproducto' => $stockReferencia->idproducto,
                    'idsucursal' => (int) $datos['idsucursal_destino'],
                    ...$valoresStock,
                ]);
            }

            $idMovimiento = DB::table('movimientos')->insertGetId([
                'idtipomovimiento' => $this->obtenerTipoMovimiento('INGRESO_STOCK'),
                'idusuario' => auth('web')->id(),
                'idsucursalorigen' => null,
                'idsucursaldestino' => (int) $datos['idsucursal_destino'],
                'fecha' => now(),
                'referencia' => null,
                'observacion' => sprintf(
                    'Ingreso de mercadería. Stock anterior: %d. Stock posterior: %d.',
                    $stockAnterior,
                    $nuevoStock
                ),
                'estado' => 1,
                'created_at' => now(),
            ], 'idmovimiento');

            DB::table('movimientos')
                ->where('idmovimiento', $idMovimiento)
                ->update([
                    'referencia' => sprintf('ING-%06d', $idMovimiento),
                ]);

            DB::table('detalle_movimiento')->insert([
                'idmovimiento' => $idMovimiento,
                'idproducto' => $stockReferencia->idproducto,
                'cantidad' => (int) $datos['cantidad_ingreso'],
                'costounitario' => null,
                'subtotal' => null,
            ]);
        });

        return redirect()
            ->route('stock.index', array_filter([
                'buscar' => trim((string) ($datos['buscar'] ?? '')),
                'idsucursal' => $datos['idsucursal_filtro'] ?? null,
                'disponibilidad' => $datos['disponibilidad'] ?? 'todos',
            ], static fn ($valor) => $valor !== null && $valor !== ''))
            ->with('success', 'El ingreso de mercadería se registró correctamente.');
    }

    /**
     * Mueve unidades entre dos sucursales sin alterar el total general.
     */
    public function transferir(
        Request $request,
        Stock $stock
    ): RedirectResponse {
        $datos = $request->validate(
            [
                'operacion_stock' => ['required', Rule::in(['transferencia'])],
                'idsucursal_destino' => [
                    'required',
                    'integer',
                    Rule::exists('sucursales', 'idsucursal')
                        ->where(fn ($consulta) => $consulta->where('estado', 1)),
                ],
                'cantidad_transferencia' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:999999',
                ],
                'stockminimo_destino' => ['required', 'integer', 'min:0', 'max:999999'],
                'stockmaximo_destino' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:999999',
                    'gte:stockminimo_destino',
                ],
                'buscar' => ['nullable', 'string', 'max:255'],
                'idsucursal_filtro' => ['nullable', 'integer'],
                'disponibilidad' => [
                    'nullable',
                    Rule::in(['todos', 'con_stock', 'sin_stock', 'bajo_minimo']),
                ],
            ],
            [
                'operacion_stock.required' =>
                    'No se pudo identificar la operación de stock. Recargá la página e intentá nuevamente.',
                'operacion_stock.in' =>
                    'La operación recibida no corresponde a una transferencia.',
                'idsucursal_destino.required' => 'Seleccioná la sucursal de destino.',
                'idsucursal_destino.integer' => 'La sucursal de destino no es válida.',
                'idsucursal_destino.exists' => 'La sucursal de destino no existe o está inactiva.',
                'cantidad_transferencia.required' => 'Ingresá la cantidad que querés transferir.',
                'cantidad_transferencia.integer' => 'La cantidad debe ser un número entero.',
                'cantidad_transferencia.min' => 'La cantidad debe ser mayor que cero.',
                'cantidad_transferencia.max' => 'La cantidad ingresada es demasiado grande.',
                'stockminimo_destino.required' => 'Ingresá el stock mínimo del destino.',
                'stockminimo_destino.integer' => 'El stock mínimo debe ser un número entero.',
                'stockminimo_destino.min' => 'El stock mínimo no puede ser negativo.',
                'stockmaximo_destino.integer' => 'El stock máximo debe ser un número entero.',
                'stockmaximo_destino.min' => 'El stock máximo no puede ser negativo.',
                'stockmaximo_destino.gte' => 'El stock máximo debe ser igual o mayor que el mínimo.',
            ]
        );

        DB::transaction(function () use ($stock, $datos) {
            $stockOrigen = Stock::query()
                ->whereKey($stock->getKey())
                ->where('estado', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $idDestino = (int) $datos['idsucursal_destino'];
            $cantidad = (int) $datos['cantidad_transferencia'];

            if ((int) $stockOrigen->idsucursal === $idDestino) {
                throw ValidationException::withMessages([
                    'idsucursal_destino' =>
                        'La sucursal de destino debe ser diferente a la de origen.',
                ]);
            }

            if ($cantidad > (int) $stockOrigen->stockactual) {
                throw ValidationException::withMessages([
                    'cantidad_transferencia' =>
                        'No hay suficientes unidades en la sucursal de origen.',
                ]);
            }

            $stockDestino = Stock::query()
                ->where('idproducto', $stockOrigen->idproducto)
                ->where('idsucursal', $idDestino)
                ->lockForUpdate()
                ->first();

            $origenAntes = (int) $stockOrigen->stockactual;
            $destinoAntes = (int) ($stockDestino?->stockactual ?? 0);
            $origenDespues = $origenAntes - $cantidad;
            $destinoDespues = $destinoAntes + $cantidad;

            if ($destinoDespues > 999999999) {
                throw ValidationException::withMessages([
                    'cantidad_transferencia' =>
                        'La cantidad supera el límite permitido en el destino.',
                ]);
            }

            // El descuento se hace de forma atómica. Además de evitar carreras
            // entre dos usuarios, obliga a que se modifique exactamente la fila
            // de origen que acabamos de bloquear.
            $filasOrigen = DB::table('stock')
                ->where('idstock', $stockOrigen->idstock)
                ->where('stockactual', $origenAntes)
                ->decrement('stockactual', $cantidad, [
                    'updated_at' => now(),
                ]);

            if ($filasOrigen !== 1) {
                throw ValidationException::withMessages([
                    'cantidad_transferencia' =>
                        'El stock de origen cambió durante la operación. Recargá la página e intentá nuevamente.',
                ]);
            }

            $stockOrigen->refresh();

            if ((int) $stockOrigen->stockactual !== $origenDespues) {
                throw ValidationException::withMessages([
                    'cantidad_transferencia' =>
                        'No se pudo descontar correctamente el stock de origen.',
                ]);
            }

            $valoresDestino = [
                'stockactual' => $destinoDespues,
                'stockminimo' => (int) $datos['stockminimo_destino'],
                'stockmaximo' => filled($datos['stockmaximo_destino'] ?? null)
                    ? (int) $datos['stockmaximo_destino']
                    : null,
                'estado' => 1,
            ];

            if ($stockDestino) {
                $stockDestino->update($valoresDestino);
            } else {
                $stockDestino = Stock::create([
                    'idproducto' => $stockOrigen->idproducto,
                    'idsucursal' => $idDestino,
                    ...$valoresDestino,
                ]);
            }

            $stockDestino->refresh();

            $totalAntes = $origenAntes + $destinoAntes;
            $totalDespues = (int) $stockOrigen->stockactual
                + (int) $stockDestino->stockactual;

            if ($totalAntes !== $totalDespues) {
                throw ValidationException::withMessages([
                    'cantidad_transferencia' =>
                        'La transferencia no conservó el total de unidades y fue cancelada.',
                ]);
            }

            $idMovimiento = DB::table('movimientos')->insertGetId([
                'idtipomovimiento' => $this->obtenerTipoMovimiento('TRANSFERENCIA_STOCK'),
                'idusuario' => auth('web')->id(),
                'idsucursalorigen' => $stockOrigen->idsucursal,
                'idsucursaldestino' => $idDestino,
                'fecha' => now(),
                'referencia' => null,
                'observacion' => sprintf(
                    'Transferencia de stock. Origen: %d a %d. Destino: %d a %d.',
                    $origenAntes,
                    $origenDespues,
                    $destinoAntes,
                    $destinoDespues
                ),
                'estado' => 1,
                'created_at' => now(),
            ], 'idmovimiento');

            DB::table('movimientos')
                ->where('idmovimiento', $idMovimiento)
                ->update([
                    'referencia' => sprintf('TRF-%06d', $idMovimiento),
                ]);

            DB::table('detalle_movimiento')->insert([
                'idmovimiento' => $idMovimiento,
                'idproducto' => $stockOrigen->idproducto,
                'cantidad' => $cantidad,
                'costounitario' => null,
                'subtotal' => null,
            ]);
        });

        return redirect()
            ->route('stock.index', array_filter([
                'buscar' => trim((string) ($datos['buscar'] ?? '')),
                'idsucursal' => $datos['idsucursal_filtro'] ?? null,
                'disponibilidad' => $datos['disponibilidad'] ?? 'todos',
            ], static fn ($valor) => $valor !== null && $valor !== ''))
            ->with('success', 'La transferencia de stock se realizó correctamente.');
    }

    /**
     * Obtiene un tipo de movimiento activo previamente configurado.
     */
    private function obtenerTipoMovimiento(string $codigo): int
    {
        $idTipo = DB::table('tipos_movimiento')
            ->where('codigo', $codigo)
            ->where('estado', 1)
            ->value('idtipomovimiento');

        if (!$idTipo) {
            throw ValidationException::withMessages([
                'movimiento' =>
                    "Falta configurar el tipo de movimiento {$codigo}.",
            ]);
        }

        return (int) $idTipo;
    }
}
