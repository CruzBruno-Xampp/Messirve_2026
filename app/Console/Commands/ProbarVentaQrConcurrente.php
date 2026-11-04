<?php

namespace App\Console\Commands;

use App\Models\IntentoPagoMp;
use App\Models\User;
use App\Services\ConfirmadorVentaLocal;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/** Prueba INTEGRADA, en MySQL messirve_test exclusivamente; sin API externa. */
class ProbarVentaQrConcurrente extends Command
{
    protected $signature = 'messirve:probar-venta-qr-concurrente {--worker= : Interno} {--inicio=0 : Interno}';
    protected $description = 'Dos procesos confirman el mismo intento con el servicio real (solo messirve_test)';

    private function aislamiento(): void
    {
       if (!app()->environment(['testing', 'qr-testing']) || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'messirve_test'
            || DB::selectOne('SELECT DATABASE() AS db')->db !== 'messirve_test') {
            throw new \RuntimeException('Protección de aislamiento: se requiere testing + mysql + messirve_test.');
        }
    }

    public function handle(ConfirmadorVentaLocal $confirmador): int
    {
        try {
            $this->aislamiento();
            if ($this->option('worker') !== null) {
                return $this->trabajador($confirmador, (int) $this->option('worker'), (int) $this->option('inicio'));
            }
            return $this->coordinador();
        } catch (Throwable $e) {
            $this->error('NO APROBADO: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function trabajador(ConfirmadorVentaLocal $confirmador, int $id, int $inicio): int
    {
        if ($id < 1 || $inicio < 1) throw new \RuntimeException('Parámetros internos inválidos.');
        $esperar = $inicio - (int) round(microtime(true) * 1000);
        if ($esperar > 0 && $esperar <= 15000) usleep($esperar * 1000);
        $intento = IntentoPagoMp::findOrFail($id);
        if ($intento->estado !== 'APROBADO_PENDIENTE_VENTA') {
            $this->line($intento->idventa ? 'YA_CONFIRMADO' : 'OTRO_ESTADO');
            return self::SUCCESS;
        }
        $usuario = User::findOrFail($intento->idusuario);
        $datos = $intento->datos_venta;
        $request = Request::create('/', 'POST', $datos);
        $request->setUserResolver(fn ($guard = null) => $usuario);
        try {
            $respuesta = $confirmador->confirmar($request, $intento, $usuario);
            $this->line($respuesta->getStatusCode() === 201 ? 'VENTA_REGISTRADA' : 'RESPUESTA_INESPERADA');
            return $respuesta->getStatusCode() === 201 ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            // La segunda solicitud puede ser rechazada una vez confirmada la primera.
            $actual = IntentoPagoMp::find($id);
            if ($actual && $actual->idventa !== null && $actual->estado === 'CONFIRMADO') {
                $this->line('YA_CONFIRMADO');
                return self::SUCCESS;
            }
            $this->error('ERROR_TRABAJADOR: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function coordinador(): int
    {
        // No se alteran los registros preexistentes de messirve_test.
        $tag = 'QRTEST-' . strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 12));
        $ids = [];
        $procesos = [];
        $salidas = [];
        $ok = false;
        try {
            // Insertar solo registros ficticios necesarios, vinculados por las FK reales.
            $ids['rol'] = DB::table('roles')->insertGetId(['nombre' => $tag, 'estado' => 1]);
            $ids['sucursal'] = DB::table('sucursales')->insertGetId(['nombre' => $tag, 'tipo' => 'SUCURSAL', 'direccion' => 'Prueba aislada', 'estado' => 1]);
            $ids['usuario'] = DB::table('users')->insertGetId(['idrol' => $ids['rol'], 'idsucursal' => $ids['sucursal'], 'nombre' => 'Prueba', 'apellido' => 'QR', 'email' => strtolower($tag).'@example.invalid', 'telefono' => '000', 'password' => 'NO_LOGIN', 'estado' => 1]);
            $ids['linea'] = DB::table('lineas')->insertGetId(['nombre' => $tag, 'estado' => 1]);
            $ids['grupo'] = DB::table('grupos')->insertGetId(['idlinea' => $ids['linea'], 'nombre' => $tag, 'estado' => 1]);
            $ids['subgrupo'] = DB::table('subgrupos')->insertGetId(['idgrupo' => $ids['grupo'], 'nombre' => $tag, 'estado' => 1]);
            $ids['marca'] = DB::table('marcas')->insertGetId(['nombre' => $tag, 'estado' => 1]);
            $ids['iva'] = DB::table('alicuotas_iva')->insertGetId(['nombre' => $tag, 'porcentaje' => 21, 'estado' => 1]);
            // El confirmador exige específicamente idmetodopago=2 para un QR.
            if (DB::table('metodos_pago')->where('idmetodopago', 2)->exists()) {
                throw new \RuntimeException('En messirve_test ya existe el medio de pago 2; usá una base de pruebas limpia.');
            }
            DB::table('metodos_pago')->insert(['idmetodopago' => 2, 'nombre' => $tag, 'recargo' => 0, 'estado' => 1]);
            $ids['metodo'] = 2;
            $ids['producto'] = DB::table('productos')->insertGetId(['codigointerno' => $tag, 'descripcion' => 'Producto ficticio QR', 'idmarca' => $ids['marca'], 'idsubgrupo' => $ids['subgrupo'], 'idiva' => $ids['iva'], 'pvp' => 6400.50, 'estado' => 1]);
            $ids['stock'] = DB::table('stock')->insertGetId(['idproducto' => $ids['producto'], 'idsucursal' => $ids['sucursal'], 'stockactual' => 10, 'stockminimo' => 0, 'estado' => 1]);
            $ids['tipo'] = DB::table('tipos_movimiento')->insertGetId(['codigo' => 'VENTA_STOCK', 'nombre' => $tag, 'signo' => -1, 'estado' => 1]);
            $datos = ['idsucursal' => $ids['sucursal'], 'tipo_cliente' => 'consumidor_final', 'idcliente' => null, 'descuento_adicional' => 0, 'idmetodopago' => 2, 'productos' => [['idproducto' => $ids['producto'], 'cantidad' => 1]]];
            $ids['intento'] = DB::table('intentos_pago_mp')->insertGetId(['referencia' => (string) Str::uuid(), 'idusuario' => $ids['usuario'], 'idsucursal' => $ids['sucursal'], 'datos_venta' => json_encode($datos, JSON_THROW_ON_ERROR), 'importe' => 6400.50, 'estado' => 'APROBADO_PENDIENTE_VENTA', 'mp_payment_id' => $tag, 'pagado_at' => now('UTC'), 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            $inicio = (int) round(microtime(true) * 1000) + 1800;
            for ($i=0; $i<2; $i++) {
                $p = new Process([PHP_BINARY, base_path('artisan'), '--env=qr-testing', 'messirve:probar-venta-qr-concurrente', '--worker='.$ids['intento'], '--inicio='.$inicio], base_path());
                $p->setTimeout(35);
                $p->start();
                $procesos[] = $p;
            }
            foreach ($procesos as $i => $p) {
                $p->wait();
                $salidas[] = trim($p->getOutput());
                $this->line('Proceso '.($i+1).': '.trim($p->getOutput().' '.$p->getErrorOutput()));
                if (!$p->isSuccessful()) throw new \RuntimeException('Falló el proceso '.($i+1));
            }
            $intento = DB::table('intentos_pago_mp')->where('id', $ids['intento'])->first();
            $ventaIds = DB::table('ventas')->where('idusuario', $ids['usuario'])->pluck('idventa');
            $ventas = $ventaIds->count();
            $detalles = DB::table('detalle_venta')->whereIn('idventa', $ventaIds)->count();
            $movimientos = DB::table('movimientos')->whereIn('idventa', $ventaIds)->count();
            $salidasStock = DB::table('detalle_movimiento')->whereIn('idmovimiento', DB::table('movimientos')->whereIn('idventa', $ventaIds)->pluck('idmovimiento'))->sum('cantidad');
            $stock = DB::table('stock')->where('idstock', $ids['stock'])->value('stockactual');
            $this->line("Verificación: ventas={$ventas}; detalles={$detalles}; movimientos={$movimientos}; unidades descontadas={$salidasStock}; stock final={$stock}; estado={$intento->estado}");
            $ok = count(array_filter($salidas, fn ($s) => str_contains($s, 'VENTA_REGISTRADA'))) === 1
                && count(array_filter($salidas, fn ($s) => str_contains($s, 'YA_CONFIRMADO'))) === 1
                && $ventas === 1 && $detalles === 1 && $movimientos === 1
                && (int) $salidasStock === 1 && (int) $stock === 9
                && $intento->estado === 'CONFIRMADO' && (int) $intento->idventa === (int) $ventaIds->first();
            $this->{$ok ? 'info' : 'error'}($ok ? 'APROBADO: una sola venta y un solo descuento de stock.' : 'NO APROBADO: revisar resultados.');
            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            $this->error('ERROR DE PRUEBA: '.$e->getMessage());
            return self::FAILURE;
        } finally {
            foreach ($procesos as $p) if ($p->isRunning()) $p->stop(2);
            // Limpiar SOLO los IDs insertados por esta ejecución y únicamente en messirve_test.
            $this->aislamiento();
            if (isset($ids['intento'])) {
                $ventaIds = DB::table('ventas')->where('idusuario', $ids['usuario'])->pluck('idventa');
                $movIds = DB::table('movimientos')->whereIn('idventa', $ventaIds)->pluck('idmovimiento');
                DB::table('intentos_pago_mp')->where('id', $ids['intento'])->delete();
                DB::table('detalle_movimiento')->whereIn('idmovimiento', $movIds)->delete();
                DB::table('movimientos')->whereIn('idmovimiento', $movIds)->delete();
                DB::table('detalle_venta')->whereIn('idventa', $ventaIds)->delete();
                DB::table('ventas')->whereIn('idventa', $ventaIds)->delete();
            }
            foreach (['stock'=>'stock','producto'=>'productos','tipo'=>'tipos_movimiento','iva'=>'alicuotas_iva','marca'=>'marcas','subgrupo'=>'subgrupos','grupo'=>'grupos','linea'=>'lineas','usuario'=>'users','sucursal'=>'sucursales','rol'=>'roles'] as $key=>$tabla) {
                if (isset($ids[$key])) {
                    $primary = ['stock'=>'idstock','producto'=>'idproducto','tipo'=>'idtipomovimiento','iva'=>'idiva','marca'=>'idmarca','subgrupo'=>'idsubgrupo','grupo'=>'idgrupo','linea'=>'idlinea','usuario'=>'id','sucursal'=>'idsucursal','rol'=>'idrol'][$key];
                    DB::table($tabla)->where($primary, $ids[$key])->delete();
                }
            }
            // Solo eliminamos el método 2 si lo insertamos nosotros.
            if (isset($ids['metodo'])) DB::table('metodos_pago')->where('idmetodopago', 2)->where('nombre', $tag)->delete();
        }
    }
}
