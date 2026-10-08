<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\DetalleMovimiento;
use App\Models\Movimiento;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\TipoMovimiento;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VentaController extends Controller
{
    private const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

    /**
     * Muestra el resumen y las últimas ventas registradas.
     */
    public function index(): View
    {
        $ahoraLocal = now(self::ZONA_HORARIA);
        $inicioHoyUtc = $ahoraLocal->copy()->startOfDay()->utc();
        $finHoyUtc = $ahoraLocal->copy()->endOfDay()->utc();

        $ventas = $this->consultaVentasPermitidas()
            ->with(['cliente', 'usuario', 'sucursal'])
            ->orderByDesc('fecha')
            ->orderByDesc('idventa')
            ->paginate(15);

        $ventasDeHoy = $this->consultaVentasPermitidas()
            ->whereBetween('fecha', [$inicioHoyUtc, $finHoyUtc])
            ->where('estado', '<>', 'ANULADA');

        $resumen = [
            'cantidadHoy' => (clone $ventasDeHoy)->count(),
            'totalHoy' => (float) (clone $ventasDeHoy)->sum('total'),
            'pendientes' => $this->consultaVentasPermitidas()
                ->where('estado', 'PENDIENTE')
                ->count(),
        ];

        $zonaHoraria = self::ZONA_HORARIA;

        return view('ventas.index', compact(
            'ventas',
            'resumen',
            'zonaHoraria'
        ));
    }

    /**
     * Muestra los datos generales y todos los productos de una venta.
     */
    public function mostrar(Venta $venta): View
    {
        $this->comprobarSucursalPermitida(
            (int) $venta->idsucursal,
            'consultar'
        );

        $venta->load([
            'cliente',
            'usuario',
            'sucursal',
            'detalles' => fn ($consulta) => $consulta
                ->with('producto')
                ->orderBy('iddetalleventa'),
        ]);

        $movimientoAnulacion = Movimiento::query()
            ->with(['tipo', 'usuario'])
            ->where('idventa', $venta->idventa)
            ->whereHas(
                'tipo',
                fn ($consulta) => $consulta->where(
                    'codigo',
                    'ANULACION_VENTA'
                )
            )
            ->orderByDesc('idmovimiento')
            ->first();

        $puedeAnular = $this->puedeUsuarioAnularVenta($venta);
        $zonaHoraria = self::ZONA_HORARIA;

        return view('ventas.detalle', compact(
            'venta',
            'movimientoAnulacion',
            'puedeAnular',
            'zonaHoraria'
        ));
    }

    /**
     * Anula una venta y reintegra todas sus unidades al stock de la sucursal.
     */
    public function anular(Request $request, Venta $venta): RedirectResponse
    {
        $request->merge([
            'motivo' => trim((string) $request->input('motivo')),
        ]);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:255'],
            'confirmar_anulacion' => ['accepted'],
        ], [
            'motivo.required' => 'Ingresá el motivo de la anulación.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
            'confirmar_anulacion.accepted' => 'Confirmá que querés anular la venta.',
        ]);

        $numeroVenta = 'V-' . str_pad(
            (string) $venta->idventa,
            6,
            '0',
            STR_PAD_LEFT
        );

        DB::transaction(function () use ($venta, $datos, $numeroVenta) {
            /** @var Venta $ventaBloqueada */
            $ventaBloqueada = Venta::query()
                ->whereKey($venta->idventa)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validarAnulacionPermitida($ventaBloqueada);

            $tipoAnulacion = TipoMovimiento::query()
                ->where('codigo', 'ANULACION_VENTA')
                ->where('estado', 1)
                ->first();

            if (!$tipoAnulacion) {
                throw ValidationException::withMessages([
                    'venta' => 'No está configurado el movimiento ANULACION_VENTA.',
                ]);
            }

            $detallesVenta = $ventaBloqueada->detalles()
                ->orderBy('iddetalleventa')
                ->get();

            if ($detallesVenta->isEmpty()) {
                throw ValidationException::withMessages([
                    'venta' => 'La venta no posee productos para reintegrar.',
                ]);
            }

            // Recuperamos cómo se repartió originalmente la salida de stock.
            // Esto permite devolver cada unidad a la sucursal de la que salió.
            $movimientosVenta = Movimiento::query()
                ->select('movimientos.*')
                ->join(
                    'tipos_movimiento',
                    'tipos_movimiento.idtipomovimiento',
                    '=',
                    'movimientos.idtipomovimiento'
                )
                ->where('movimientos.idventa', $ventaBloqueada->idventa)
                ->where('tipos_movimiento.codigo', 'VENTA_STOCK')
                ->where('movimientos.estado', 1)
                ->orderBy('movimientos.idmovimiento')
                ->lockForUpdate()
                ->get();

            $reposiciones = [];

            if ($movimientosVenta->isNotEmpty()) {
                $detallesMovimiento = DetalleMovimiento::query()
                    ->whereIn('idmovimiento', $movimientosVenta->pluck('idmovimiento'))
                    ->get()
                    ->groupBy(fn ($detalle) => (int) $detalle->idmovimiento);

                foreach ($movimientosVenta as $movimientoVenta) {
                    $idSucursalDestino = (int) (
                        $movimientoVenta->idsucursalorigen
                        ?: $ventaBloqueada->idsucursal
                    );

                    foreach ($detallesMovimiento->get(
                        (int) $movimientoVenta->idmovimiento,
                        collect()
                    ) as $detalleMovimiento) {
                        $idProducto = (int) $detalleMovimiento->idproducto;
                        $reposiciones[$idSucursalDestino] ??= [];
                        $reposiciones[$idSucursalDestino][$idProducto] =
                            ($reposiciones[$idSucursalDestino][$idProducto] ?? 0)
                            + (int) $detalleMovimiento->cantidad;
                    }
                }
            } else {
                // Compatibilidad con ventas antiguas: antes todo se descontaba
                // únicamente de la sucursal que registró la venta.
                $idSucursalDestino = (int) $ventaBloqueada->idsucursal;
                $reposiciones[$idSucursalDestino] = $detallesVenta
                    ->groupBy(fn ($detalle) => (int) $detalle->idproducto)
                    ->map(fn ($detalles) => (int) $detalles->sum('cantidad'))
                    ->all();
            }

            $idsSucursales = collect(array_keys($reposiciones));
            $idsProductos = collect($reposiciones)
                ->flatMap(fn ($productos) => array_keys($productos))
                ->unique()
                ->values();

            $stocks = Stock::query()
                ->whereIn('idsucursal', $idsSucursales)
                ->whereIn('idproducto', $idsProductos)
                ->where('estado', 1)
                ->orderBy('idstock')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Stock $stock) =>
                    (int) $stock->idsucursal . ':' . (int) $stock->idproducto
                );

            $usuario = auth('web')->user();
            $fechaAnulacion = now('UTC');

            foreach ($reposiciones as $idSucursalDestino => $productos) {
                $movimiento = Movimiento::create([
                    'idtipomovimiento' => $tipoAnulacion->idtipomovimiento,
                    'idusuario' => $usuario->id,
                    'idsucursalorigen' => null,
                    'idsucursaldestino' => (int) $idSucursalDestino,
                    'idventa' => $ventaBloqueada->idventa,
                    'fecha' => $fechaAnulacion,
                    'referencia' => sprintf(
                        'ANU-%06d-S%03d',
                        $ventaBloqueada->idventa,
                        (int) $idSucursalDestino
                    ),
                    'observacion' => $datos['motivo'],
                    'estado' => 1,
                ]);

                foreach ($productos as $idProducto => $cantidad) {
                    $clave = (int) $idSucursalDestino . ':' . (int) $idProducto;
                    /** @var Stock|null $stock */
                    $stock = $stocks->get($clave);

                    if (!$stock) {
                        throw ValidationException::withMessages([
                            'venta' => 'No se encontró el stock original de uno de los productos para reintegrarlo.',
                        ]);
                    }

                    $stock->stockactual = (int) $stock->stockactual + (int) $cantidad;
                    $stock->save();

                    DetalleMovimiento::create([
                        'idmovimiento' => $movimiento->idmovimiento,
                        'idproducto' => (int) $idProducto,
                        'cantidad' => (int) $cantidad,
                        'costounitario' => null,
                        'subtotal' => null,
                    ]);
                }
            }

            $ventaBloqueada->estado = 'ANULADA';
            $ventaBloqueada->save();
        }, 3);

        return redirect()
            ->route('ventas.detalle', $venta->idventa)
            ->with(
                'success',
                "La venta {$numeroVenta} fue anulada y cada unidad volvió a su sucursal de origen."
            );
    }

    /**
     * Muestra el armado de una nueva venta.
     */
    public function crear(): View
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol');

        $esDueno = $usuario->rol
            && $usuario->rol->nombre === 'DUEÑO';

        $puedeElegirSucursal = $esDueno
            && $usuario->idsucursal === null;

        abort_if(
            !$puedeElegirSucursal && $usuario->idsucursal === null,
            403,
            'Tu cuenta no tiene una sucursal asignada para registrar ventas.'
        );

        $sucursales = Sucursal::query()
            ->where('estado', 1)
            ->when(
                !$puedeElegirSucursal,
                fn ($consulta) => $consulta->where(
                    'idsucursal',
                    $usuario->idsucursal
                )
            )
            ->orderBy('nombre')
            ->get(['idsucursal', 'nombre', 'tipo']);

        abort_if(
            $sucursales->isEmpty(),
            403,
            'Tu cuenta no tiene una sucursal activa para registrar ventas.'
        );

        $idSucursalInicial = $puedeElegirSucursal
            ? (int) $sucursales->first()->idsucursal
            : (int) $usuario->idsucursal;

        return view('ventas.crear', compact(
            'sucursales',
            'idSucursalInicial',
            'puedeElegirSucursal'
        ));
    }

    /**
     * Búsqueda manual de productos para el modal F3.
     *
     * Esta consulta solo se ejecuta cuando el usuario necesita buscar por
     * nombre u otros datos. Primero limita los productos encontrados y recién
     * después consulta el stock de esos productos en todas las sucursales.
     */
    public function buscarProductos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'buscar' => ['required', 'string', 'min:2', 'max:100'],
            'idsucursal' => [
                'required',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
        ], [
            'buscar.required' => 'Ingresá un dato para buscar el producto.',
            'buscar.min' => 'Ingresá al menos dos caracteres para buscar.',
            'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',
        ]);

        $idSucursalVenta = (int) $datos['idsucursal'];
        $this->comprobarSucursalPermitida($idSucursalVenta);

        $busqueda = trim((string) $datos['buscar']);
        $terminoLike = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $busqueda
        );

        $idsProductos = DB::table('productos')
            ->leftJoin('marcas', 'marcas.idmarca', '=', 'productos.idmarca')
            ->where('productos.estado', 1)
            ->where('productos.pvp', '>', 0)
            ->whereExists(function ($consulta) {
                $consulta
                    ->select(DB::raw(1))
                    ->from('stock')
                    ->join(
                        'sucursales',
                        'sucursales.idsucursal',
                        '=',
                        'stock.idsucursal'
                    )
                    ->whereColumn('stock.idproducto', 'productos.idproducto')
                    ->where('stock.estado', 1)
                    ->where('stock.stockactual', '>', 0)
                    ->where('sucursales.estado', 1);
            })
            ->where(function ($consulta) use ($terminoLike, $busqueda) {
                $consulta
                    ->where('productos.descripcion', 'like', "%{$terminoLike}%")
                    ->orWhere('productos.codigointerno', 'like', "%{$terminoLike}%")
                    ->orWhere('productos.codigofabricante', 'like', "%{$terminoLike}%")
                    ->orWhere('marcas.nombre', 'like', "%{$terminoLike}%")
                    ->orWhere('productos.codigobarra', '=', $busqueda);
            })
            ->orderByRaw(
                'CASE WHEN productos.codigobarra = ? THEN 0 '
                . 'WHEN productos.codigointerno = ? THEN 1 '
                . 'WHEN productos.descripcion LIKE ? THEN 2 ELSE 3 END',
                [$busqueda, $busqueda, $busqueda . '%']
            )
            ->orderBy('productos.descripcion')
            ->limit(20)
            ->pluck('productos.idproducto');

        return response()->json([
            'productos' => $this->productosDisponiblesPorIds(
                $idsProductos,
                $idSucursalVenta
            ),
        ]);
    }

    /**
     * Busca un producto por código de barras conservando el valor como texto.
     *
     * Primero intenta exactamente lo que leyó el escáner. Solo si ese valor no
     * existe en productos se prueban equivalencias seguras entre UPC-A y EAN-13.
     * De esta manera nunca se eliminan ceros de forma arbitraria ni se modifica
     * un CODE-128, CODE-39 u otro formato donde los ceros sean parte del código.
     */
    public function buscarProductoPorCodigo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:100'],
            'formato' => ['nullable', 'string', 'max:40'],
            'idsucursal' => [
                'required',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
        ], [
            'codigo.required' => 'Ingresá o escaneá un código de barras.',
            'codigo.max' => 'El código de barras no puede superar los 100 caracteres.',
            'formato.max' => 'El formato del código detectado no es válido.',
        ]);

        $idSucursalVenta = (int) $datos['idsucursal'];
        $this->comprobarSucursalPermitida($idSucursalVenta);

        // Importante: el código se mantiene siempre como string. No se castea a
        // int ni se aplica ltrim(), por lo que los ceros iniciales se conservan.
        $codigo = trim((string) $datos['codigo']);
        $formato = $this->normalizarFormatoCodigoBarras(
            (string) ($datos['formato'] ?? '')
        );

        // Si el valor exacto existe en productos, tiene prioridad absoluta, aun
        // cuando esté inactivo o sin stock. Así nunca "saltamos" por error a un
        // código equivalente que pudiera pertenecer a otro producto.
        $idProductoExacto = DB::table('productos')
            ->where('codigobarra', '=', $codigo)
            ->value('idproducto');

        $codigoEncontrado = null;
        $equivalenciaAplicada = false;

        if ($idProductoExacto !== null) {
            $idsProductos = DB::table('productos')
                ->where('idproducto', (int) $idProductoExacto)
                ->where('estado', 1)
                ->where('pvp', '>', 0)
                ->whereExists(function ($consulta) {
                    $consulta
                        ->select(DB::raw(1))
                        ->from('stock')
                        ->join(
                            'sucursales',
                            'sucursales.idsucursal',
                            '=',
                            'stock.idsucursal'
                        )
                        ->whereColumn('stock.idproducto', 'productos.idproducto')
                        ->where('stock.estado', 1)
                        ->where('stock.stockactual', '>', 0)
                        ->where('sucursales.estado', 1);
                })
                ->pluck('idproducto');

            $codigoEncontrado = $codigo;
        } else {
            $variantes = $this->variantesEquivalentesCodigoBarras(
                $codigo,
                $formato
            );

            $idsProductos = collect();

            if ($variantes->isNotEmpty()) {
                $coincidencias = DB::table('productos')
                    ->where('estado', 1)
                    ->where('pvp', '>', 0)
                    ->whereIn('codigobarra', $variantes)
                    ->whereExists(function ($consulta) {
                        $consulta
                            ->select(DB::raw(1))
                            ->from('stock')
                            ->join(
                                'sucursales',
                                'sucursales.idsucursal',
                                '=',
                                'stock.idsucursal'
                            )
                            ->whereColumn('stock.idproducto', 'productos.idproducto')
                            ->where('stock.estado', 1)
                            ->where('stock.stockactual', '>', 0)
                            ->where('sucursales.estado', 1);
                    })
                    ->get(['idproducto', 'codigobarra']);

                $idsProductos = $coincidencias->pluck('idproducto');

                if ($coincidencias->count() === 1) {
                    $codigoEncontrado = (string) $coincidencias->first()->codigobarra;
                    $equivalenciaAplicada = $codigoEncontrado !== $codigo;
                }
            }
        }

        $productos = $this->productosDisponiblesPorIds(
            $idsProductos,
            $idSucursalVenta
        );

        return response()->json([
            'producto' => $productos->count() === 1
                ? $productos->first()
                : null,
            'productos' => $productos->count() > 1
                ? $productos->values()
                : [],
            'ambiguo' => $productos->count() > 1,
            'codigo_recibido' => $codigo,
            'codigo_encontrado' => $codigoEncontrado,
            'formato_recibido' => $formato !== '' ? $formato : null,
            'equivalencia_aplicada' => $equivalenciaAplicada,
        ]);
    }

    /**
     * Devuelve únicamente equivalencias de representación que son seguras.
     *
     * UPC-A de 12 dígitos y EAN-13 con cero inicial representan el mismo GTIN.
     * Si no conocemos el formato (por ejemplo, ingreso manual o una foto), la
     * equivalencia solo se habilita cuando el número supera el dígito de control.
     */
    private function variantesEquivalentesCodigoBarras(
        string $codigo,
        string $formato
    ): \Illuminate\Support\Collection {
        $variantes = collect();

        if (!ctype_digit($codigo)) {
            return $variantes;
        }

        $largo = strlen($codigo);
        $formatoConocido = $formato !== '';

        if ($largo === 12) {
            $esUpcA = $formato === 'UPC_A'
                || (!$formatoConocido && $this->codigoGs1TieneDigitoControlValido($codigo));

            if ($esUpcA && $this->codigoGs1TieneDigitoControlValido($codigo)) {
                $variantes->push('0' . $codigo);
            }
        }

        if ($largo === 13 && str_starts_with($codigo, '0')) {
            $esEan13 = $formato === 'EAN_13'
                || (!$formatoConocido && $this->codigoGs1TieneDigitoControlValido($codigo));

            if ($esEan13 && $this->codigoGs1TieneDigitoControlValido($codigo)) {
                $variantes->push(substr($codigo, 1));
            }
        }

        return $variantes
            ->filter(fn ($variante) => $variante !== '' && $variante !== $codigo)
            ->unique()
            ->values();
    }

    /**
     * Normaliza solamente el nombre del formato informado por el lector.
     * Nunca toca el contenido del código de barras.
     */
    private function normalizarFormatoCodigoBarras(string $formato): string
    {
        $formato = strtoupper(trim($formato));

        if ($formato === '') {
            return '';
        }

        return str_replace(['-', ' '], '_', $formato);
    }

    /**
     * Comprueba el dígito de control usado por EAN/UPC/GTIN.
     * Funciona para longitudes comerciales habituales (8, 12, 13 y 14).
     */
    private function codigoGs1TieneDigitoControlValido(string $codigo): bool
    {
        if (!ctype_digit($codigo) || !in_array(strlen($codigo), [8, 12, 13, 14], true)) {
            return false;
        }

        $digitos = array_map('intval', str_split($codigo));
        $digitoControl = array_pop($digitos);
        $suma = 0;
        $peso = 3;

        for ($indice = count($digitos) - 1; $indice >= 0; $indice--) {
            $suma += $digitos[$indice] * $peso;
            $peso = $peso === 3 ? 1 : 3;
        }

        $esperado = (10 - ($suma % 10)) % 10;

        return $digitoControl === $esperado;
    }

    /**
     * Arma la información de stock local y total para una lista acotada de
     * productos. Se reutiliza tanto en el código de barras como en F3.
     */
    private function productosDisponiblesPorIds(
        mixed $idsProductos,
        int $idSucursalVenta
    ): \Illuminate\Support\Collection {
        $ids = collect($idsProductos)
            ->map(fn ($idProducto) => (int) $idProducto)
            ->filter(fn ($idProducto) => $idProducto > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $orden = $ids->flip();

        $filas = Stock::query()
            ->select([
                'productos.idproducto',
                'productos.codigointerno',
                'productos.codigofabricante',
                'productos.codigobarra',
                'productos.descripcion',
                'productos.pvp',
                'productos.rutaimagen',
                'stock.idsucursal',
                'stock.stockactual',
                'sucursales.nombre as sucursal',
                'marcas.nombre as marca',
            ])
            ->join('productos', 'productos.idproducto', '=', 'stock.idproducto')
            ->join('sucursales', 'sucursales.idsucursal', '=', 'stock.idsucursal')
            ->leftJoin('marcas', 'marcas.idmarca', '=', 'productos.idmarca')
            ->whereIn('productos.idproducto', $ids)
            ->where('stock.estado', 1)
            ->where('stock.stockactual', '>', 0)
            ->where('productos.estado', 1)
            ->where('productos.pvp', '>', 0)
            ->where('sucursales.estado', 1)
            ->orderBy('productos.idproducto')
            ->orderBy('sucursales.nombre')
            ->get();

        return $filas
            ->groupBy(fn ($fila) => (int) $fila->idproducto)
            ->map(function ($ubicaciones) use ($idSucursalVenta) {
                $producto = $ubicaciones->first();
                $stockLocal = (int) $ubicaciones
                    ->where('idsucursal', $idSucursalVenta)
                    ->sum('stockactual');
                $stockTotal = (int) $ubicaciones->sum('stockactual');

                return [
                    'idproducto' => (int) $producto->idproducto,
                    'codigointerno' => $producto->codigointerno,
                    'codigofabricante' => $producto->codigofabricante,
                    'codigobarra' => $producto->codigobarra,
                    'descripcion' => $producto->descripcion,
                    'marca' => $producto->marca ?: 'Sin marca',
                    'pvp' => (float) $producto->pvp,
                    'stocklocal' => $stockLocal,
                    'stocktotal' => $stockTotal,
                    'stockactual' => $stockTotal,
                    'sucursales' => $ubicaciones
                        ->map(fn ($ubicacion) => [
                            'idsucursal' => (int) $ubicacion->idsucursal,
                            'nombre' => $ubicacion->sucursal,
                            'stockactual' => (int) $ubicacion->stockactual,
                            'es_local' => (int) $ubicacion->idsucursal === $idSucursalVenta,
                        ])
                        ->values(),
                    'imagen' => $producto->rutaimagen
                        ? asset('storage/' . $producto->rutaimagen)
                        : null,
                ];
            })
            ->sortBy(fn ($producto) => $orden->get((int) $producto['idproducto'], PHP_INT_MAX))
            ->values();
    }

    /**
     * Busca clientes activos para una venta de mostrador.
     *
     * El código 1 es una opción virtual de la pantalla: representa a
     * Consumidor Final y, al guardar la venta, se convertirá en idcliente NULL.
     * Los clientes registrados se muestran como CLI-000001, CLI-000002, etc.
     */
    public function buscarClientes(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'buscar' => [
                'required',
                'string',
                'max:100',
                function (string $atributo, mixed $valor, \Closure $fallar): void {
                    $termino = trim((string) $valor);

                    if ($termino === '') {
                        $fallar('Ingresá un código o un dato del cliente.');
                        return;
                    }

                    if ($termino !== '1' && mb_strlen($termino) < 2) {
                        $fallar('Ingresá al menos dos caracteres para buscar.');
                    }
                },
            ],
        ], [
            'buscar.required' => 'Ingresá un código o un dato del cliente.',
            'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',
        ]);

        $busqueda = trim((string) $datos['buscar']);

        if ($busqueda === '1') {
            return response()->json([
                'clientes' => [[
                    'idcliente' => null,
                    'codigo' => '1',
                    'nombre_completo' => 'Consumidor Final',
                    'documento' => null,
                    'email' => null,
                    'telefono' => null,
                    'consumidor_final' => true,
                    'tipo_cliente_nombre' => 'CONSUMIDOR FINAL',
                    'descuento_tipo' => 0.0,
                    'aplica_local' => true,
                    'descuento_local' => 0.0,
                ]],
            ]);
        }

        $consulta = Cliente::query()
            ->with('tipoCliente')
            ->where('estado', 1);

        if (preg_match('/^CLI-(\d{1,10})$/i', $busqueda, $coincidencia)) {
            $consulta->where('idcliente', (int) $coincidencia[1]);
        } else {
            $terminoLike = str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                $busqueda
            );

            $consulta->where(function ($subconsulta) use ($terminoLike) {
                $subconsulta
                    ->where('nombre', 'like', "%{$terminoLike}%")
                    ->orWhere('apellido', 'like', "%{$terminoLike}%")
                    ->orWhereRaw(
                        "CONCAT(nombre, ' ', apellido) LIKE ?",
                        ["%{$terminoLike}%"]
                    )
                    ->orWhereRaw(
                        "CONCAT(apellido, ' ', nombre) LIKE ?",
                        ["%{$terminoLike}%"]
                    )
                    ->orWhere('documento', 'like', "%{$terminoLike}%")
                    ->orWhere('email', 'like', "%{$terminoLike}%")
                    ->orWhere('telefono', 'like', "%{$terminoLike}%");
            });
        }

        $clientes = $consulta
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->limit(15)
            ->get([
                'idcliente',
                'idtipocliente',
                'nombre',
                'apellido',
                'documento',
                'email',
                'telefono',
            ])
            ->map(function (Cliente $cliente) {
                $tipo = $cliente->tipoCliente;
                $tipoActivo = $tipo && (int) $tipo->estado === 1;
                $aplicaLocal = $tipoActivo && (bool) $tipo->aplica_local;
                $descuentoTipo = $tipoActivo
                    ? max(0.0, min(100.0, (float) $tipo->descuento))
                    : 0.0;
                $descuentoLocal = $aplicaLocal ? $descuentoTipo : 0.0;

                return [
                    'idcliente' => (int) $cliente->idcliente,
                    'codigo' => 'CLI-' . str_pad(
                        (string) $cliente->idcliente,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'nombre_completo' => trim(
                        $cliente->nombre . ' ' . $cliente->apellido
                    ),
                    'documento' => $cliente->documento,
                    'email' => $cliente->email,
                    'telefono' => $cliente->telefono,
                    'consumidor_final' => false,
                    'tipo_cliente_nombre' => $tipo?->nombre ?: 'SIN TIPO',
                    'descuento_tipo' => $descuentoTipo,
                    'aplica_local' => $aplicaLocal,
                    'descuento_local' => $descuentoLocal,
                ];
            });

        return response()->json([
            'clientes' => $clientes,
        ]);
    }

    /**
     * Confirma una venta local, descuenta el stock y registra su movimiento.
     */
    public function guardar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'idsucursal' => [
                'required',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
            'tipo_cliente' => [
                'required',
                Rule::in(['consumidor_final', 'registrado']),
            ],
            'idcliente' => ['nullable', 'integer'],
            'productos' => ['required', 'array', 'min:1', 'max:100'],
            'productos.*.idproducto' => ['required', 'integer', 'distinct'],
            'productos.*.cantidad' => [
                'required',
                'integer',
                'min:1',
                'max:999999',
            ],
        ], [
            'idsucursal.required' => 'Seleccioná la sucursal de la venta.',
            'idsucursal.exists' => 'La sucursal seleccionada no está activa.',
            'tipo_cliente.required' => 'Seleccioná un cliente válido.',
            'tipo_cliente.in' => 'La selección del cliente no es válida.',
            'productos.required' => 'Agregá al menos un producto.',
            'productos.min' => 'Agregá al menos un producto.',
            'productos.max' => 'Una venta no puede superar las 100 líneas.',
            'productos.*.idproducto.distinct' => 'Un producto está repetido en la venta.',
            'productos.*.cantidad.required' => 'Indicá la cantidad de cada producto.',
            'productos.*.cantidad.integer' => 'Las cantidades deben ser números enteros.',
            'productos.*.cantidad.min' => 'Las cantidades deben ser mayores a cero.',
        ]);

        $idSucursalVenta = (int) $datos['idsucursal'];
        $this->comprobarSucursalPermitida($idSucursalVenta);

        $idCliente = null;
        $porcentajeDescuentoLocal = 0.0;

        if ($datos['tipo_cliente'] === 'registrado') {
            $idClienteSolicitado = (int) ($datos['idcliente'] ?? 0);

            $clienteSeleccionado = $idClienteSolicitado > 0
                ? Cliente::query()
                    ->with('tipoCliente')
                    ->where('idcliente', $idClienteSolicitado)
                    ->where('estado', 1)
                    ->first()
                : null;

            if (!$clienteSeleccionado) {
                throw ValidationException::withMessages([
                    'idcliente' => 'El cliente seleccionado no existe o está inactivo.',
                ]);
            }

            $idCliente = $idClienteSolicitado;
            $tipoClienteSeleccionado = $clienteSeleccionado->tipoCliente;

            if (
                $tipoClienteSeleccionado
                && (int) $tipoClienteSeleccionado->estado === 1
                && (bool) $tipoClienteSeleccionado->aplica_local
            ) {
                $porcentajeDescuentoLocal = max(
                    0.0,
                    min(100.0, (float) $tipoClienteSeleccionado->descuento)
                );
            }
        }

        $usuario = auth('web')->user();

        $resultado = DB::transaction(function () use (
            $datos,
            $idSucursalVenta,
            $idCliente,
            $porcentajeDescuentoLocal,
            $usuario
        ) {
            $tipoMovimiento = TipoMovimiento::query()
                ->where('codigo', 'VENTA_STOCK')
                ->where('estado', 1)
                ->first();

            if (!$tipoMovimiento) {
                throw ValidationException::withMessages([
                    'venta' => 'No está configurado el movimiento VENTA_STOCK.',
                ]);
            }

            $sucursalesActivas = Sucursal::query()
                ->where('estado', 1)
                ->get(['idsucursal', 'nombre'])
                ->keyBy(fn (Sucursal $sucursal) => (int) $sucursal->idsucursal);

            $sucursalVenta = $sucursalesActivas->get($idSucursalVenta);

            if (!$sucursalVenta) {
                throw ValidationException::withMessages([
                    'idsucursal' => 'La sucursal de la venta ya no se encuentra activa.',
                ]);
            }

            $productosSolicitados = collect($datos['productos']);
            $idsProductos = $productosSolicitados
                ->pluck('idproducto')
                ->map(fn ($idProducto) => (int) $idProducto)
                ->values();

            // Se bloquea el stock de todas las sucursales para poder completar
            // la venta con otra ubicación si la sucursal vendedora no alcanza.
            $existencias = Stock::query()
                ->with('producto')
                ->where('estado', 1)
                ->where('stockactual', '>', 0)
                ->whereIn('idsucursal', $sucursalesActivas->keys())
                ->whereIn('idproducto', $idsProductos)
                ->orderBy('idstock')
                ->lockForUpdate()
                ->get()
                ->groupBy(fn (Stock $stock) => (int) $stock->idproducto);

            $idsIva = $existencias
                ->flatMap(fn ($stocks) => $stocks)
                ->map(fn (Stock $stock) => (int) $stock->producto?->idiva)
                ->filter()
                ->unique()
                ->values();

            $alicuotas = DB::table('alicuotas_iva')
                ->whereIn('idiva', $idsIva)
                ->where('estado', 1)
                ->where(function ($consulta) {
                    $consulta
                        ->whereNull('vigente_desde')
                        ->orWhereDate(
                            'vigente_desde',
                            '<=',
                            now(self::ZONA_HORARIA)->toDateString()
                        );
                })
                ->where(function ($consulta) {
                    $consulta
                        ->whereNull('vigente_hasta')
                        ->orWhereDate(
                            'vigente_hasta',
                            '>=',
                            now(self::ZONA_HORARIA)->toDateString()
                        );
                })
                ->get()
                ->keyBy(fn ($alicuota) => (int) $alicuota->idiva);

            $lineas = [];
            $salidasPorSucursal = [];
            $subtotalVenta = 0.0;
            $descuentoVenta = 0.0;
            $totalVenta = 0.0;
            $usaStockRemoto = false;

            foreach ($productosSolicitados as $indice => $solicitado) {
                $idProducto = (int) $solicitado['idproducto'];
                $cantidad = (int) $solicitado['cantidad'];
                $stocksProducto = $existencias->get($idProducto, collect());

                if ($stocksProducto->isEmpty()) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" =>
                            'El producto ya no posee stock disponible en ninguna sucursal.',
                    ]);
                }

                /** @var Stock|null $stockReferencia */
                $stockReferencia = $stocksProducto->first();
                $producto = $stockReferencia?->producto;

                if (!$producto || !$producto->estado) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => 'El producto ya no está activo.',
                    ]);
                }

                $disponibleTotal = (int) $stocksProducto->sum('stockactual');

                if ($disponibleTotal < $cantidad) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.cantidad" => sprintf(
                            'Stock insuficiente para %s. Disponibles entre todas las sucursales: %d.',
                            $producto->descripcion,
                            $disponibleTotal
                        ),
                    ]);
                }

                $precioUnitario = round((float) $producto->pvp, 2);

                if ($precioUnitario <= 0) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => sprintf(
                            '%s no tiene un precio de venta válido.',
                            $producto->descripcion
                        ),
                    ]);
                }

                $idIva = (int) $producto->idiva;
                $alicuota = $alicuotas->get($idIva);

                if (!$alicuota) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => sprintf(
                            '%s no tiene una alícuota de IVA activa y vigente.',
                            $producto->descripcion
                        ),
                    ]);
                }

                $porcentajeIva = (float) $alicuota->porcentaje;

                if ($porcentajeIva < 0 || $porcentajeIva > 100) {
                    throw ValidationException::withMessages([
                        "productos.{$indice}.idproducto" => 'El porcentaje de IVA no es válido.',
                    ]);
                }

                $subtotalLinea = round($precioUnitario * $cantidad, 2);
                $descuentoLinea = round(
                    $subtotalLinea * ($porcentajeDescuentoLocal / 100),
                    2
                );
                $totalLinea = round($subtotalLinea - $descuentoLinea, 2);
                $baseSinIva = round(
                    $totalLinea / (1 + ($porcentajeIva / 100)),
                    2
                );
                $importeIva = round($totalLinea - $baseSinIva, 2);

                $lineas[] = [
                    'idproducto' => $idProducto,
                    'cantidad' => $cantidad,
                    'detalle' => [
                        'idproducto' => $idProducto,
                        'idiva' => $idIva,
                        'cantidad' => $cantidad,
                        'costounitario' => 0,
                        'preciounitario' => $precioUnitario,
                        'subtotal' => $subtotalLinea,
                        'descuento' => $descuentoLinea,
                        'importeiva' => $importeIva,
                        'totallinea' => $totalLinea,
                    ],
                ];

                // Primero se consume la sucursal de la venta. Si no alcanza,
                // se completa con las otras sucursales con mayor stock.
                $stockLocal = $stocksProducto->first(
                    fn (Stock $stock) => (int) $stock->idsucursal === $idSucursalVenta
                );

                $stocksOrdenados = collect();

                if ($stockLocal) {
                    $stocksOrdenados->push($stockLocal);
                }

                $stocksOrdenados = $stocksOrdenados->concat(
                    $stocksProducto
                        ->reject(fn (Stock $stock) =>
                            (int) $stock->idsucursal === $idSucursalVenta)
                        ->sortByDesc(fn (Stock $stock) => (int) $stock->stockactual)
                );

                $pendiente = $cantidad;

                foreach ($stocksOrdenados as $stockOrigen) {
                    if ($pendiente <= 0) {
                        break;
                    }

                    $tomar = min($pendiente, (int) $stockOrigen->stockactual);

                    if ($tomar <= 0) {
                        continue;
                    }

                    $idSucursalOrigen = (int) $stockOrigen->idsucursal;
                    $salidasPorSucursal[$idSucursalOrigen] ??= [];
                    $salidasPorSucursal[$idSucursalOrigen][] = [
                        'stock' => $stockOrigen,
                        'idproducto' => $idProducto,
                        'cantidad' => $tomar,
                    ];

                    if ($idSucursalOrigen !== $idSucursalVenta) {
                        $usaStockRemoto = true;
                    }

                    $pendiente -= $tomar;
                }

                $subtotalVenta = round($subtotalVenta + $subtotalLinea, 2);
                $descuentoVenta = round($descuentoVenta + $descuentoLinea, 2);
                $totalVenta = round($totalVenta + $totalLinea, 2);
            }

            $fecha = now('UTC');

            $venta = Venta::create([
                'idcliente' => $idCliente,
                'idusuario' => $usuario->id,
                'idsucursal' => $idSucursalVenta,
                'fecha' => $fecha,
                'canal' => 'LOCAL',
                'tipo' => 'DIRECTA',
                'estado' => 'CONFIRMADA',
                'subtotal' => $subtotalVenta,
                'descuento' => $descuentoVenta,
                'total' => $totalVenta,
                'facturada' => false,
            ]);

            foreach ($lineas as $linea) {
                $venta->detalles()->create($linea['detalle']);
            }

            $numeroVenta = 'V-' . str_pad(
                (string) $venta->idventa,
                6,
                '0',
                STR_PAD_LEFT
            );

            foreach ($salidasPorSucursal as $idSucursalOrigen => $salidas) {
                $esRemota = (int) $idSucursalOrigen !== $idSucursalVenta;
                $nombreOrigen = $sucursalesActivas->get((int) $idSucursalOrigen)?->nombre
                    ?? 'Sucursal';

                $movimiento = Movimiento::create([
                    'idtipomovimiento' => $tipoMovimiento->idtipomovimiento,
                    'idusuario' => $usuario->id,
                    'idsucursalorigen' => (int) $idSucursalOrigen,
                    // Cuando es remoto, este campo permite que la sucursal
                    // de origen vea el aviso de qué local realizó la venta.
                    'idsucursaldestino' => $esRemota ? $idSucursalVenta : null,
                    'idventa' => $venta->idventa,
                    'fecha' => $fecha,
                    'referencia' => sprintf(
                        'VTA-%06d-S%03d',
                        $venta->idventa,
                        (int) $idSucursalOrigen
                    ),
                    'observacion' => $esRemota
                        ? sprintf(
                            '%s fue realizada en %s usando stock de %s. El stock ya fue descontado; preparar o coordinar la entrega.',
                            $numeroVenta,
                            $sucursalVenta->nombre,
                            $nombreOrigen
                        )
                        : 'Salida automática por venta local',
                    'estado' => 1,
                ]);

                foreach ($salidas as $salida) {
                    DetalleMovimiento::create([
                        'idmovimiento' => $movimiento->idmovimiento,
                        'idproducto' => $salida['idproducto'],
                        'cantidad' => $salida['cantidad'],
                        'costounitario' => null,
                        'subtotal' => null,
                    ]);

                    /** @var Stock $stock */
                    $stock = $salida['stock'];
                    $stock->stockactual = (int) $stock->stockactual
                        - (int) $salida['cantidad'];
                    $stock->save();
                }
            }

            return [
                'venta' => $venta,
                'usaStockRemoto' => $usaStockRemoto,
            ];
        }, 3);

        /** @var Venta $venta */
        $venta = $resultado['venta'];

        session()->flash(
            'exito',
            'Venta V-' . str_pad((string) $venta->idventa, 6, '0', STR_PAD_LEFT)
                . ' registrada correctamente.'
        );

        return response()->json([
            'mensaje' => $resultado['usaStockRemoto']
                ? 'Venta registrada. Se utilizó stock de otra sucursal y se generó el aviso correspondiente.'
                : 'Venta registrada correctamente.',
            'idventa' => (int) $venta->idventa,
            'usa_stock_remoto' => (bool) $resultado['usaStockRemoto'],
            'redirect' => route('ventas.index'),
        ], 201);
    }

    /**
     * Impide que un usuario de una sucursal consulte existencias de otra.
     */
    private function comprobarSucursalPermitida(
        int $idSucursal,
        string $accion = 'registrar'
    ): void
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol');

        $esDuenoGlobal = $usuario->rol
            && $usuario->rol->nombre === 'DUEÑO'
            && $usuario->idsucursal === null;

        if ($esDuenoGlobal) {
            return;
        }

        abort_if(
            $usuario->idsucursal === null,
            403,
            $accion === 'consultar'
                ? 'Tu cuenta no tiene una sucursal asignada para consultar ventas.'
                : 'Tu cuenta no tiene una sucursal asignada para registrar ventas.'
        );

        if (
            (int) $usuario->idsucursal !== $idSucursal
        ) {
            abort(
                403,
                $accion === 'consultar'
                    ? 'No podés consultar ventas de otra sucursal.'
                    : 'No podés registrar ventas en otra sucursal.'
            );
        }
    }

    /**
     * Determina si corresponde mostrar la acción de anular en el detalle.
     */
    private function puedeUsuarioAnularVenta(Venta $venta): bool
    {
        $usuario = auth('web')->user();

        if (!$usuario || !$usuario->tienePermiso('ventas.anular')) {
            return false;
        }

        $estado = strtoupper((string) $venta->estado);

        if (
            !in_array($estado, ['CONFIRMADA', 'COMPLETADA'], true)
            || (bool) $venta->facturada
        ) {
            return false;
        }

        $usuario->loadMissing('rol');
        $rol = strtoupper((string) $usuario->rol?->nombre);

        $esDuenoGlobal = $rol === 'DUEÑO'
            && $usuario->idsucursal === null;

        if ($esDuenoGlobal) {
            return true;
        }

        if (
            $usuario->idsucursal === null
            || (int) $usuario->idsucursal !== (int) $venta->idsucursal
        ) {
            return false;
        }

        if (in_array($rol, ['DUEÑO', 'GERENTE'], true)) {
            return true;
        }

        return $rol === 'EMPLEADO'
            && (int) $venta->idusuario === (int) $usuario->id
            && $this->esFechaLocalDeHoy($venta->fecha);
    }

    /**
     * Vuelve a validar la autorización y el estado dentro de la transacción.
     */
    private function validarAnulacionPermitida(Venta $venta): void
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol');

        abort_unless(
            $usuario->tienePermiso('ventas.anular'),
            403,
            'No tenés permiso para anular ventas.'
        );

        $this->comprobarSucursalPermitida(
            (int) $venta->idsucursal,
            'consultar'
        );

        $estado = strtoupper((string) $venta->estado);

        if ($estado === 'ANULADA' || $estado === 'CANCELADA') {
            throw ValidationException::withMessages([
                'venta' => 'La venta ya se encuentra anulada.',
            ]);
        }

        if (!in_array($estado, ['CONFIRMADA', 'COMPLETADA'], true)) {
            throw ValidationException::withMessages([
                'venta' => 'El estado actual de la venta no permite anularla.',
            ]);
        }

        if ((bool) $venta->facturada) {
            throw ValidationException::withMessages([
                'venta' => 'Una venta facturada no puede anularse directamente.',
            ]);
        }

        $rol = strtoupper((string) $usuario->rol?->nombre);

        if (in_array($rol, ['DUEÑO', 'GERENTE'], true)) {
            return;
        }

        abort_unless(
            $rol === 'EMPLEADO',
            403,
            'Tu rol no puede anular ventas.'
        );

        abort_unless(
            (int) $venta->idusuario === (int) $usuario->id,
            403,
            'Solo podés anular ventas realizadas por vos.'
        );

        if (!$this->esFechaLocalDeHoy($venta->fecha)) {
            throw ValidationException::withMessages([
                'venta' => 'Los empleados solo pueden anular sus ventas del día.',
            ]);
        }
    }

    /**
     * Compara el día usando la hora local argentina, aunque la BD guarde UTC.
     */
    private function esFechaLocalDeHoy(mixed $fecha): bool
    {
        if (!$fecha) {
            return false;
        }

        return $fecha
            ->copy()
            ->setTimezone(self::ZONA_HORARIA)
            ->isSameDay(now(self::ZONA_HORARIA));
    }

    /**
     * Limita los listados y resúmenes a la sucursal del usuario.
     * El dueño sin sucursal asignada puede consultar todas las ubicaciones.
     */
    private function consultaVentasPermitidas(): Builder
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol');

        $esDuenoGlobal = $usuario->rol
            && $usuario->rol->nombre === 'DUEÑO'
            && $usuario->idsucursal === null;

        if ($esDuenoGlobal) {
            return Venta::query();
        }

        abort_if(
            $usuario->idsucursal === null,
            403,
            'Tu cuenta no tiene una sucursal asignada para consultar ventas.'
        );

        return Venta::query()->where(
            'idsucursal',
            (int) $usuario->idsucursal
        );
    }
}
