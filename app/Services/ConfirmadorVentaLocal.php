<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\DetalleMovimiento;
use App\Models\IntentoPagoMp;
use App\Models\MetodoPago;
use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\TipoMovimiento;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de confirmación basado en VentaController::guardar.
 * NO conectar a rutas de pago hasta validar el flujo con pruebas funcionales.
 */
class ConfirmadorVentaLocal
{
    private const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

    public function confirmar(Request $request, ?IntentoPagoMp $intentoPago = null, ?User $usuarioOperacion = null): JsonResponse
    {
        $datos = $request->validate([
            'idsucursal' => [
                'required',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
            'tipo_cliente' => [
                'required',
                Rule::in(['consumidor_final', 'registrado']),
            ],
            'idcliente' => ['nullable', 'integer'],
            'descuento_adicional' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'idmetodopago' => [
                'required',
                'integer',
                Rule::exists('metodos_pago', 'idmetodopago')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
            'productos' => ['required', 'array', 'min:1', 'max:100'],
            'productos.*.idproducto' => ['required', 'integer', 'distinct'],
            'productos.*.cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:999999',
            ],
        ], [
            'idsucursal.required' => 'Seleccioná la sucursal de la venta.',
            'idsucursal.exists' => 'La sucursal seleccionada no está activa.',
            'tipo_cliente.required' => 'Seleccioná un cliente válido.',
            'tipo_cliente.in' => 'La selección del cliente no es válida.',
            'descuento_adicional.numeric' => 'El descuento adicional debe ser un número.',
            'descuento_adicional.min' => 'El descuento adicional no puede ser menor a 0%.',
            'descuento_adicional.max' => 'El descuento adicional no puede superar el 100%.',
            'idmetodopago.required' => 'Seleccioná un medio de pago.',
            'idmetodopago.integer' => 'El medio de pago seleccionado no es válido.',
            'idmetodopago.exists' => 'El medio de pago seleccionado no existe o está inactivo.',
            'productos.required' => 'Agregá al menos un producto.',
            'productos.min' => 'Agregá al menos un producto.',
            'productos.max' => 'Una venta no puede superar las 100 líneas.',
            'productos.*.idproducto.distinct' => 'Un producto está repetido en la venta.',
            'productos.*.cantidad.required' => 'Indicá la cantidad de cada producto.',
            'productos.*.cantidad.integer' => 'Las cantidades deben ser números enteros.',
            'productos.*.cantidad.min' => 'Las cantidades deben ser mayores a cero.',
        ]);

        $idSucursalVenta = (int) $datos['idsucursal'];
$this->comprobarSucursalPermitida($idSucursalVenta, $request);

        $idCliente = null;
        $porcentajeDescuentoLocal = 0.0;
        $porcentajeDescuentoAdicional = round(
            (float) ($datos['descuento_adicional'] ?? 0),
            2
        );

        if ($datos['tipo_cliente'] === 'registrado') {
            $idClienteSolicitado = (int) ($datos['idcliente'] ?? 0);

            $clienteSeleccionado = $idClienteSolicitado > 0
                ? Cliente::query()
                    ->with('tipoCliente')
                    ->where('idcliente', $idClienteSolicitado)
                    ->where('estado', 1)
                    ->first()
                : null;

            if (!$clienteSeleccionado) {
                throw ValidationException::withMessages([
                    'idcliente' => 'El cliente seleccionado no existe o está inactivo.',
                ]);
            }

            $idCliente = $idClienteSolicitado;
            $tipoClienteSeleccionado = $clienteSeleccionado->tipoCliente;

            if (
                $tipoClienteSeleccionado
                && (int) $tipoClienteSeleccionado->estado === 1
                && (bool) $tipoClienteSeleccionado->aplica_local
            ) {
                $porcentajeDescuentoLocal = max(
                    0.0,
                    min(100.0, (float) $tipoClienteSeleccionado->descuento)
                );
            }
        }

        /** @var User|null $usuario */

        $usuario = $usuarioOperacion ?? $request->user('web') ?? auth('web')->user();
        abort_unless($usuario, 403);

        $resultado = DB::transaction(function () use (
            $datos,
            $idSucursalVenta,
            $idCliente,
            $porcentajeDescuentoLocal,
            $porcentajeDescuentoAdicional,
            $usuario,
            $intentoPago
        ) {
            // La fila del intento queda bloqueada dentro de la misma transacción
            // que registra la venta y descuenta su stock.
            $intentoBloqueado = null;
            if ($intentoPago !== null) {
                $intentoBloqueado = IntentoPagoMp::query()
                    ->whereKey($intentoPago->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($intentoBloqueado->idventa !== null) {
                    throw ValidationException::withMessages([
                        'pago' => 'Este cobro ya tiene una venta registrada.',
                    ]);
                }

                if ($intentoBloqueado->estado !== 'APROBADO_PENDIENTE_VENTA'
                    || !$intentoBloqueado->mp_payment_id
                    || !$intentoBloqueado->pagado_at) {
                    throw ValidationException::withMessages([
                        'pago' => 'El pago todavía no está verificado como aprobado.',
                    ]);
                }

                if ((int) $intentoBloqueado->idusuario !== (int) $usuario->id
                    || (int) $intentoBloqueado->idsucursal !== $idSucursalVenta) {
                    abort(403, 'El intento no pertenece a esta operación.');
                }

                $datosGuardados = $intentoBloqueado->datos_venta;
                if (!is_array($datosGuardados)
                    || $this->normalizarDatosVenta($datosGuardados) !== $this->normalizarDatosVenta($datos)) {
                    throw ValidationException::withMessages([
                        'pago' => 'Los datos de la venta cambiaron después de iniciar el cobro.',
                    ]);
                }
            }
            $metodoPago = MetodoPago::query()
                ->whereKey((int) $datos['idmetodopago'])
                ->where('estado', 1)
                ->lockForUpdate()
                ->first();

            if (!$metodoPago) {
                throw ValidationException::withMessages([
                    'idmetodopago' => 'El medio de pago seleccionado ya no está disponible.',
                ]);
            }

            // Protección temporal hasta completar la integración con Mercado Pago.
            if ($intentoBloqueado === null && in_array((int) $metodoPago->idmetodopago, [2, 4], true)) {
                throw ValidationException::withMessages([
                    'idmetodopago' =>
                        'Este medio de pago requiere confirmación de Mercado Pago. '
                        . 'La integración todavía está en preparación.',
                ]);
            }

            $porcentajeRecargo = round((float) $metodoPago->recargo, 2);

            if ($porcentajeRecargo < 0 || $porcentajeRecargo > 100) {
                throw ValidationException::withMessages([
                    'idmetodopago' => 'El recargo configurado para este medio de pago no es válido.',
                ]);
            }

            $tipoMovimiento = TipoMovimiento::query()
                ->where('codigo', 'VENTA_STOCK')
                ->where('estado', 1)
                ->first();

            if (!$tipoMovimiento) {
                throw ValidationException::withMessages([
                    'venta' => 'No está configurado el movimiento VENTA_STOCK.',
                ]);
            }

            $sucursalesActivas = Sucursal::query()
                ->where('estado', 1)
                ->get(['idsucursal', 'nombre'])
                ->keyBy(fn (Sucursal $sucursal) => (int) $sucursal->idsucursal);

            $sucursalVenta = $sucursalesActivas->get($idSucursalVenta);

            if (!$sucursalVenta) {
                throw ValidationException::withMessages([
                    'idsucursal' => 'La sucursal de la venta ya no se encuentra activa.',
                ]);
            }

            $productosSolicitados = collect($datos['productos']);
            $idsProductos = $productosSolicitados
                ->pluck('idproducto')
                ->map(fn ($idProducto) => (int) $idProducto)
                ->values();

            // Se bloquea el stock de todas las sucursales para poder completar
            // la venta con otra ubicación si la sucursal vendedora no alcanza.
            $existencias = Stock::query()
                ->with('producto')
                ->where('estado', 1)
                ->where('stockactual', '>', 0)
                ->whereIn('idsucursal', $sucursalesActivas->keys())
                ->whereIn('idproducto', $idsProductos)
                ->orderBy('idstock')
                ->lockForUpdate()
                ->get()
                ->groupBy(fn (Stock $stock) => (int) $stock->idproducto);

            $idsIva = $existencias
                ->flatMap(fn ($stocks) => $stocks)
                ->map(fn (Stock $stock) => (int) $stock->producto?->idiva)
                ->filter()
                ->unique()
                ->values();

            $alicuotas = DB::table('alicuotas_iva')
                ->whereIn('idiva', $idsIva)
                ->where('estado', 1)
                ->where(function ($consulta) {
                    $consulta
                        ->whereNull('vigente_desde')
                        ->orWhereDate(
                            'vigente_desde',
                            '<=',
                            now(self::ZONA_HORARIA)->toDateString()
                        );
                })
                ->where(function ($consulta) {
                    $consulta
                        ->whereNull('vigente_hasta')
                        ->orWhereDate(
                            'vigente_hasta',
                            '>=',
                            now(self::ZONA_HORARIA)->toDateString()
                        );
                })
                ->get()
                ->keyBy(fn ($alicuota) => (int) $alicuota->idiva);

            $lineas = [];
            $salidasPorSucursal = [];
            $subtotalVenta = 0.0;
            $descuentoVenta = 0.0;
            $totalVenta = 0.0;
            $usaStockRemoto = false;

            foreach ($productosSolicitados as $indice => $solicitado) {
                $idProducto = (int) $solicitado['idproducto'];
                $cantidad = (int) $solicitado['cantidad'];
                $stocksProducto = $existencias->get($idProducto, collect());

                if ($stocksProducto->isEmpty()) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" =>
                            'El producto ya no posee stock disponible en ninguna sucursal.',
                    ]);
                }

                /** @var Stock|null $stockReferencia */
                $stockReferencia = $stocksProducto->first();
                $producto = $stockReferencia?->producto;

                if (!$producto || !$producto->estado) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => 'El producto ya no está activo.',
                    ]);
                }

