<?php

namespace App\Services;

use App\Models\IntentoPagoMp;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;

class MercadoPagoPreferencias
{
    public function crear(IntentoPagoMp $intento): string
    {
        if ($intento->mp_preference_id) {
            throw new RuntimeException(
                'Este intento ya tiene una preferencia de pago.'
            );
        }

        $importe = (float) $intento->importe;

        if ($importe <= 0) {
            throw new RuntimeException(
                'El importe del cobro debe ser mayor a cero.'
            );
        }

        MercadoPagoConfig::setAccessToken(
            config('services.mercadopago.access_token')
        );

        $cliente = new PreferenceClient();

        $preferencia = $cliente->create([
            'items' => [
                [
                    'id' => 'MESSIRVE-LOCAL',
                    'title' => 'Compra en Pinturería Messirve',
                    'quantity' => 1,
                    'currency_id' => 'ARS',
                    'unit_price' => $importe,
                ],
            ],
            'external_reference' => $intento->referencia,
        ]);

        if (empty($preferencia->id)
            || empty($preferencia->init_point)) {
            throw new RuntimeException(
                'Mercado Pago no devolvió una preferencia válida.'
            );
        }

        $intento->mp_preference_id = $preferencia->id;
        $intento->estado = 'PENDIENTE';
        $intento->save();

        return $preferencia->init_point;
    }
}
