<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Prueba de bloqueo InnoDB en una fila ficticia. NO confirma ventas ni cobros.
 * Ejecutar: php artisan --env=qr-testing messirve:probar-bloqueo-qr
 */
class ProbarBloqueoQr extends Command
{
    protected $signature = 'messirve:probar-bloqueo-qr {--worker= : Uso interno: id del intento} {--inicio=0 : Uso interno: instante de comienzo en ms}';
    protected $description = 'Comprueba bloqueo de fila QR con dos procesos PHP en messirve_test';

    public function handle(): int
    {
        try {
            $this->exigirBaseAislada();
        } catch (Throwable $e) {
            $this->error('BLOQUEADO: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('worker') !== null) {
            return $this->ejecutarTrabajador((int) $this->option('worker'), (int) $this->option('inicio'));
        }

        $referencia = (string) Str::uuid();
        $id = null;
        $procesos = [];
        try {
            $id = DB::table('intentos_pago_mp')->insertGetId([
                'referencia' => $referencia,
                'idusuario' => 1,
                'idsucursal' => 2,
                'idventa' => null,
                'datos_venta' => json_encode(['prueba' => 'bloqueo_sin_pago'], JSON_THROW_ON_ERROR),
                'importe' => '1.00',
                'estado' => 'PENDIENTE',
                'mp_order_id' => null,
                'mp_payment_id' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            $inicio = (int) floor(microtime(true) * 1000) + 1100;
            $comando = base_path('artisan');
            for ($i = 0; $i < 2; $i++) {
                $proceso = new Process([
                    PHP_BINARY, $comando, '--env=qr-testing', 'messirve:probar-bloqueo-qr',
                    '--worker=' . $id, '--inicio=' . $inicio,
                ], base_path());
                $proceso->setTimeout(30);
                $proceso->start();
                $procesos[] = $proceso;
            }
            $ganadores = 0;
            $repetidos = 0;
            foreach ($procesos as $i => $proceso) {
                $proceso->wait();
                $salida = trim($proceso->getOutput());
                $this->line('Proceso ' . ($i + 1) . ': ' . ($salida ?: trim($proceso->getErrorOutput())));
                if (!$proceso->isSuccessful()) {
                    throw new \RuntimeException('Falló el proceso ' . ($i + 1));
                }
                $ganadores += (int) str_contains($salida, 'GANADOR_UNICO');
                $repetidos += (int) str_contains($salida, 'YA_PROCESADO');
            }
            $fila = DB::table('intentos_pago_mp')->where('id', $id)->where('referencia', $referencia)->first();
            $correcto = $ganadores === 1 && $repetidos === 1 && $fila && $fila->estado === 'QR_PRUEBA_BLOQUEO' && $fila->idventa === null;
            if ($correcto) {
                $this->info('APROBADO: 1 proceso ganó, el otro reconoció la operación. No se creó venta ni se descontó stock.');
                $this->warn('Alcance: prueba REAL de bloqueo de fila MySQL; NO es aún una prueba completa del confirmador de ventas.');
                return self::SUCCESS;
            }
            $this->error('NO APROBADO: resultados diferentes a los esperados.');
            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('ERROR: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            foreach ($procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop(2);
                }
            }
            if ($id !== null) {
                DB::table('intentos_pago_mp')
                    ->where('id', $id)
                    ->where('referencia', $referencia)
                    ->whereNull('idventa')
                    ->whereNull('mp_order_id')
                    ->delete();
            }
        }
    }

    private function ejecutarTrabajador(int $id, int $inicio): int
    {
        if ($id < 1 || $inicio < 1) {
            $this->error('Parámetros de trabajador inválidos.');
            return self::FAILURE;
        }
        $espera = $inicio - (int) floor(microtime(true) * 1000);
        if ($espera > 0 && $espera < 15000) {
            usleep($espera * 1000);
        }
        try {
            $resultado = DB::transaction(function () use ($id) {
                $fila = DB::table('intentos_pago_mp')->where('id', $id)->lockForUpdate()->first();
                if (!$fila || $fila->mp_order_id !== null || $fila->idventa !== null) {
                    throw new \RuntimeException('La fila no corresponde a una prueba aislada.');
                }
                if ($fila->estado !== 'PENDIENTE') {
                    return 'YA_PROCESADO';
                }
                usleep(900000); // Sostiene el bloqueo para que el otro proceso espere.
                DB::table('intentos_pago_mp')->where('id', $id)->update([
                    'estado' => 'QR_PRUEBA_BLOQUEO', 'updated_at' => now('UTC'),
                ]);
                return 'GANADOR_UNICO';
            }, 3);
            $this->line($resultado);
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function exigirBaseAislada(): void
    {
        if (config('app.env') !== 'testing' || config('database.default') !== 'mysql') {
            throw new \RuntimeException('Se exige APP_ENV=testing y DB_CONNECTION=mysql.');
        }
        $declarada = (string) config('database.connections.mysql.database');
        $real = (string) (DB::selectOne('SELECT DATABASE() AS nombre')->nombre ?? '');
        if ($declarada !== 'messirve_test' || $real !== 'messirve_test') {
            throw new \RuntimeException('La conexión no apunta exclusivamente a messirve_test.');
        }
    }
}
