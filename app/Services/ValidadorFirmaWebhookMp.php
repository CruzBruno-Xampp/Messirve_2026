<?php

namespace App\Services;

use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;

final class ValidadorFirmaWebhookMp
{
    public function validar(string $dataId, string $firma, string $requestId, string $secreto): bool
    {
        // Estos son requisitos propios del flujo de ORDENES QR de Messirve.
        // Nunca se valida la firma utilizando valores del body no autenticado.
        if ($secreto === '' || $firma === '' || $requestId === ''
            || !preg_match('/^ORD[A-Za-z0-9]{1,77}$/', $dataId)) {
            return false;
        }

        try {
            // Delegamos la construccion del manifest y la verificacion al SDK
            // oficial. MUY IMPORTANTE: NO cambiar dataId a minusculas.
            // El SDK 3.16.0 calcula el HMAC con el ID original, sin lowercasing.
            WebhookSignatureValidator::validate($firma, $requestId, $dataId, $secreto, 300);
            return true;
        } catch (InvalidWebhookSignatureException $e) {
            return false;
        }
    }
}
