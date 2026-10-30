<?php

namespace App\Http\Controllers;

use App\Models\IntentoPagoMp;
use App\Services\CalculadoraVentaLocal;
use App\Services\ConfirmadorVentaLocal;
use App\Services\MercadoPagoQrEstatico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Cobros QR: verificación remota, recuperación y confirmación idempotente. */
class MercadoPagoQrController extends Controller
{
    public function crear(Request $request, CalculadoraVentaLocal $calculadora, MercadoPagoQrEstatico $qr): JsonResponse
    {
        $datos = $request->validate([
            'idsucursal' => ['required','integer',Rule::exists('sucursales','idsucursal')->where('estado',1)],
            'tipo_cliente' => ['required', Rule::in(['consumidor_final','registrado'])],
            'idcliente' => ['nullable','integer'],
            'descuento_adicional' => ['nullable','numeric','min:0','max:100'],
            'idmetodopago' => ['required','integer',Rule::in([2])],
            'productos' => ['required','array','min:1','max:100'],
            'productos.*.idproducto' => ['required','integer','distinct'],
            'productos.*.cantidad' => ['required','integer','min:1','max:999999'],
        ]);
        $usuario = $request->user('web');
        abort_unless($usuario, 403);
        $usuario->loadMissing('rol');
        $dueño = $usuario->rol?->nombre === 'DUEÑO' && $usuario->idsucursal === null;
        abort_unless($dueño || ($usuario->idsucursal !== null && (int)$usuario->idsucursal === (int)$datos['idsucursal']), 403);
        $caja = config('services.mercadopago.qr_cajas.' . $datos['idsucursal']);
        if (!is_string($caja) || $caja === '') {
            return response()->json(['message' => 'Falta vincular el ID de sucursal de Messirve a la caja QR en .env.'], 422);
        }
        // Una sola orden no resuelta por caja; no arriesgar reemplazar una orden pagable.
        $ocupada = IntentoPagoMp::query()->where('qr_external_pos_id', $caja)
            ->whereIn('estado', ['CREADO','PENDIENTE','APROBADO_PENDIENTE_VENTA','REVISION','QR_EN_CREACION','QR_REVISAR'])
            ->exists();
        if ($ocupada) {
            return response()->json(['message' => 'Ya hay una orden QR pendiente o por revisar en esta caja. Conciliá antes de crear otra.'], 409);
        }
        $cotizacion = $calculadora->calcular($datos);
        $referencia = (string) Str::uuid();
        $llave = (string) Str::uuid();
        $intento = IntentoPagoMp::create([
            'referencia' => $referencia,
            'idusuario' => $usuario->id,
            'idsucursal' => (int)$datos['idsucursal'],
            'datos_venta' => [
                'tipo_cliente' => $datos['tipo_cliente'],
                'idcliente' => $cotizacion['idcliente'],
                'idmetodopago' => 2,
                'descuento_adicional' => $cotizacion['descuento_adicional_porcentaje'],
                'productos' => $datos['productos'],
            ],
            'importe' => $cotizacion['total'],
            'estado' => 'QR_EN_CREACION',
            'qr_external_pos_id' => $caja,
            'qr_idempotency_key' => $llave,
        ]);
        try {
            $orden = $qr->crearOrden($referencia, (int)$datos['idsucursal'], (string)$intento->importe, $llave);
            $intento->update(['mp_order_id' => $orden['order_id'], 'estado' => 'PENDIENTE']);
            return response()->json([
                'referencia' => $referencia,
                'order_id' => $orden['order_id'],
                'importe' => $intento->importe,
                'estado' => 'PENDIENTE',
                'mensaje' => 'Orden QR creada. El cliente debe escanear el QR fijo de Caja 1.',
            ], 201);
        } catch (Throwable $e) {
            report($e);
            // Puede haberse creado remotamente: NO repetir ni liberar la caja automáticamente.
            $intento->update(['estado' => 'QR_REVISAR']);
            return response()->json(['message' => 'No se pudo confirmar la creación de la orden. No vuelvas a generar cobro hasta conciliar la referencia ' . $referencia . '.'], 502);
        }
    }

