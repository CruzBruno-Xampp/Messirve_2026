<?php

namespace Tests\Unit;

use App\Services\MercadoPagoQrEstatico;
use Tests\TestCase;

/**
 * Solo lectura: nunca crea órdenes, no consulta API, no migra ni escribe a la BD.
 * Estas pruebas NO sustituyen una prueba real de carreras entre procesos.
 */
class MercadoPagoQrSeguridadTest extends TestCase
{
    private MercadoPagoQrEstatico $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.mercadopago.qr_cajas', [2 => 'MESSIRVEALMCAJA1']);
        $this->servicio = new MercadoPagoQrEstatico();
    }

    private function ordenValida(): array
    {
        return [
            'id' => 'ORDTEST1234',
            'type' => 'qr',
            'external_reference' => 'ref-de-prueba',
            'currency' => 'ARS',
            'total_amount' => '6400.50',
            'status' => 'processed',
            'config' => ['qr' => ['mode' => 'static', 'external_pos_id' => 'MESSIRVEALMCAJA1']],
            'transactions' => ['payments' => [[
                'id' => 'PAGO_FICTICIO',
                'status' => 'processed',
                'status_detail' => 'accredited',
                'amount' => '6400.50',
            ]]],
        ];
    }

    public function test_acepta_orden_correctamente_acreditada(): void
    {
        $this->assertTrue($this->servicio->ordenAcreditada($this->ordenValida(), 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_orden_con_referencia_distinta(): void
    {
        $orden = $this->ordenValida();
        $orden['external_reference'] = 'otra-referencia';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_monto_de_orden_distinto(): void
    {
        $orden = $this->ordenValida();
        $orden['total_amount'] = '6401.50';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_monto_del_pago_distinto(): void
    {
        $orden = $this->ordenValida();
        $orden['transactions']['payments'][0]['amount'] = '6000.50';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_moneda_que_no_sea_ars(): void
    {
        $orden = $this->ordenValida();
        $orden['currency'] = 'USD';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_caja_distinta(): void
    {
        $orden = $this->ordenValida();
        $orden['config']['qr']['external_pos_id'] = 'OTRACAJA';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_modo_dinamico(): void
    {
        $orden = $this->ordenValida();
        $orden['config']['qr']['mode'] = 'dynamic';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_orden_no_procesada(): void
    {
        $orden = $this->ordenValida();
        $orden['status'] = 'created';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_pago_no_acreditado(): void
    {
        $orden = $this->ordenValida();
        $orden['transactions']['payments'][0]['status_detail'] = 'pending';
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_dos_pagos_para_una_orden(): void
    {
        $orden = $this->ordenValida();
        $orden['transactions']['payments'][] = $orden['transactions']['payments'][0];
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_rechaza_orden_sin_pagos(): void
    {
        $orden = $this->ordenValida();
        unset($orden['transactions']);
        $this->assertFalse($this->servicio->ordenAcreditada($orden, 'ref-de-prueba', '6400.50', 2));
    }

    public function test_confirmador_contiene_bloqueo_transaccional_y_control_idventa(): void
    {
        $codigo = file_get_contents(app_path('Services/ConfirmadorVentaLocal.php'));
        $this->assertIsString($codigo);
        $this->assertStringContainsString('DB::transaction(', $codigo);
        $this->assertStringContainsString('->lockForUpdate()', $codigo);
        $this->assertStringContainsString('$intentoBloqueado->idventa !== null', $codigo);
        // Verificación estructural; no afirma que se probó concurrencia real.
    }

    public function test_conciliador_incluye_recuperacion_de_pago_verificado(): void
    {
        $codigo = file_get_contents(app_path('Console/Commands/ConciliarOrdenesQr.php'));
        $this->assertIsString($codigo);
        $this->assertStringContainsString('APROBADO_PENDIENTE_VENTA', $codigo);
        $this->assertStringContainsString('->lockForUpdate()', $codigo);
        $this->assertStringContainsString('--simular', $codigo);
    }
}
