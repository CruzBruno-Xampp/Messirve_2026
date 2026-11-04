<?php

namespace App\Http\Controllers;

use App\Models\IntentoPagoMp;
use App\Services\ValidadorFirmaWebhookMp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook QR: el aviso autenticado solo dispara la conciliacion de una orden.
 * Nunca se acredita un pago a partir del cuerpo del Webhook.
 */
class MercadoPagoQrWebhookController extends Controller
{
    public function recibir(Request $request, ValidadorFirmaWebhookMp $validador): JsonResponse
    {
        $secreto = (string) config('services.mercadopago.webhook_secret', '');
        $parametros = $request->query();

        // PHP normaliza el parametro URL data.id a data_id.
        // Solo usar el identificador de la URL para validar el HMAC.
        $idOrden = $parametros['data_id']
            ?? $parametros['data.id']
            ?? data_get($parametros, 'data.id');
        $tipo = $request->query('type');
        $firma = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');

        if ($secreto === '') {
            return response()->json(['message' => 'Webhook no configurado'], 503);
        }

        if (!is_string($idOrden) || $tipo !== 'order') {
            return response()->json(['message' => 'Notificacion no aplicable'], 400);
        }

        // El SDK oficial valida la firma; una solicitud no autenticada se rechaza.
        if (!$validador->validar($idOrden, $firma, $requestId, $secreto)) {
            return response()->json(['message' => 'Firma invalida'], 401);
        }

        $cuerpo = $request->json()->all();
        // El simulador puede mostrar el objeto data serializado como texto.
        // Verificamos la consistencia si es un objeto o JSON decodificable.
        $datosCuerpo = $cuerpo['data'] ?? null;
        if (is_string($datosCuerpo)) {
            $decodificado = json_decode($datosCuerpo, true);
            $datosCuerpo = is_array($decodificado) ? $decodificado : null;
        }
        if (is_array($datosCuerpo) && isset($datosCuerpo['id'])
            && (string) $datosCuerpo['id'] !== $idOrden) {
            return response()->json(['message' => 'Identificador inconsistente'], 400);
        }
        if (isset($cuerpo['type']) && $cuerpo['type'] !== 'order') {
            return response()->json(['message' => 'Tipo inconsistente'], 400);
        }

        $intento = IntentoPagoMp::query()->where('mp_order_id', $idOrden)
            ->first(['id', 'estado', 'idventa']);

        if ($intento && $intento->idventa === null
            && in_array($intento->estado, ['PENDIENTE', 'APROBADO_PENDIENTE_VENTA'], true)) {
            // La consulta a MP ocurre despues de responder y no confia en el payload.
            // Si el proceso web muere, queda el conciliador periodico de respaldo.
            app()->terminating(function () use ($idOrden, $intento): void {
                try {
                    $resultado = Artisan::call('messirve:conciliar-qr', [
                        '--order-id' => $idOrden,
                        '--limite' => 1,
                    ]);
                    if ($resultado !== 0) {
                        Log::warning('Webhook QR: conciliacion inmediata fallida; usar programador', [
                            'intento_id' => $intento->id,
                            'codigo_salida' => $resultado,
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::error('Webhook QR: no se pudo conciliar', [
                        'intento_id' => $intento->id,
                        'tipo_error' => get_class($e),
                    ]);
                }
            });
        }

        return response()->json(['recibido' => true], 200);
    }
}
