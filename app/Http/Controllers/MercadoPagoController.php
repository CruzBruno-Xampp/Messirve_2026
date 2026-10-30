<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use App\Models\IntentoPagoMp;
use App\Models\MetodoPago;
use App\Models\Sucursal;
use App\Services\MercadoPagoPreferencias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
use App\Services\CalculadoraVentaLocal;
use App\Services\ConfirmadorVentaLocal;

class MercadoPagoController extends Controller
{
    public function crearPreferencia(
        Request $request,
        MercadoPagoPreferencias $servicio,
        CalculadoraVentaLocal $calculadora
    ): JsonResponse {
        $datos = $request->validate([
            'idsucursal' => [
                'required', 'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where('estado', 1),
            ],
            'tipo_cliente' => [
                'required',
                Rule::in(['consumidor_final', 'registrado']),
            ],
            'idcliente' => ['nullable', 'integer'],
            'descuento_adicional' => [
                'nullable', 'numeric', 'min:0', 'max:100',
            ],
            'idmetodopago' => [
                'required', 'integer',
                Rule::exists('metodos_pago', 'idmetodopago')
                    ->where('estado', 1),
            ],
            'productos' => ['required', 'array', 'min:1', 'max:100'],
            'productos.*.idproducto' => [
                'required', 'integer', 'distinct',
            ],
            'productos.*.cantidad' => [
                'required', 'integer', 'min:1', 'max:999999',
            ],
        ]);
        /** @var \App\Models\User|null $usuario */
        $usuario = auth('web')->user();

        abort_unless($usuario, 403);

        $usuario->loadMissing('rol');

        $esDuenoGlobal = $usuario->rol?->nombre === 'DUEÑO'
            && $usuario->idsucursal === null;

        abort_unless(
            $esDuenoGlobal ||
            (
                $usuario->idsucursal !== null &&
                (int) $usuario->idsucursal === (int) $datos['idsucursal']
            ),
            403,
            'No tenés permiso para vender desde esta sucursal.'
        );

        $sucursal = Sucursal::query()
            ->whereKey($datos['idsucursal'])
            ->where('estado', 1)
            ->firstOrFail();

        $metodo = MetodoPago::query()
            ->whereKey($datos['idmetodopago'])
            ->where('estado', 1)
            ->firstOrFail();

        // Los métodos 2 y 4 se integrarán con Mercado Pago.
        // El método 3 (débito) se conserva por ahora como cobro local.
        abort_unless(
            in_array((int) $metodo->idmetodopago, [2, 4], true),
            422,
            'Este medio de pago no utiliza Mercado Pago.'
        );

        // Una sola fuente de cálculo para los pagos del local.
        // La calculadora usa precios y stock de MySQL, sin descontar unidades.
        $cotizacion = $calculadora->calcular($datos);
        $importe = (float) $cotizacion['total'];
        $idCliente = $cotizacion['idcliente'];
        $descuentoAdicional = (float) $cotizacion['descuento_adicional_porcentaje'];

        $intento = IntentoPagoMp::create([
            'referencia' => (string) Str::uuid(),
            'idusuario' => $usuario->id,
            'idsucursal' => $sucursal->idsucursal,
            'datos_venta' => [
                'tipo_cliente' => $datos['tipo_cliente'],
                'idcliente' => $idCliente,
                'idmetodopago' => $metodo->idmetodopago,
                'descuento_adicional' => $descuentoAdicional,
                'productos' => $datos['productos'],
            ],
            'importe' => $importe,
            'estado' => 'CREADO',
        ]);

        try {
            $enlace = $servicio->crear($intento);
        } catch (Throwable $error) {
            report($error);

            $intento->update(['estado' => 'ERROR']);

            return response()->json([
                'mensaje' => 'No se pudo preparar el cobro con Mercado Pago.',
            ], 502);
        }

        return response()->json([
            'mensaje' => 'Solicitud de cobro creada.',
            'referencia' => $intento->referencia,
            'importe' => $intento->importe,
            'enlace_pago' => $enlace,
            'estado' => $intento->estado,
        ], 201);
    }

public function consultarPago(Request $request): JsonResponse
{
    $datos = $request->validate([
        'referencia' => ['required', 'uuid'],
        'payment_id' => ['required', 'integer', 'min:1'],
    ]);

    $usuario = auth('web')->user();
    abort_unless($usuario, 403);

    /** @var \App\Models\IntentoPagoMp|null $intento */
    $intento = IntentoPagoMp::query()
        ->where('referencia', $datos['referencia'])
        ->where('idusuario', $usuario->id)
        ->firstOrFail();

    // No permitir asignar otro pago a un intento ya vinculado.
    if (
        $intento->mp_payment_id !== null &&
        (string) $intento->mp_payment_id !== (string) $datos['payment_id']
    ) {
        return response()->json([
            'mensaje' => 'El intento ya tiene otro pago asociado.',
        ], 409);
    }

    $token = config('services.mercadopago.access_token');

    if (!is_string($token) || $token === '') {
        return response()->json([
            'mensaje' => 'Mercado Pago no está configurado.',
        ], 503);
    }

    try {
        $respuesta = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->get(
                'https://api.mercadopago.com/v1/payments/'
                . $datos['payment_id']
            );
    } catch (\Throwable $error) {
        report($error);

        return response()->json([
            'mensaje' => 'No se pudo consultar Mercado Pago.',
        ], 502);
    }

    if (!$respuesta->successful()) {
        return response()->json([
            'mensaje' => 'Mercado Pago no pudo verificar el pago.',
        ], 502);
    }

    $pago = $respuesta->json();

    // Verificamos que corresponda exactamente a nuestro intento.
    $referenciaValida =
        (string) ($pago['external_reference'] ?? '')
        === (string) $intento->referencia;

    $monedaValida = ($pago['currency_id'] ?? '') === 'ARS';

    $importeValido = isset($pago['transaction_amount'])
        && is_numeric($pago['transaction_amount'])
        && abs(
            (float) $pago['transaction_amount']
            - (float) $intento->importe
        ) < 0.005;

    if (
        !$referenciaValida ||
        !$monedaValida ||
        !$importeValido
    ) {
        return response()->json([
            'mensaje' => 'Los datos del pago no coinciden con la venta.',
        ], 422);
    }

    $estadoMp = (string) ($pago['status'] ?? '');

    $estadoInterno = match ($estadoMp) {
        'approved' => 'APROBADO_PENDIENTE_VENTA',
        'pending', 'in_process', 'authorized' => 'PENDIENTE',
        'rejected', 'cancelled' => 'RECHAZADO',
        default => 'REVISION',
    };

    // Guardamos el resultado de manera controlada.
    DB::transaction(function () use (
        $intento,
        $datos,
        $estadoInterno
    ) {
        $registro = IntentoPagoMp::query()
            ->whereKey($intento->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $registro->mp_payment_id !== null &&
            (string) $registro->mp_payment_id
                !== (string) $datos['payment_id']
        ) {
            abort(409, 'El pago ya está vinculado a otra operación.');
        }

        if ($registro->idventa !== null) {
            return;
        }

        if ($registro->estado === 'APROBADO_PENDIENTE_VENTA') {
            return;
        }

        $registro->mp_payment_id = (string) $datos['payment_id'];
        $registro->estado = $estadoInterno;

        if ($estadoInterno === 'APROBADO_PENDIENTE_VENTA') {
            $registro->pagado_at = now();
        }

        $registro->save();
    });

    $intento->refresh();

    return response()->json([
        'referencia' => $intento->referencia,
        'estado_mp' => $estadoMp,
        'estado_interno' => $intento->estado,
        'venta_confirmada' => $intento->idventa !== null,
        'mensaje' => $intento->estado === 'APROBADO_PENDIENTE_VENTA'
            ? 'Pago aprobado. Falta registrar la venta.'
            : 'Estado del pago consultado correctamente.',
    ]);
}





