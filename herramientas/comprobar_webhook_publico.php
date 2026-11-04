<?php
/**
 * Prueba de extremo a extremo de Webhook QR por un tunel temporal Cloudflare.
 * No usa la API de Mercado Pago; no genera ni modifica pagos ni ventas.
 * Requiere el entorno local de Messirve y un host *.trycloudflare.com.
 */
$projectRoot = dirname(__DIR__);
if (!is_file($projectRoot . '/vendor/autoload.php') || !is_file($projectRoot . '/bootstrap/app.php')) {
    fwrite(STDERR, "ERROR: copia este archivo a herramientas/ dentro del proyecto Messirve.\n");
    exit(1);
}
if ($argc !== 2) {
    fwrite(STDERR, "Uso: php herramientas/comprobar_webhook_publico.php https://TU-SUBDOMINIO.trycloudflare.com\n");
    exit(1);
}
$base = rtrim(trim($argv[1]), '/');
$parts = parse_url($base);
$host = strtolower((string) ($parts['host'] ?? ''));
if (($parts['scheme'] ?? '') !== 'https'
    || !preg_match('/^[a-z0-9-]+\.trycloudflare\.com$/', $host)
    || isset($parts['user'], $parts['pass'])
    || isset($parts['query']) || isset($parts['fragment'])
    || !in_array(($parts['path'] ?? ''), ['', '/'], true)
    || isset($parts['port'])) {
    fwrite(STDERR, "ERROR: utiliza exclusivamente la URL base HTTPS de tu tunel *.trycloudflare.com, sin ruta ni parametros.\n");
    exit(1);
}
require $projectRoot . '/vendor/autoload.php';
$app = require $projectRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$secret = (string) config('services.mercadopago.webhook_secret', '');
if ($secret === '') {
    fwrite(STDERR, "ERROR: el secreto del webhook no esta configurado en este entorno.\n");
    exit(1);
}
if (!function_exists('curl_init')) {
    fwrite(STDERR, "ERROR: falta la extension PHP cURL en tu PHP de Laragon.\n");
    exit(1);
}
$orderId = 'ORDTEST' . strtoupper(bin2hex(random_bytes(12)));
$requestId = bin2hex(random_bytes(16));
$timestamp = (string) time();
$manifest = 'id:' . $orderId . ';request-id:' . $requestId . ';ts:' . $timestamp . ';';
$signature = hash_hmac('sha256', $manifest, $secret);
$endpoint = $base . '/webhooks/mercadopago/qr?data.id=' . rawurlencode($orderId) . '&type=order';
$payload = json_encode(['type' => 'order', 'action' => 'order.processed', 'data' => ['id' => $orderId]], JSON_THROW_ON_ERROR);

function sendWebhook(string $url, string $payload, string $requestId, string $signature): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Request-Id: ' . $requestId,
            'X-Signature: ' . $signature,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADER => true,
    ]);
    $response = curl_exec($c);
    $error = curl_error($c);
    $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
    $headerLength = (int) curl_getinfo($c, CURLINFO_HEADER_SIZE);
    $headers = is_string($response) ? substr($response, 0, $headerLength) : '';
    curl_close($c);
    return [$code, (bool) preg_match('/^X-Messirve-Webhook-Revision\s*:/mi', $headers), $error];
}

[$validCode, $validMarker, $validError] = sendWebhook($endpoint, $payload, $requestId, 'ts=' . $timestamp . ',v1=' . $signature);
[$badCode, $badMarker, $badError] = sendWebhook($endpoint, $payload, $requestId, 'ts=' . $timestamp . ',v1=' . str_repeat('0', 64));

echo "Prueba WEBHOOK QR por Cloudflare (datos ficticios; sin cobros):\n";
echo '  Firma valida: HTTP ' . ($validCode ?: 'SIN_RESPUESTA') . '; controlador actualizado: ' . ($validMarker ? 'SI' : 'NO') . "\n";
echo '  Firma adulterada: HTTP ' . ($badCode ?: 'SIN_RESPUESTA') . '; controlador actualizado: ' . ($badMarker ? 'SI' : 'NO') . "\n";
if ($validError || $badError) {
    echo '  Error de transporte: ' . ($validError ?: $badError) . "\n";
}
if ($validCode === 200 && $badCode === 401 && $validMarker && $badMarker) {
    echo "APROBADO: la URL publica conserva parametros y cabeceras de firma hasta Laravel.\n";
    exit(0);
}
echo "NO APROBADO: el tunel/puerta/Laravel no coincide con la prueba local. No cambies claves ni repitas pagos.\n";
exit(1);
