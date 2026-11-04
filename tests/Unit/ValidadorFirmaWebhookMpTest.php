<?php

namespace Tests\Unit;

use App\Services\ValidadorFirmaWebhookMp;
use PHPUnit\Framework\TestCase;

class ValidadorFirmaWebhookMpTest extends TestCase
{
    private const ID = 'ORD01M28P44G5FG8RJPM579EH56FV';
    private const SECRET = 'secreto-ficticio-para-pruebas';

    private function firma(string $id, string $requestId, string $ts): string
    {
        // El SDK 3.16.0 conserva las mayúsculas del identificador.
        // Para comprobar antigüedad, interpreta ts como segundos Unix.
        $manifiesto = 'id:' . $id . ';request-id:' . $requestId . ';ts:' . $ts . ';';

        return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifiesto, self::SECRET);
    }

    public function test_acepta_firma_valida(): void
    {
        $ts = (string) time();
        $firma = $this->firma(self::ID, 'pedido-1', $ts);

        $this->assertTrue(
            (new ValidadorFirmaWebhookMp())->validar(self::ID, $firma, 'pedido-1', self::SECRET)
        );
    }

    public function test_rechaza_firma_alterada(): void
    {
        $ts = (string) time();
        $firma = $this->firma(self::ID, 'pedido-1', $ts);

        $this->assertFalse(
            (new ValidadorFirmaWebhookMp())->validar(self::ID, $firma, 'pedido-2', self::SECRET)
        );
    }

    public function test_rechaza_ausencia_de_firma(): void
    {
        $this->assertFalse(
            (new ValidadorFirmaWebhookMp())->validar(self::ID, '', 'pedido-1', self::SECRET)
        );
    }

    public function test_rechaza_id_que_no_es_orden(): void
    {
        $this->assertFalse(
            (new ValidadorFirmaWebhookMp())->validar('NOT-ORDER', $this->firma(self::ID, 'pedido-1', (string) time()), 'pedido-1', self::SECRET)
        );
    }
}
