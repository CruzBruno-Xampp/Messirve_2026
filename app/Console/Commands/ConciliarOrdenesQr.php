<?php

namespace App\Console\Commands;

use App\Models\IntentoPagoMp;
use App\Models\User;
use App\Services\ConfirmadorVentaLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\MercadoPagoQrEstatico;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Fase 2: concilia órdenes QR y registra pagos acreditados sin navegador.
 * Usa el confirmador transaccional de ventas existente.
 */
class ConciliarOrdenesQr extends Command
{
    protected $signature = 'messirve:conciliar-qr {--limite=25 : Máximo de órdenes por ejecución} {--simular : Consultar sin modificar la base de datos}';

    protected $description = 'Concilia órdenes QR y confirma ventas acreditadas verificadas';

    public function handle(MercadoPagoQrEstatico $qr, ConfirmadorVentaLocal $confirmador): int
    {
        $limite = (int) $this->option('limite');
        if ($limite < 1 || $limite > 100) {
            $this->error('El límite debe estar entre 1 y 100.');
            return self::FAILURE;
        }

        $simular = (bool) $this->option('simular');

        $intentos = IntentoPagoMp::query()
            ->whereIn('estado', ['PENDIENTE', 'APROBADO_PENDIENTE_VENTA'])
            ->whereNull('idventa')
            ->whereNotNull('mp_order_id')
            ->orderBy('id')
            ->limit($limite)
            ->get();

        $vencidos = 0;
        $pagados = 0;
        $pendientes = 0;
        $errores = 0;

        foreach ($intentos as $intento) {
            try {
                $orden = $qr->consultarOrden((string) $intento->mp_order_id);
                $coincide = ($orden['id'] ?? null) === $intento->mp_order_id
                    && ($orden['external_reference'] ?? null) === $intento->referencia
                    && ($orden['type'] ?? null) === 'qr'
                    && ($orden['currency'] ?? null) === 'ARS'
                    && ($orden['config']['qr']['mode'] ?? null) === 'static'
                    && ($orden['config']['qr']['external_pos_id'] ?? null) === $intento->qr_external_pos_id;

                if (!$coincide) {
                    $errores++;
                    Log::error('Conciliación QR: identidad de orden inconsistente', [
                        'intento_id' => $intento->getKey(),
                    ]);
                    continue;
                }

                $acreditada = $qr->ordenAcreditada(
                    $orden,
                    (string) $intento->referencia,
                    (string) $intento->importe,
                    (int) $intento->idsucursal
                );

                if ($acreditada) {
                    $pagados++;
                    if ($simular) {
                        $this->warn('[SIMULACIÓN] Pago acreditado en intento #' . $intento->getKey() . '. No se registra venta.');
                        continue;
                    }
                    $pagoId = $orden['transactions']['payments'][0]['id'] ?? null;
                    if (!is_string($pagoId) || trim($pagoId) === '' || strlen($pagoId) > 150) {
                        throw new \RuntimeException('Identificador de transacción ausente o inválido.');
                    }
                    $usuario = User::query()->find($intento->idusuario);
                    if (!$usuario) {
                        throw new \RuntimeException('No existe el usuario que inició el cobro; requiere revisión.');
                    }
                    $datosVenta = $intento->datos_venta;
                    if (!is_array($datosVenta) || (int) ($datosVenta['idmetodopago'] ?? 0) !== 2) {
                        throw new \RuntimeException('Datos originales de la venta inválidos.');
                    }
                    // Releer y bloquear: otro proceso puede haber confirmado entre la consulta
                    // de Mercado Pago y este punto. El estado aprobado también es recuperable.
                    $asociado = DB::transaction(function () use ($intento, $pagoId) {
                        $actual = IntentoPagoMp::query()
                            ->whereKey($intento->getKey())->lockForUpdate()->firstOrFail();
                        if ($actual->idventa !== null || $actual->estado === 'CONFIRMADO') {
                            return false;
                        }
                        if (!in_array($actual->estado, ['PENDIENTE', 'APROBADO_PENDIENTE_VENTA'], true)
                            || (string) $actual->mp_order_id !== (string) $intento->mp_order_id) {
                            return false;
                        }
                        if ($actual->mp_payment_id !== null && (string) $actual->mp_payment_id !== $pagoId) {
                            throw new \RuntimeException('La orden tiene otro identificador de pago; requiere revisión.');
                        }
                        if (IntentoPagoMp::query()->where('mp_payment_id', $pagoId)
                            ->whereKeyNot($actual->getKey())->exists()) {
                            throw new \RuntimeException('Transacción vinculada a otro intento.');
                        }
                        $actual->mp_payment_id = $pagoId;
                        $actual->pagado_at ??= now();
                        $actual->estado = 'APROBADO_PENDIENTE_VENTA';
                        $actual->save();
                        return true;
                    }, 3);
                    if (!$asociado) continue;
                    $solicitud = Request::create('/', 'POST', array_merge($datosVenta, [
                        'idsucursal' => (int) $intento->idsucursal,
                    ]));
                    $solicitud->setUserResolver(fn ($guard = null) => $usuario);
                    try {
                        $respuesta = $confirmador->confirmar($solicitud, $intento, $usuario);
                        if (!in_array($respuesta->getStatusCode(), [200, 201], true)) {
                            throw new \RuntimeException('La confirmación no devolvió un resultado satisfactorio.');
                        }
                        $this->info('Venta registrada para intento QR #' . $intento->getKey());
                    } catch (Throwable $errorVenta) {
                        // Concurrencia: el otro proceso pudo confirmar mientras esperábamos el lock.
                        // Nunca sobrescribimos CONFIRMADO por REVISION.
                        $estadoActual = IntentoPagoMp::query()->find($intento->getKey());
                        if ($estadoActual && $estadoActual->idventa !== null
                            && $estadoActual->estado === 'CONFIRMADO') {
                            $this->line('Intento QR #' . $intento->getKey()
                                . ' ya confirmado por otro proceso.');
                            continue;
                        }
                        // Un error de validación empresarial necesita intervención.
                        // Errores transitorios (deadlock, caída de MySQL, etc.) quedan
                        // APROBADO_PENDIENTE_VENTA y serán reintentados en la siguiente ejecución.
                        if ($errorVenta instanceof ValidationException) {
                            IntentoPagoMp::query()->whereKey($intento->getKey())
                                ->whereNull('idventa')->where('estado', 'APROBADO_PENDIENTE_VENTA')
                                ->update(['estado' => 'REVISION']);
                        }
                        throw $errorVenta;
                    }
                    continue;
                }

                $estado = (string) ($orden['status'] ?? '');
                if (in_array($estado, ['expired', 'canceled'], true)) {
                    $nuevoEstado = $estado === 'expired' ? 'VENCIDO' : 'CANCELADO';
                    $actualizadas = $simular ? 0 : IntentoPagoMp::query()
                        ->whereKey($intento->getKey())
                        ->where('estado', 'PENDIENTE')
                        ->whereNull('idventa')
                        ->whereNull('pagado_at')
                        ->whereNull('mp_payment_id')
                        ->where('mp_order_id', $intento->mp_order_id)
                        ->update(['estado' => $nuevoEstado]);
                    $vencidos += $actualizadas;
                } else {
                    // Si ya hay pago asociado, nunca degradamos el intento por
                    // una respuesta no acreditada; conservamos para conciliar.
                    $pendientes++;
                }
            } catch (Throwable $e) {
                $errores++;
                Log::error('Conciliación QR: no se pudo verificar una orden', [
                    'intento_id' => $intento->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($simular) $this->warn("MODO SIMULACIÓN: no se modificó ninguna orden ni venta.");
        $this->info("QR revisados: {$intentos->count()}; vencidos/cancelados: {$vencidos}; acreditados detectados: {$pagados}; pendientes: {$pendientes}; errores: {$errores}.");
        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
