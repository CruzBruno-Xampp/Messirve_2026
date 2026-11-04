<?php

namespace Tests\Unit;

use App\Services\ValidadorFirmaWebhookMp;
use PHPUnit\Framework\TestCase;

final class MercadoPagoWebhookFirmaSdkTest extends TestCase
{
    private function cabecera(string $id, string $requestId, string $secret, string $timestamp): string
    {
        // Mismo manifest que usa el SDK oficial: conservar mayusculas del ID.
        $manifest = 'id:' . $id . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
        return 'ts=' . $timestamp . ',v1=' . hash_hmac('sha256', $manifest, $secret);
    }

    public function test_acepta_orden_firmada_con_id_original(): void
    {
        $id = 'ORDTST01ABCDEF1234';
        $requestId = 'test-request-456';
        $secret = 'clave-ficticia-para-tests';
        $firma = $this->cabecera($id, $requestId, $secret, (string) time());
        self::assertTrue((new ValidadorFirmaWebhookMp())->validar($id, $firma, $requestId, $secret));
    }

    public function test_rechaza_firma_si_usa_id_convertido_a_minusculas(): void
    {
        $id = 'ORDTST01ABCDEF1234';
        $requestId = 'test-request-456';
        $secret = 'clave-ficticia-para-tests';
        $firma = $this->cabecera(strtolower($id), $requestId, $secret, (string) time());
        self::assertFalse((new ValidadorFirmaWebhookMp())->validar($id, $firma, $requestId, $secret));
    }

    public function test_rechaza_firma_adulterada(): void
    {
        $id = 'ORDTST01ABCDEF1234';
        $requestId = 'test-request-456';
        $secret = 'clave-ficticia-para-tests';
        $firma = 'ts=' . time() . ',v1=' . str_repeat('0', 64);
        self::assertFalse((new ValidadorFirmaWebhookMp())->validar($id, $firma, $requestId, $secret));
    }

    public function test_rechaza_notificacion_antigua(): void
    {
        $id = 'ORDTST01ABCDEF1234';
        $requestId = 'test-request-456';
        $secret = 'clave-ficticia-para-tests';
        $firma = $this->cabecera($id, $requestId, $secret, (string) (time() - 3600));
        self::assertFalse((new ValidadorFirmaWebhookMp())->validar($id, $firma, $requestId, $secret));
    }
}
