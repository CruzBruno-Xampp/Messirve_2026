<?php

namespace Tests\Feature;

use App\Services\MercadoPagoQrEstatico;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Pruebas sin DB ni conexión de red: TODAS las solicitudes HTTP están falsificadas.
 * Verifica la comunicación con Orders API, no el almacenamiento ni los reintentos
 * programados de ConciliarOrdenesQr.
 */
class MercadoPagoQrFallosConexionTest extends TestCase
{
    private const ORDEN_ID = 'ORDTEST123456789';
    private const REFERENCIA = 'a1ee476b-f8f2-4ad8-9667-b8aa9147fdf9';
    private const CAJA = 'MESSIRVEALMCAJA1';

    protected function setUp(): void
    {
        parent::setUp();
        // Prohíbe llamadas reales a Mercado Pago si falta un Http::fake.
        Http::preventStrayRequests();
        config()->set('services.mercadopago.access_token', 'TOKEN_FICTICIO_SOLO_TEST');
        config()->set('services.mercadopago.qr_cajas', [2 => self::CAJA]);
    }

    private function ordenAcreditada(): array
    {
        return [
            'id' => self::ORDEN_ID,
            'type' => 'qr',
            'external_reference' => self::REFERENCIA,
            'currency' => 'ARS',
            'status' => 'processed',
            'total_amount' => '6400.50',
            'config' => ['qr' => ['mode' => 'static', 'external_pos_id' => self::CAJA]],
            'transactions' => ['payments' => [[
                'id' => 'PAY_FICTICIO_123',
                'amount' => '6400.50',
                'status' => 'processed',
                'status_detail' => 'accredited',
            ]]],
        ];
    }

    public function test_error_http_503_no_se_interpreta_como_pago_acreditado(): void
    {
        Http::fake(['api.mercadopago.com/*' => Http::response(['message' => 'Servicio no disponible'], 503)]);

        try {
            app(MercadoPagoQrEstatico::class)->consultarOrden(self::ORDEN_ID);
            $this->fail('La consulta HTTP 503 no debe devolverse como orden válida.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_caida_de_conexion_propaga_error_sin_inventar_acreditacion(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Conexión simulada interrumpida');
        });

        $this->expectException(ConnectionException::class);
        app(MercadoPagoQrEstatico::class)->consultarOrden(self::ORDEN_ID);
    }

    public function test_una_consulta_puede_fallar_y_la_siguiente_recuperarse(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'Error temporal'], 503)
            ->push($this->ordenAcreditada(), 200);

        $servicio = app(MercadoPagoQrEstatico::class);
        try {
            $servicio->consultarOrden(self::ORDEN_ID);
            $this->fail('La primera solicitud debe fallar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
        }

        $orden = $servicio->consultarOrden(self::ORDEN_ID);
        $this->assertTrue($servicio->ordenAcreditada($orden, self::REFERENCIA, '6400.50', 2));
        Http::assertSentCount(2);
    }

    public function test_orden_vencida_no_es_pago_acreditado(): void
    {
        $orden = $this->ordenAcreditada();
        $orden['status'] = 'expired';
        Http::fake(['api.mercadopago.com/*' => Http::response($orden, 200)]);
        $servicio = app(MercadoPagoQrEstatico::class);
        $this->assertFalse($servicio->ordenAcreditada(
            $servicio->consultarOrden(self::ORDEN_ID), self::REFERENCIA, '6400.50', 2
        ));
    }

    public function test_respuesta_con_identificador_distinto_se_rechaza(): void
    {
        $orden = $this->ordenAcreditada();
        $orden['id'] = 'ORDDISTINTA123';
        Http::fake(['api.mercadopago.com/*' => Http::response($orden, 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Respuesta de orden QR inválida');
        app(MercadoPagoQrEstatico::class)->consultarOrden(self::ORDEN_ID);
    }

    public function test_pago_con_referencia_o_importe_distinto_no_se_acredita(): void
    {
        $servicio = app(MercadoPagoQrEstatico::class);
        $orden = $this->ordenAcreditada();
        $this->assertFalse($servicio->ordenAcreditada($orden, 'referencia-distinta', '6400.50', 2));
        $this->assertFalse($servicio->ordenAcreditada($orden, self::REFERENCIA, '6400.51', 2));
    }

    public function test_creacion_fallida_no_devuelve_una_orden_falsa(): void
    {
        Http::fake(['api.mercadopago.com/*' => Http::response(['message' => 'No disponible'], 503)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 503');
        app(MercadoPagoQrEstatico::class)->crearOrden(self::REFERENCIA, 2, '6400.50', self::REFERENCIA);
    }
}
