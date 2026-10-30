<?php
// Verificación de solo lectura: NO carga Laravel ni ejecuta migraciones.
$raiz = dirname(__DIR__);
$archivo = $raiz . DIRECTORY_SEPARATOR . 'phpunit.qr.xml';
if (!is_file($archivo)) {
    fwrite(STDERR, "FALTA phpunit.qr.xml\n"); exit(1);
}
$contenido = file_get_contents($archivo);
$requeridos = [
    'name="APP_ENV" value="testing" force="true"',
    'name="DB_CONNECTION" value="sqlite" force="true"',
    'name="DB_DATABASE" value=":memory:" force="true"',
];
foreach ($requeridos as $valor) {
    if (strpos($contenido, $valor) === false) {
        fwrite(STDERR, "BLOQUEADO: falta aislamiento en phpunit.qr.xml: {$valor}\n"); exit(1);
    }
}
if (!is_file($raiz . '/tests/Unit/MercadoPagoQrSeguridadTest.php')) {
    fwrite(STDERR, "BLOQUEADO: faltan los tests QR.\n"); exit(1);
}
echo "OK: archivo PHPUnit QR separado y DB configurada en sqlite :memory:.\n";
echo "Este paquete NO crea ni modifica bases de datos.\n";