    /**
     * Confirma una venta local exclusivamente desde un intento MP
     * previamente verificado, usando los datos persistidos en MySQL.
     * No se invoca automáticamente al recibir la URL de retorno.
     */
    public function confirmarVenta(Request $request, ConfirmadorVentaLocal $confirmador): JsonResponse
    {
        $datos = $request->validate([
            'referencia' => ['required', 'uuid'],
        ]);

        /** @var \App\Models\User|null $usuario */
        $usuario = $request->user('web');
        abort_unless($usuario, 403);

        $intento = IntentoPagoMp::query()
            ->where('referencia', $datos['referencia'])
            ->where('idusuario', $usuario->id)
            ->firstOrFail();

        if ($intento->idventa !== null) {
            return response()->json([
                'mensaje' => 'Este intento ya tiene una venta confirmada.',
                'idventa' => (int) $intento->idventa,
            ], 409);
        }

        if ($intento->estado !== 'APROBADO_PENDIENTE_VENTA'
            || !$intento->mp_payment_id
            || !$intento->pagado_at) {
            return response()->json([
                'mensaje' => 'El cobro todavía no está verificado como aprobado.',
            ], 409);
        }

        // Seguridad: no alcanza con el estado guardado previamente.
        // Consultamos nuevamente el pago en Mercado Pago justo antes de
        // confirmar la venta y descontar existencias.
        $token = config('services.mercadopago.access_token');
        if (!is_string($token) || $token === '') {
            return response()->json([
                'mensaje' => 'Mercado Pago no está configurado.',
            ], 503);
        }

        try {
            $respuestaMp = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.mercadopago.com/v1/payments/' . $intento->mp_payment_id);
        } catch (Throwable $error) {
            report($error);
            return response()->json([
                'mensaje' => 'No se pudo verificar nuevamente el pago. No se registró la venta.',
            ], 502);
        }

        if (!$respuestaMp->successful()) {
            return response()->json([
                'mensaje' => 'No se pudo verificar nuevamente el pago. No se registró la venta.',
            ], 502);
        }

        $pagoVerificado = $respuestaMp->json();
        if (!is_array($pagoVerificado)
            || (string) ($pagoVerificado['id'] ?? '') !== (string) $intento->mp_payment_id
            || ($pagoVerificado['status'] ?? '') !== 'approved'
            || ($pagoVerificado['currency_id'] ?? '') !== 'ARS'
            || (string) ($pagoVerificado['external_reference'] ?? '') !== (string) $intento->referencia
            || !isset($pagoVerificado['transaction_amount'])
            || !is_numeric($pagoVerificado['transaction_amount'])
            || abs((float) $pagoVerificado['transaction_amount'] - (float) $intento->importe) >= 0.005
        ) {
            return response()->json([
                'mensaje' => 'El pago no figura aprobado con la referencia e importe esperados. No se registró la venta.',
            ], 409);
        }

        $ventaGuardada = $intento->datos_venta;
        if (!is_array($ventaGuardada)) {
            return response()->json([
                'mensaje' => 'No se encontraron datos válidos para este intento.',
            ], 422);
        }

        // El navegador NO decide cantidades, cliente, descuentos ni importe.
        // El confirmador repite las validaciones y recalcula dentro de una
        // transacción antes de registrar movimientos de stock.
        $request->replace(array_merge($ventaGuardada, [
            'idsucursal' => (int) $intento->idsucursal,
        ]));

        return $confirmador->confirmar($request, $intento);
    }
}

