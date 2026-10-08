<?php

namespace App\Http\Controllers;

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
            ->join(
                'ventas as v',
                'v.idventa',
                '=',
                'm.idventa'
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
            ->whereNotIn('v.estado', ['ANULADA', 'CANCELADA'])
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

        return view('stock.index', compact(
            'stocks',
            'sucursales',
            'resumen',
            'busqueda',
            'idSucursal',
            'disponibilidad',
            'notificacionesVenta'
        ));
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
