<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integración aislada con Orders API para QR estático atendido (PDV).
 * NO confirma ventas ni modifica stock. Debe conectarse luego a un flujo
 * persistido e idempotente con verificación estricta de la order.
 */
class MercadoPagoQrEstatico
{
    private const API = 'https://api.mercadopago.com/v1/orders';

    public function crearOrden(
        string $referencia,
        int $idSucursal,
        string $importe,
        ?string $idempotencia = null
    ): array {
        $caja = $this->caja($idSucursal);
        $referencia = trim($referencia);
        if ($referencia === '' || strlen($referencia) > 64) {
            throw new RuntimeException('Referencia QR inválida.');
        }
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $importe) || (float) $importe <= 0) {
            throw new RuntimeException('Importe QR inválido.');
        }
        $importe = number_format((float) $importe, 2, '.', '');
        $idempotencia ??= (string) Str::uuid();
        if (!Str::isUuid($idempotencia)) {
            throw new RuntimeException('La clave de idempotencia debe ser un UUID.');
        }

        $payload = [
            'type' => 'qr',
            'total_amount' => $importe,
            'description' => 'Venta local Messirve',
            'external_reference' => $referencia,
            'expiration_time' => 'PT15M',
            'config' => [
                'qr' => ['external_pos_id' => $caja, 'mode' => 'static'],
            ],
            'transactions' => [
                'payments' => [['amount' => $importe]],
            ],
        ];

        $respuesta = Http::withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-Idempotency-Key' => $idempotencia])
            ->timeout(20)
            ->post(self::API, $payload);

        if (!$respuesta->successful()) {
            throw new RuntimeException('Mercado Pago rechazó la creación de la orden QR (HTTP ' . $respuesta->status() . '). Consultá el log del servidor antes de repetir.');
        }
        $orden = $respuesta->json();
        if (!is_array($orden)
            || !is_string($orden['id'] ?? null)
            || !str_starts_with($orden['id'], 'ORD')
            || ($orden['external_reference'] ?? null) !== $referencia
            || ($orden['config']['qr']['mode'] ?? null) !== 'static'
            || ($orden['config']['qr']['external_pos_id'] ?? null) !== $caja) {
            throw new RuntimeException('La respuesta de la orden QR está incompleta. No repitas sin conciliar.');
        }
        return [
            'order_id' => $orden['id'],
            'referencia' => $referencia,
            'importe' => $importe,
            'caja' => $caja,
            'estado' => $orden['status'] ?? 'created',
            'idempotencia' => $idempotencia,
        ];
    }

    public function consultarOrden(string $orderId): array
    {
        if (!preg_match('/^ORD[A-Za-z0-9]{1,77}$/', $orderId)) {
            throw new RuntimeException('Order ID QR inválido.');
        }
        $respuesta = Http::withToken($this->token())
            ->acceptJson()
            ->timeout(20)
            ->get(self::API . '/' . $orderId);
        if (!$respuesta->successful()) {
            throw new RuntimeException('No se pudo consultar la orden QR (HTTP ' . $respuesta->status() . ').');
        }
        $orden = $respuesta->json();
        if (!is_array($orden) || ($orden['id'] ?? null) !== $orderId) {
            throw new RuntimeException('Respuesta de orden QR inválida.');
        }
        return $orden;
    }

    /**
     * Evaluación informativa: NO reemplaza validación en DB con locks,
     * propiedad de la caja, pagos únicos, moneda, order y referencia.
     */
    public function ordenAcreditada(array $orden, string $referencia, string $importe, int $sucursal): bool
    {
        $pagos = $orden['transactions']['payments'] ?? null;
        if (!is_array($pagos) || count($pagos) !== 1) {
            return false;
        }
        $pago = $pagos[0];
        return ($orden['type'] ?? null) === 'qr'
            && ($orden['external_reference'] ?? null) === $referencia
            && ($orden['currency'] ?? null) === 'ARS'
            && ($orden['config']['qr']['mode'] ?? null) === 'static'
            && ($orden['config']['qr']['external_pos_id'] ?? null) === $this->caja($sucursal)
            && ($orden['status'] ?? null) === 'processed'
            && ($pago['status'] ?? null) === 'processed'
            && ($pago['status_detail'] ?? null) === 'accredited'
            && isset($orden['total_amount'], $pago['amount'])
            && abs((float) $orden['total_amount'] - (float) $importe) < 0.005
            && abs((float) $pago['amount'] - (float) $importe) < 0.005;
    }

    private function caja(int $idSucursal): string
    {
        $cajas = config('services.mercadopago.qr_cajas', []);
        $caja = is_array($cajas) ? ($cajas[$idSucursal] ?? null) : null;
        if (!is_string($caja) || trim($caja) === '') {
            throw new RuntimeException('Falta configurar la caja QR de la sucursal ' . $idSucursal . '.');
        }
        return $caja;
    }

    private function token(): string
    {
        $token = config('services.mercadopago.access_token');
        if (!is_string($token) || trim($token) === '') {
            throw new RuntimeException('Falta MERCADOPAGO_ACCESS_TOKEN.');
        }
        return $token;
    }
}