    /** Recupera el cobro todavía sin resolver del usuario y sucursal autenticados. */
    public function recuperar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'idsucursal' => ['required', 'integer', Rule::exists('sucursales', 'idsucursal')],
        ]);
        $usuario = $request->user('web');
        abort_unless($usuario, 403);
        $intento = IntentoPagoMp::query()
            ->where('idusuario', $usuario->id)
            ->where('idsucursal', (int) $datos['idsucursal'])
            ->whereNotNull('mp_order_id')
            ->whereIn('estado', ['PENDIENTE', 'APROBADO_PENDIENTE_VENTA', 'REVISION', 'QR_REVISAR'])
            ->whereNull('idventa')
            ->latest('id')->first();
        return response()->json(['intento' => $intento ? [
            'referencia' => $intento->referencia,
            'order_id' => $intento->mp_order_id,
            'importe' => (string) $intento->importe,
            'estado' => $intento->estado,
        ] : null]);
    }

    public function consultar(Request $request, MercadoPagoQrEstatico $qr): JsonResponse
    {
        $datos = $request->validate(['referencia' => ['required','uuid']]);
        $usuario = $request->user('web');
        abort_unless($usuario, 403);
        $intento = IntentoPagoMp::query()->where('referencia', $datos['referencia'])->where('idusuario', $usuario->id)->firstOrFail();
        if (!$intento->mp_order_id) {
            return response()->json(['message' => 'La orden no tiene identificador; requiere conciliación manual.'], 409);
        }
        try {
            $orden = $qr->consultarOrden($intento->mp_order_id);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'No se pudo consultar la orden en Mercado Pago.'], 502);
        }
        $acreditada = $qr->ordenAcreditada($orden, $intento->referencia, (string)$intento->importe, (int)$intento->idsucursal);
        $estadoMp = (string) ($orden['status'] ?? 'desconocido');
        // Una orden vencida/cancelada no puede mantenerse bloqueando la caja.
        // Solo se libera si coincidieron orden y referencia, y no hubo venta ni pago acreditado.
        if (in_array($estadoMp, ['expired', 'canceled'], true)
            && ($orden['id'] ?? null) === $intento->mp_order_id
            && ($orden['external_reference'] ?? null) === $intento->referencia
            && !$acreditada) {
            IntentoPagoMp::query()->whereKey($intento->getKey())
                ->whereNull('idventa')->whereNull('pagado_at')->whereNull('mp_payment_id')
                ->whereIn('estado', ['PENDIENTE', 'CREADO'])
                ->update(['estado' => $estadoMp === 'expired' ? 'VENCIDO' : 'CANCELADO']);
        }
        $intento->refresh();
        // No se considera verificada una venta ni se escribe APROBADO_PENDIENTE_VENTA en esta etapa.
        return response()->json([
            'referencia' => $intento->referencia,
            'order_id' => $intento->mp_order_id,
            'estado_mp' => $orden['status'] ?? 'desconocido',
            'detalle_mp' => $orden['status_detail'] ?? null,
            'acreditada_en_mp' => $acreditada,
            'venta_confirmada' => $intento->idventa !== null,
            'idventa' => $intento->idventa,
            'estado_local' => $intento->estado,
            'mensaje' => $acreditada
                ? 'Pago acreditado. Validando y registrando venta automáticamente…'
                : (in_array($estadoMp, ['expired', 'canceled'], true)
                    ? 'La orden venció o se canceló. No se registró ninguna venta.'
                    : 'Esperando acreditación del pago. No se descontó stock.'),
        ]);
    }
    /** Confirma una venta solo si Mercado Pago informa una orden QR acreditada.
     * La operación se asocia en DB antes de llamar al confirmador transaccional.
     * Una falla posterior exige conciliación: nunca se genera un nuevo cobro.
     */
    public function confirmar(Request $request, MercadoPagoQrEstatico $qr, ConfirmadorVentaLocal $confirmador): JsonResponse
    {
        $datos = $request->validate(['referencia' => ['required', 'uuid']]);
        $usuario = $request->user('web');
        abort_unless($usuario, 403);
        $intento = IntentoPagoMp::query()
            ->where('referencia', $datos['referencia'])
            ->where('idusuario', $usuario->id)->firstOrFail();

        if ($intento->idventa !== null) {
            session()->flash('exito', 'Venta V-' . str_pad((string) $intento->idventa, 6, '0', STR_PAD_LEFT) . ' registrada correctamente.');
            return response()->json(['message' => 'La venta ya está registrada.', 'idventa' => (int)$intento->idventa,
                'redirect' => route('ventas.index')], 200);
        }
        if (in_array($intento->estado, ['VENCIDO', 'CANCELADO', 'QR_REVISAR'], true)) {
            return response()->json(['message' => 'El intento requiere revisión y no puede confirmarse automáticamente.'], 409);
        }
        if (!$intento->mp_order_id || !$intento->qr_external_pos_id) {
            return response()->json(['message' => 'Intento sin orden QR conciliada; revisión manual obligatoria.'], 409);
        }
        try {
            $orden = $qr->consultarOrden($intento->mp_order_id);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'No se pudo verificar el cobro con Mercado Pago. No generes otro.'], 502);
        }
        if (!$qr->ordenAcreditada($orden, $intento->referencia, (string)$intento->importe, (int)$intento->idsucursal)
            || ($orden['id'] ?? null) !== $intento->mp_order_id
            || ($orden['config']['qr']['external_pos_id'] ?? null) !== $intento->qr_external_pos_id) {
            return response()->json(['message' => 'La orden no está acreditada o sus datos no coinciden.'], 409);
        }
        $pagoId = $orden['transactions']['payments'][0]['id'] ?? null;
        if (!is_string($pagoId) || trim($pagoId) === '' || strlen($pagoId) > 150) {
            return response()->json(['message' => 'Falta el identificador de transacción para conciliar el pago.'], 409);
        }

        $datosGuardados = $intento->datos_venta;
        if (!is_array($datosGuardados) || (int)($datosGuardados['idmetodopago'] ?? 0) !== 2) {
            return response()->json(['message' => 'Los datos guardados del intento no son válidos.'], 409);
        }
        try {
            DB::transaction(function () use ($intento, $pagoId, $usuario) {
                $bloqueado = IntentoPagoMp::query()->whereKey($intento->getKey())->lockForUpdate()->firstOrFail();
                if ($bloqueado->idventa !== null) {
                    throw ValidationException::withMessages(['pago' => 'Esta orden ya tiene una venta.']);
                }
                if ((int)$bloqueado->idusuario !== (int)$usuario->id ||
                    (string)$bloqueado->mp_order_id !== (string)$intento->mp_order_id ||
                    !in_array($bloqueado->estado, ['PENDIENTE', 'APROBADO_PENDIENTE_VENTA', 'REVISION'], true)) {
                    throw ValidationException::withMessages(['pago' => 'Intento inconsistente; requiere conciliación.']);
                }
                if ($bloqueado->mp_payment_id !== null && $bloqueado->mp_payment_id !== $pagoId) {
                    throw ValidationException::withMessages(['pago' => 'El pago asociado cambió; requiere conciliación.']);
                }
                $otro = IntentoPagoMp::query()->where('mp_payment_id', $pagoId)
                    ->whereKeyNot($bloqueado->getKey())->exists();
                if ($otro) {
                    throw ValidationException::withMessages(['pago' => 'Esta transacción ya se vinculó a otro intento.']);
                }
                $bloqueado->mp_payment_id = $pagoId;
                $bloqueado->pagado_at ??= now();
                $bloqueado->estado = 'APROBADO_PENDIENTE_VENTA';
                $bloqueado->save();
            }, 3);

            // Solo se usan los datos congelados al crear la orden, jamás un carrito actualizado.
            $solicitud = Request::create('/', 'POST', array_merge($datosGuardados, [
                'idsucursal' => (int)$intento->idsucursal,
            ]));
            $solicitud->setUserResolver(fn ($guard = null) => $usuario);
            return $confirmador->confirmar($solicitud, $intento);
        } catch (Throwable $e) {
            report($e);
            // Se conserva el pago verificado para conciliación si falló precio/stock/registro.
            IntentoPagoMp::query()->whereKey($intento->getKey())
                ->whereNull('idventa')->where('estado', 'APROBADO_PENDIENTE_VENTA')
                ->update(['estado' => 'REVISION']);
            return response()->json(['message' => 'Pago acreditado, pero no se pudo registrar la venta. Requiere conciliación; NO vuelvas a cobrar.'], 409);
        }
    }

}
