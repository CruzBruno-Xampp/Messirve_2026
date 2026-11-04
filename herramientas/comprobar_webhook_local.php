<?php
/**
 * Prueba aislada y sin escrituras. Invoca la ruta HTTP local con una orden
 * ficticia no existente; autentica la firma con la misma clave configurada.
 * NUNCA imprime la clave, el header HMAC ni los IDs internos.
 */
$proyecto = dirname(__DIR__);
if (!file_exists($proyecto . '/vendor/autoload.php')) {
    fwrite(STDERR, "ERROR: ejecuta esta herramienta dentro de Messirve.\n");
    exit(1);
}
require $proyecto . '/vendor/autoload.php';
$app = require $proyecto . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$secreto = (string) config('services.mercadopago.webhook_secret', '');
if ($secreto === '') {
    fwrite(STDERR, "ERROR: falta MERCADOPAGO_WEBHOOK_SECRET en el entorno activo.\n");
    exit(1);
}
$base = 'http://127.0.0.1:8000'; // Deliberadamente NO usa un destino publico
$id = 'ORDTEST' . strtoupper(bin2hex(random_bytes(12)));
$requestId = bin2hex(random_bytes(16));
$ts = (string) time();
$manifiesto = 'id:' . $id . ';request-id:' . $requestId . ';ts:' . $ts . ';';
$v1 = hash_hmac('sha256', $manifiesto, $secreto);
$url = $base . '/webhooks/mercadopago/qr?data.id=' . rawurlencode($id) . '&type=order';
$cuerpo = json_encode(['type' => 'order', 'action'=>'order.processed', 'data' => ['id' => $id]], JSON_THROW_ON_ERROR);

function llamar(string $url, string $cuerpo, string $requestId, string $firma): array {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n"
            . "X-Request-Id: {$requestId}\r\nX-Signature: {$firma}\r\n",
        'content' => $cuerpo,
        'ignore_errors' => true,
        'timeout' => 8,
    ]]);
    $respuesta = @file_get_contents($url, false, $ctx);
    $cabeceras = $http_response_header ?? [];
    if ($respuesta === false && count($cabeceras) === 0) {
        return [0, 'SIN_RESPUESTA', false];
    }
    preg_match('/^HTTP\/\S+\s+(\d+)/', $cabeceras[0] ?? '', $m);
    $codigo = isset($m[1]) ? (int)$m[1] : 0;
    $marca = false;
    foreach ($cabeceras as $cabecera) {
        if (stripos($cabecera, 'X-Messirve-Webhook-Revision:') === 0) {
            $marca = true;
        }
    }
    return [$codigo, is_string($respuesta) ? $respuesta : '', $marca];
}

[$codigoValido, , $marcaValida] = llamar($url, $cuerpo, $requestId, "ts={$ts},v1={$v1}");
// Enviar firma corrupta para probar el rechazo del mismo endpoint.
[$codigoInvalido, , $marcaInvalida] = llamar($url, $cuerpo, $requestId, 'ts=' . $ts . ',v1=' . str_repeat('0', 64));

fwrite(STDOUT, "Prueba HTTP local de Webhook QR (sin API externa y sin cobros):\n");
fwrite(STDOUT, "  Firma valida: HTTP {$codigoValido}; controlador actualizado: " . ($marcaValida ? 'SI' : 'NO') . "\n");
fwrite(STDOUT, "  Firma invalida: HTTP {$codigoInvalido}; controlador actualizado: " . ($marcaInvalida ? 'SI' : 'NO') . "\n");
if ($codigoValido === 200 && $codigoInvalido === 401 && $marcaValida && $marcaInvalida) {
    fwrite(STDOUT, "APROBADO: Laravel verifica la firma y rechaza la adulterada.\n");
    exit(0);
}
fwrite(STDERR, "NO APROBADO: revisar los codigos y la marca de revision. No repetir pagos.\n");
exit(1);