                $disponibleTotal = (int) $stocksProducto->sum('stockactual');

                if ($disponibleTotal < $cantidad) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.cantidad" => sprintf(
                            'Stock insuficiente para %s. Disponibles entre todas las sucursales: %d.',
                            $producto->descripcion,
                            $disponibleTotal
                        ),
                    ]);
                }

                $precioUnitario = round((float) $producto->pvp, 2);

                if ($precioUnitario <= 0) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => sprintf(
                            '%s no tiene un precio de venta válido.',
                            $producto->descripcion
                        ),
                    ]);
                }

                $idIva = (int) $producto->idiva;
                $alicuota = $alicuotas->get($idIva);

                if (!$alicuota) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => sprintf(
                            '%s no tiene una alícuota de IVA activa y vigente.',
                            $producto->descripcion
                        ),
                    ]);
                }

                $porcentajeIva = (float) $alicuota->porcentaje;

                if ($porcentajeIva < 0 || $porcentajeIva > 100) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => 'El porcentaje de IVA no es válido.',
                    ]);
                }

                $subtotalLinea = round($precioUnitario * $cantidad, 2);

                // Primero se aplica el beneficio automático del tipo de cliente.
                $descuentoClienteLinea = round(
                    $subtotalLinea * ($porcentajeDescuentoLocal / 100),
                    2
                );
                $subtotalTrasCliente = round(
                    $subtotalLinea - $descuentoClienteLinea,
                    2
                );

                // El descuento adicional lo decide el vendedor y se aplica sobre
                // el importe que quedó luego del descuento del cliente.
                $descuentoAdicionalLinea = round(
                    $subtotalTrasCliente * ($porcentajeDescuentoAdicional / 100),
                    2
                );
                $descuentoLinea = round(
                    $descuentoClienteLinea + $descuentoAdicionalLinea,
                    2
                );
                $totalLinea = round($subtotalLinea - $descuentoLinea, 2);
                $baseSinIva = round(
                    $totalLinea / (1 + ($porcentajeIva / 100)),
                    2
                );
                $importeIva = round($totalLinea - $baseSinIva, 2);

                $lineas[] = [
                    'idproducto' => $idProducto,
                    'cantidad' => $cantidad,
                    'detalle' => [
                        'idproducto' => $idProducto,
                        'idiva' => $idIva,
                        'cantidad' => $cantidad,
                        'costounitario' => 0,
                        'preciounitario' => $precioUnitario,
                        'subtotal' => $subtotalLinea,
                        'descuento' => $descuentoLinea,
                        'importeiva' => $importeIva,
                        'totallinea' => $totalLinea,
                    ],
                ];

                // Primero se consume la sucursal de la venta. Si no alcanza,
                // se completa con las otras sucursales con mayor stock.
                $stockLocal = $stocksProducto->first(
                    fn (Stock $stock) => (int) $stock->idsucursal === $idSucursalVenta
                );

                $stocksOrdenados = collect();

                if ($stockLocal) {
                    $stocksOrdenados->push($stockLocal);
                }

                $stocksOrdenados = $stocksOrdenados->concat(
                    $stocksProducto
                        ->reject(fn (Stock $stock) =>
                            (int) $stock->idsucursal === $idSucursalVenta)
                        ->sortByDesc(fn (Stock $stock) => (int) $stock->stockactual)
                );

                $pendiente = $cantidad;

                foreach ($stocksOrdenados as $stockOrigen) {
                    if ($pendiente <= 0) {
                        break;
                    }

                    $tomar = min($pendiente, (int) $stockOrigen->stockactual);

                    if ($tomar <= 0) {
                        continue;
                    }

                    $idSucursalOrigen = (int) $stockOrigen->idsucursal;
                    $salidasPorSucursal[$idSucursalOrigen] ??= [];
                    $salidasPorSucursal[$idSucursalOrigen][] = [
                        'stock' => $stockOrigen,
                        'idproducto' => $idProducto,
                        'cantidad' => $tomar,
                    ];

                    if ($idSucursalOrigen !== $idSucursalVenta) {
                        $usaStockRemoto = true;
                    }

                    $pendiente -= $tomar;
                }

                $subtotalVenta = round($subtotalVenta + $subtotalLinea, 2);
                $descuentoVenta = round($descuentoVenta + $descuentoLinea, 2);
                $totalVenta = round($totalVenta + $totalLinea, 2);
            }

            $importeRecargo = round(
                $totalVenta * ($porcentajeRecargo / 100),
                2
            );
            $totalFinalVenta = round($totalVenta + $importeRecargo, 2);

            if ($intentoBloqueado !== null) {
                if (!in_array((int) $metodoPago->idmetodopago, [2, 4], true)) {
                    throw ValidationException::withMessages([
                        'pago' => 'Este medio no corresponde a un cobro de Mercado Pago.',
                    ]);
                }
                // Se coteja dentro de la transacción contra el precio definitivo.
                if (abs($totalFinalVenta - (float) $intentoBloqueado->importe) >= 0.005) {
                    throw ValidationException::withMessages([
                        'pago' => 'El total actual no coincide con el cobrado. Requiere conciliación o devolución.',
                    ]);
                }
            }

            $fecha = now('UTC');

            $venta = Venta::create([
                'idcliente' => $idCliente,
                'idusuario' => $usuario->id,
                'idsucursal' => $idSucursalVenta,
                'fecha' => $fecha,
                'canal' => 'LOCAL',
                'tipo' => 'DIRECTA',
                'estado' => 'CONFIRMADA',
                'subtotal' => $subtotalVenta,
                'descuento' => $descuentoVenta,
                'porcentajedescuentocliente' => $porcentajeDescuentoLocal,
                'porcentajedescuentoadicional' => $porcentajeDescuentoAdicional,
                'idmetodopago' => (int) $metodoPago->idmetodopago,
                'porcentajerecargo' => $porcentajeRecargo,
                'importerecargo' => $importeRecargo,
                'total' => $totalFinalVenta,
                'facturada' => false,
            ]);

            foreach ($lineas as $linea) {
                $venta->detalles()->create($linea['detalle']);
            }

            $numeroVenta = 'V-' . str_pad(
                (string) $venta->idventa,
                6,
                '0',
                STR_PAD_LEFT
            );

            foreach ($salidasPorSucursal as $idSucursalOrigen => $salidas) {
                $esRemota = (int) $idSucursalOrigen !== $idSucursalVenta;
                $nombreOrigen = $sucursalesActivas->get((int) $idSucursalOrigen)?->nombre
                    ?? 'Sucursal';

                $movimiento = Movimiento::create([
                    'idtipomovimiento' => $tipoMovimiento->idtipomovimiento,
                    'idusuario' => $usuario->id,
                    'idsucursalorigen' => (int) $idSucursalOrigen,
                    // Cuando es remoto, este campo permite que la sucursal
                    // de origen vea el aviso de qué local realizó la venta.
                    'idsucursaldestino' => $esRemota ? $idSucursalVenta : null,
                    'idventa' => $venta->idventa,
                    'fecha' => $fecha,
                    'referencia' => sprintf(
                        'VTA-%06d-S%03d',
                        $venta->idventa,
                        (int) $idSucursalOrigen
                    ),
                    'observacion' => $esRemota
                        ? sprintf(
                            '%s fue realizada en %s usando stock de %s. El stock ya fue descontado; preparar o coordinar la entrega.',
                            $numeroVenta,
                            $sucursalVenta->nombre,
                            $nombreOrigen
                        )
                        : 'Salida automática por venta local',
                    'estado' => 1,
                ]);

                foreach ($salidas as $salida) {
                    DetalleMovimiento::create([
                        'idmovimiento' => $movimiento->idmovimiento,
                        'idproducto' => $salida['idproducto'],
                        'cantidad' => $salida['cantidad'],
                        'costounitario' => null,
                        'subtotal' => null,
                    ]);

                    /** @var Stock $stock */
                    $stock = $salida['stock'];
                    $stock->stockactual = (int) $stock->stockactual
                        - (int) $salida['cantidad'];
                    $stock->save();
                }
            }

            if ($intentoBloqueado !== null) {
                $intentoBloqueado->idventa = $venta->idventa;
                $intentoBloqueado->estado = 'CONFIRMADO';
                $intentoBloqueado->confirmado_at = now();
                $intentoBloqueado->save();
            }

            return [
                'venta' => $venta,
                'usaStockRemoto' => $usaStockRemoto,
            ];
        }, 3);

        /** @var Venta $venta */
        $venta = $resultado['venta'];

        if (!app()->runningInConsole()) {
            session()->flash(
                'exito',
                'Venta V-' . str_pad((string) $venta->idventa, 6, '0', STR_PAD_LEFT)
                    . ' registrada correctamente.'
            );
        }

        return response()->json([
            'mensaje' => $resultado['usaStockRemoto']
                ? 'Venta registrada. Se utilizó stock de otra sucursal y se generó el aviso correspondiente.'
                : 'Venta registrada correctamente.',
            'idventa' => (int) $venta->idventa,
            'usa_stock_remoto' => (bool) $resultado['usaStockRemoto'],
            'redirect' => route('ventas.index'),
        ], 201);
    }

    private function comprobarSucursalPermitida(int $idSucursal, Request $request): void
    {
        /** @var User|null $usuario */
        $usuario = $request->user('web');
        abort_unless($usuario, 403);
        $usuario->loadMissing('rol');
        $esDuenoGlobal = $usuario->rol?->nombre === 'DUEÑO'
            && $usuario->idsucursal === null;
        abort_unless(
            $esDuenoGlobal || (
                $usuario->idsucursal !== null &&
                (int) $usuario->idsucursal === $idSucursal
            ),
            403,
            'No podés registrar ventas desde esta sucursal.'
        );
    }

    private function normalizarDatosVenta(array $datos): array
    {
        return [
            'tipo_cliente' => (string) ($datos['tipo_cliente'] ?? ''),
            'idcliente' => empty($datos['idcliente']) ? null : (int) $datos['idcliente'],
            'idmetodopago' => (int) ($datos['idmetodopago'] ?? 0),
            'descuento_adicional' => round((float) ($datos['descuento_adicional'] ?? 0), 2),
            'productos' => collect($datos['productos'] ?? [])->map(fn ($linea) => [
                'idproducto' => (int) $linea['idproducto'],
                'cantidad' => (int) $linea['cantidad'],
            ])->sortBy('idproducto')->values()->all(),
        ];
    }
}
