<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\Sucursal;
use App\Models\Stock;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    private const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

    /**
     * Muestra el panel general de ventas con filtros y resúmenes.
     */
    public function index(Request $request): View
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol', 'sucursal');

        $esDuenoGlobal = strtoupper((string) $usuario->rol?->nombre) === 'DUEÑO'
            && $usuario->idsucursal === null;

        abort_if(
            !$esDuenoGlobal && $usuario->idsucursal === null,
            403,
            'Tu cuenta no tiene una sucursal asignada para consultar reportes.'
        );

        $hoyLocal = now(self::ZONA_HORARIA);
        $valoresIniciales = [
            'desde' => $hoyLocal->copy()->startOfMonth()->format('Y-m-d'),
            'hasta' => $hoyLocal->format('Y-m-d'),
        ];

        $request->merge([
            'desde' => $request->input('desde', $valoresIniciales['desde']),
            'hasta' => $request->input('hasta', $valoresIniciales['hasta']),
            'buscar' => trim((string) $request->input('buscar', '')),
        ]);

        $datos = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'idsucursal' => [
                'nullable',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
            'idusuario' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', 1)
                ),
            ],
            'estado' => [
                'nullable',
                Rule::in([
                    'PENDIENTE',
                    'CONFIRMADA',
                    'COMPLETADA',
                    'ANULADA',
                    'CANCELADA',
                ]),
            ],
            'buscar' => ['nullable', 'string', 'max:100'],
        ], [
            'desde.required' => 'Seleccioná la fecha inicial.',
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.required' => 'Seleccioná la fecha final.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'idsucursal.exists' => 'La sucursal seleccionada no está disponible.',
            'idusuario.exists' => 'El vendedor seleccionado no está disponible.',
            'estado.in' => 'El estado elegido no es válido.',
            'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',
        ]);

        $desdeLocal = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $datos['desde'] . ' 00:00:00',
            self::ZONA_HORARIA
        );
        $hastaLocal = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $datos['hasta'] . ' 23:59:59',
            self::ZONA_HORARIA
        );

        if ($desdeLocal->diffInDays($hastaLocal) > 366) {
            throw ValidationException::withMessages([
                'hasta' => 'El período máximo de consulta es de 366 días.',
            ]);
        }

        $idSucursal = $esDuenoGlobal
            ? ($datos['idsucursal'] ?? null)
            : (int) $usuario->idsucursal;

        $consulta = Venta::query()
            ->whereBetween('fecha', [
                $desdeLocal->copy()->utc(),
                $hastaLocal->copy()->utc(),
            ])
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where(
                    'idsucursal',
                    (int) $idSucursal
                )
            )
            ->when(
                $datos['idusuario'] ?? null,
                fn (Builder $consulta, $idUsuario) => $consulta->where(
                    'idusuario',
                    (int) $idUsuario
                )
            )
            ->when(
                $datos['estado'] ?? null,
                fn (Builder $consulta, $estado) => $consulta->where(
                    'estado',
                    $estado
                )
            );

        $this->aplicarBusqueda($consulta, $datos['buscar'] ?? '');

        $ventasNoAnuladas = (clone $consulta)->whereNotIn(
            'estado',
            ['ANULADA', 'CANCELADA']
        );

        $resumen = [
            'operaciones' => (clone $consulta)->count(),
            'ventasValidas' => (clone $ventasNoAnuladas)->count(),
            'totalVendido' => (float) (clone $ventasNoAnuladas)->sum('total'),
            'promedio' => (float) ((clone $ventasNoAnuladas)->avg('total') ?? 0),
            'anuladas' => (clone $consulta)
                ->whereIn('estado', ['ANULADA', 'CANCELADA'])
                ->count(),
            'unidades' => (int) DetalleVenta::query()
                ->whereIn(
                    'idventa',
                    (clone $ventasNoAnuladas)->select('idventa')
                )
                ->sum('cantidad'),
        ];

        $topProductos = $this->obtenerRankingProductos($ventasNoAnuladas);

        $ventas = (clone $consulta)
            ->with(['cliente', 'usuario', 'sucursal'])
            ->orderByDesc('fecha')
            ->orderByDesc('idventa')
            ->paginate(20)
            ->withQueryString();

        $ventasPorDia = $this->agruparVentasPorDia(
            (clone $ventasNoAnuladas)
                ->orderBy('fecha')
                ->get(['fecha', 'total'])
        );

        $sucursales = Sucursal::query()
            ->where('estado', 1)
            ->when(
                !$esDuenoGlobal,
                fn (Builder $consulta) => $consulta->where(
                    'idsucursal',
                    (int) $usuario->idsucursal
                )
            )
            ->orderBy('nombre')
            ->get(['idsucursal', 'nombre', 'tipo']);

        $usuarios = User::query()
            ->where('estado', 1)
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where(
                    'idsucursal',
                    (int) $idSucursal
                )
            )
            ->when(
                !$esDuenoGlobal,
                fn (Builder $consulta) => $consulta->where(
                    'idsucursal',
                    (int) $usuario->idsucursal
                )
            )
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get(['id', 'nombre', 'apellido', 'idsucursal']);

        return view('reportes.index', [
            'ventas' => $ventas,
            'resumen' => $resumen,
            'ventasPorDia' => $ventasPorDia,
            'topProductos' => $topProductos,
            'sucursales' => $sucursales,
            'usuarios' => $usuarios,
            'filtros' => $datos,
            'esDuenoGlobal' => $esDuenoGlobal,
            'sucursalUsuario' => $usuario->sucursal,
            'zonaHoraria' => self::ZONA_HORARIA,
        ]);
    }

    /**
     * Descarga en CSV las ventas que coinciden con los filtros del panel.
     */
    public function exportarVentas(Request $request): StreamedResponse
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol', 'sucursal');

        $esDuenoGlobal = strtoupper((string) $usuario->rol?->nombre) === 'DUEÑO'
            && $usuario->idsucursal === null;

        abort_if(
            !$esDuenoGlobal && $usuario->idsucursal === null,
            403,
            'Tu cuenta no tiene una sucursal asignada para exportar reportes.'
        );

        $hoyLocal = now(self::ZONA_HORARIA);

        $request->merge([
            'desde' => $request->input(
                'desde',
                $hoyLocal->copy()->startOfMonth()->format('Y-m-d')
            ),
            'hasta' => $request->input('hasta', $hoyLocal->format('Y-m-d')),
            'buscar' => trim((string) $request->input('buscar', '')),
        ]);

        $datos = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'idsucursal' => [
                'nullable',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
            'idusuario' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', 1)
                ),
            ],
            'estado' => [
                'nullable',
                Rule::in([
                    'PENDIENTE',
                    'CONFIRMADA',
                    'COMPLETADA',
                    'ANULADA',
                    'CANCELADA',
                ]),
            ],
            'buscar' => ['nullable', 'string', 'max:100'],
        ], [
            'desde.required' => 'Seleccioná la fecha inicial.',
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.required' => 'Seleccioná la fecha final.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'idsucursal.exists' => 'La sucursal seleccionada no está disponible.',
            'idusuario.exists' => 'El vendedor seleccionado no está disponible.',
            'estado.in' => 'El estado elegido no es válido.',
            'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',
        ]);

        $desdeLocal = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $datos['desde'] . ' 00:00:00',
            self::ZONA_HORARIA
        );
        $hastaLocal = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $datos['hasta'] . ' 23:59:59',
            self::ZONA_HORARIA
        );

        if ($desdeLocal->diffInDays($hastaLocal) > 366) {
            throw ValidationException::withMessages([
                'hasta' => 'El período máximo de exportación es de 366 días.',
            ]);
        }

        $idSucursal = $esDuenoGlobal
            ? ($datos['idsucursal'] ?? null)
            : (int) $usuario->idsucursal;

        $consulta = Venta::query()
            ->whereBetween('fecha', [
                $desdeLocal->copy()->utc(),
                $hastaLocal->copy()->utc(),
            ])
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where(
                    'idsucursal',
                    (int) $idSucursal
                )
            )
            ->when(
                $datos['idusuario'] ?? null,
                fn (Builder $consulta, $idUsuario) => $consulta->where(
                    'idusuario',
                    (int) $idUsuario
                )
            )
            ->when(
                $datos['estado'] ?? null,
                fn (Builder $consulta, $estado) => $consulta->where(
                    'estado',
                    $estado
                )
            );

        $this->aplicarBusqueda($consulta, $datos['buscar'] ?? '');

        $ventas = $consulta
            ->with(['cliente', 'usuario', 'sucursal'])
            ->orderByDesc('fecha')
            ->orderByDesc('idventa')
            ->get();

        $nombreArchivo = sprintf(
            'ventas_%s_al_%s.csv',
            Carbon::parse($datos['desde'])->format('Ymd'),
            Carbon::parse($datos['hasta'])->format('Ymd')
        );

        return response()->streamDownload(
            function () use ($ventas) {
                $salida = fopen('php://output', 'wb');

                if ($salida === false) {
                    return;
                }

                // Excel para Windows abre mejor los acentos usando Windows-1252.
                fwrite($salida, "sep=;\r\n");

                fputcsv($salida, array_map(
                    fn ($valor) => $this->codificarParaExcel($valor),
                    [
                        'Número',
                        'Fecha',
                        'Hora',
                        'Cliente',
                        'Email del cliente',
                        'Vendedor',
                        'Sucursal',
                        'Canal',
                        'Subtotal',
                        'Descuento',
                        'Total',
                        'Estado',
                    ]
                ), ';', '"', '');

                foreach ($ventas as $venta) {
                    $fechaLocal = $venta->fecha
                        ? $venta->fecha->copy()->setTimezone(self::ZONA_HORARIA)
                        : null;

                    $cliente = $venta->cliente
                        ? trim($venta->cliente->nombre . ' ' . $venta->cliente->apellido)
                        : 'Consumidor final';
                    $vendedor = trim(
                        ($venta->usuario?->nombre ?? 'Usuario no disponible')
                        . ' '
                        . ($venta->usuario?->apellido ?? '')
                    );

                    fputcsv($salida, array_map(
                        fn ($valor) => $this->codificarParaExcel(
                            $this->protegerCeldaCsv($valor)
                        ),
                        [
                            'V-' . str_pad(
                                (string) $venta->idventa,
                                6,
                                '0',
                                STR_PAD_LEFT
                            ),
                            $fechaLocal?->format('d/m/Y') ?? '',
                            $fechaLocal?->format('H:i') ?? '',
                            $cliente,
                            $venta->cliente?->email ?? '',
                            $vendedor,
                            $venta->sucursal?->nombre ?? 'Sin sucursal',
                            $venta->canal ?: '',
                            number_format((float) $venta->subtotal, 2, ',', ''),
                            number_format((float) $venta->descuento, 2, ',', ''),
                            number_format((float) $venta->total, 2, ',', ''),
                            strtoupper((string) ($venta->estado ?: 'PENDIENTE')),
                        ]
                    ), ';', '"', '');
                }

                fclose($salida);
            },
            $nombreArchivo,
            [
                'Content-Type' => 'text/csv; charset=Windows-1252',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Muestra una versión limpia del reporte para imprimir o guardar como PDF.
     */
    public function imprimirVentas(Request $request): View
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol', 'sucursal');

        $esDuenoGlobal = strtoupper((string) $usuario->rol?->nombre) === 'DUEÑO'
            && $usuario->idsucursal === null;

        abort_if(
            !$esDuenoGlobal && $usuario->idsucursal === null,
            403,
            'Tu cuenta no tiene una sucursal asignada para imprimir reportes.'
        );

        $hoyLocal = now(self::ZONA_HORARIA);

        $request->merge([
            'desde' => $request->input(
                'desde',
                $hoyLocal->copy()->startOfMonth()->format('Y-m-d')
            ),
            'hasta' => $request->input('hasta', $hoyLocal->format('Y-m-d')),
            'buscar' => trim((string) $request->input('buscar', '')),
        ]);

        $datos = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'idsucursal' => [
                'nullable',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
            'idusuario' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($consulta) => $consulta->where('estado', 1)
                ),
            ],
            'estado' => [
                'nullable',
                Rule::in([
                    'PENDIENTE',
                    'CONFIRMADA',
                    'COMPLETADA',
                    'ANULADA',
                    'CANCELADA',
                ]),
            ],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $desdeLocal = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $datos['desde'] . ' 00:00:00',
            self::ZONA_HORARIA
        );
        $hastaLocal = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $datos['hasta'] . ' 23:59:59',
            self::ZONA_HORARIA
        );

        if ($desdeLocal->diffInDays($hastaLocal) > 366) {
            throw ValidationException::withMessages([
                'hasta' => 'El período máximo de impresión es de 366 días.',
            ]);
        }

        $idSucursal = $esDuenoGlobal
            ? ($datos['idsucursal'] ?? null)
            : (int) $usuario->idsucursal;

        $consulta = Venta::query()
            ->whereBetween('fecha', [
                $desdeLocal->copy()->utc(),
                $hastaLocal->copy()->utc(),
            ])
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where(
                    'idsucursal',
                    (int) $idSucursal
                )
            )
            ->when(
                $datos['idusuario'] ?? null,
                fn (Builder $consulta, $idUsuario) => $consulta->where(
                    'idusuario',
                    (int) $idUsuario
                )
            )
            ->when(
                $datos['estado'] ?? null,
                fn (Builder $consulta, $estado) => $consulta->where(
                    'estado',
                    $estado
                )
            );

        $this->aplicarBusqueda($consulta, $datos['buscar'] ?? '');

        $ventasNoAnuladas = (clone $consulta)->whereNotIn(
            'estado',
            ['ANULADA', 'CANCELADA']
        );

        $resumen = [
            'operaciones' => (clone $consulta)->count(),
            'ventasValidas' => (clone $ventasNoAnuladas)->count(),
            'totalVendido' => (float) (clone $ventasNoAnuladas)->sum('total'),
            'promedio' => (float) ((clone $ventasNoAnuladas)->avg('total') ?? 0),
            'anuladas' => (clone $consulta)
                ->whereIn('estado', ['ANULADA', 'CANCELADA'])
                ->count(),
            'unidades' => (int) DetalleVenta::query()
                ->whereIn(
                    'idventa',
                    (clone $ventasNoAnuladas)->select('idventa')
                )
                ->sum('cantidad'),
        ];

        $topProductos = $this->obtenerRankingProductos($ventasNoAnuladas);

        $ventas = (clone $consulta)
            ->with(['cliente', 'usuario', 'sucursal'])
            ->orderByDesc('fecha')
            ->orderByDesc('idventa')
            ->get();

        $sucursalFiltro = $idSucursal
            ? Sucursal::query()->find((int) $idSucursal)?->nombre
            : 'Todas las sucursales';

        $vendedorFiltro = 'Todos los vendedores';

        if (!empty($datos['idusuario'])) {
            $vendedor = User::query()->find((int) $datos['idusuario']);
            $vendedorFiltro = $vendedor
                ? trim($vendedor->nombre . ' ' . $vendedor->apellido)
                : 'Vendedor no disponible';
        }

        return view('reportes.imprimir', [
            'ventas' => $ventas,
            'resumen' => $resumen,
            'topProductos' => $topProductos,
            'filtros' => $datos,
            'sucursalFiltro' => $sucursalFiltro ?: 'Sucursal no disponible',
            'vendedorFiltro' => $vendedorFiltro,
            'generadoPor' => trim($usuario->nombre . ' ' . $usuario->apellido),
            'fechaEmision' => now(self::ZONA_HORARIA),
            'zonaHoraria' => self::ZONA_HORARIA,
        ]);
    }

    /**
     * Muestra los productos sin stock, por debajo del mínimo o en el máximo.
     */
    public function stock(Request $request): View
    {
        $usuario = auth('web')->user();
        $usuario->loadMissing('rol', 'sucursal');

        $esDuenoGlobal = strtoupper((string) $usuario->rol?->nombre) === 'DUEÑO'
            && $usuario->idsucursal === null;

        abort_if(
            !$esDuenoGlobal && $usuario->idsucursal === null,
            403,
            'Tu cuenta no tiene una sucursal asignada para consultar alertas.'
        );

        $request->merge([
            'buscar' => trim((string) $request->input('buscar', '')),
        ]);

        $datos = $request->validate([
            'idsucursal' => [
                'nullable',
                'integer',
                Rule::exists('sucursales', 'idsucursal')
                    ->where(fn ($consulta) => $consulta->where('estado', 1)),
            ],
            'alerta' => [
                'nullable',
                Rule::in(['SIN_STOCK', 'BAJO_MINIMO', 'MAXIMO']),
            ],
            'buscar' => ['nullable', 'string', 'max:100'],
        ], [
            'idsucursal.exists' => 'La sucursal seleccionada no está disponible.',
            'alerta.in' => 'El tipo de alerta elegido no es válido.',
            'buscar.max' => 'La búsqueda no puede superar los 100 caracteres.',
        ]);

        $idSucursal = $esDuenoGlobal
            ? ($datos['idsucursal'] ?? null)
            : (int) $usuario->idsucursal;

        $consultaBase = Stock::query()
            ->where('stock.estado', 1)
            ->whereHas('producto', fn (Builder $consulta) => $consulta->where(
                'estado',
                1
            ))
            ->when(
                $idSucursal,
                fn (Builder $consulta) => $consulta->where(
                    'stock.idsucursal',
                    (int) $idSucursal
                )
            );

        $this->aplicarBusquedaStock(
            $consultaBase,
            $datos['buscar'] ?? ''
        );

        $resumenStock = [
            'sinStock' => (clone $consultaBase)
                ->where('stockactual', '<=', 0)
                ->count(),
            'bajoMinimo' => (clone $consultaBase)
                ->where('stockactual', '>', 0)
                ->where('stockminimo', '>', 0)
                ->whereColumn('stockactual', '<=', 'stockminimo')
                ->count(),
            'maximo' => (clone $consultaBase)
                ->where('stockmaximo', '>', 0)
                ->whereColumn('stockactual', '>=', 'stockmaximo')
                ->count(),
        ];
        $resumenStock['total'] = $resumenStock['sinStock']
            + $resumenStock['bajoMinimo']
            + $resumenStock['maximo'];

        $alertas = clone $consultaBase;
        $this->aplicarTipoAlertaStock(
            $alertas,
            $datos['alerta'] ?? null
        );

        $stocks = $alertas
            ->with(['producto', 'sucursal'])
            ->orderByRaw(
                'CASE '
                . 'WHEN stockactual <= 0 THEN 1 '
                . 'WHEN stockminimo > 0 AND stockactual <= stockminimo THEN 2 '
                . 'WHEN stockmaximo > 0 AND stockactual >= stockmaximo THEN 3 '
                . 'ELSE 4 END'
            )
            ->orderBy('stockactual')
            ->orderBy('idstock')
            ->paginate(25)
            ->withQueryString();

        $sucursales = Sucursal::query()
            ->where('estado', 1)
            ->when(
                !$esDuenoGlobal,
                fn (Builder $consulta) => $consulta->where(
                    'idsucursal',
                    (int) $usuario->idsucursal
                )
            )
            ->orderBy('nombre')
            ->get(['idsucursal', 'nombre', 'tipo']);

        return view('reportes.stock', [
            'stocks' => $stocks,
            'resumenStock' => $resumenStock,
            'sucursales' => $sucursales,
            'filtros' => $datos,
            'esDuenoGlobal' => $esDuenoGlobal,
            'sucursalUsuario' => $usuario->sucursal,
        ]);
    }

    /**
     * Permite localizar una operación por número, cliente o vendedor.
     */
    private function aplicarBusqueda(Builder $consulta, string $buscar): void
    {
        if ($buscar === '') {
            return;
        }

        $termino = trim($buscar);
        $numero = preg_replace('/\D+/', '', $termino);

        $consulta->where(function (Builder $subconsulta) use ($termino, $numero) {
            if ($numero !== '') {
                $subconsulta->orWhere('idventa', (int) $numero);
            }

            $subconsulta
                ->orWhereHas('cliente', function (Builder $cliente) use ($termino) {
                    $cliente
                        ->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('apellido', 'like', "%{$termino}%")
                        ->orWhere('email', 'like', "%{$termino}%");
                })
                ->orWhereHas('usuario', function (Builder $vendedor) use ($termino) {
                    $vendedor
                        ->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('apellido', 'like', "%{$termino}%");
                });
        });
    }

    /**
     * Evita que Excel interprete textos del CSV como fórmulas.
     */
    private function protegerCeldaCsv(mixed $valor): string
    {
        $texto = (string) ($valor ?? '');

        if (preg_match('/^[=+\-@]/u', $texto) === 1) {
            return "'" . $texto;
        }

        return $texto;
    }

    /**
     * Convierte cada celda al juego de caracteres que Excel usa en Windows.
     */
    private function codificarParaExcel(mixed $valor): string
    {
        $texto = (string) ($valor ?? '');
        $convertido = iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);

        return $convertido === false ? $texto : $convertido;
    }

    /**
     * Devuelve los cinco productos con más unidades vendidas.
     */
    private function obtenerRankingProductos(
        Builder $ventasNoAnuladas
    ): Collection {
        $productos = DetalleVenta::query()
            ->join(
                'productos',
                'productos.idproducto',
                '=',
                'detalle_venta.idproducto'
            )
            ->whereIn(
                'detalle_venta.idventa',
                (clone $ventasNoAnuladas)->select('ventas.idventa')
            )
            ->select([
                'detalle_venta.idproducto',
                'productos.codigointerno',
                'productos.descripcion',
            ])
            ->selectRaw('SUM(detalle_venta.cantidad) AS unidades_vendidas')
            ->selectRaw('SUM(detalle_venta.totallinea) AS importe_generado')
            ->selectRaw(
                'COUNT(DISTINCT detalle_venta.idventa) AS cantidad_ventas'
            )
            ->groupBy(
                'detalle_venta.idproducto',
                'productos.codigointerno',
                'productos.descripcion'
            )
            ->orderByDesc('unidades_vendidas')
            ->orderByDesc('importe_generado')
            ->limit(5)
            ->get();

        $maximo = max(
            1,
            (int) ($productos->max('unidades_vendidas') ?? 0)
        );

        return $productos
            ->values()
            ->map(function ($producto, int $indice) use ($maximo) {
                $producto->posicion = $indice + 1;
                $producto->porcentaje = max(
                    6,
                    ((int) $producto->unidades_vendidas / $maximo) * 100
                );

                return $producto;
            });
    }

    /**
     * Busca una alerta por código interno, código de barras o descripción.
     */
    private function aplicarBusquedaStock(Builder $consulta, string $buscar): void
    {
        if ($buscar === '') {
            return;
        }

        $consulta->whereHas('producto', function (Builder $producto) use ($buscar) {
            $producto
                ->where('codigointerno', 'like', "%{$buscar}%")
                ->orWhere('codigobarra', 'like', "%{$buscar}%")
                ->orWhere('codigofabricante', 'like', "%{$buscar}%")
                ->orWhere('descripcion', 'like', "%{$buscar}%");
        });
    }

    /**
     * Aplica una categoría o, si no se eligió una, muestra todas las alertas.
     */
    private function aplicarTipoAlertaStock(
        Builder $consulta,
        ?string $tipo
    ): void {
        if ($tipo === 'SIN_STOCK') {
            $consulta->where('stockactual', '<=', 0);

            return;
        }

        if ($tipo === 'BAJO_MINIMO') {
            $consulta
                ->where('stockactual', '>', 0)
                ->where('stockminimo', '>', 0)
                ->whereColumn('stockactual', '<=', 'stockminimo');

            return;
        }

        if ($tipo === 'MAXIMO') {
            $consulta
                ->where('stockmaximo', '>', 0)
                ->whereColumn('stockactual', '>=', 'stockmaximo');

            return;
        }

        $consulta->where(function (Builder $alertas) {
            $alertas
                ->where('stockactual', '<=', 0)
                ->orWhere(function (Builder $bajoMinimo) {
                    $bajoMinimo
                        ->where('stockactual', '>', 0)
                        ->where('stockminimo', '>', 0)
                        ->whereColumn('stockactual', '<=', 'stockminimo');
                })
                ->orWhere(function (Builder $maximo) {
                    $maximo
                        ->where('stockmaximo', '>', 0)
                        ->whereColumn('stockactual', '>=', 'stockmaximo');
                });
        });
    }

    /**
     * Agrupa los importes por día local y conserva los últimos 14 días con actividad.
     */
    private function agruparVentasPorDia(Collection $ventas): Collection
    {
        $totales = $ventas
            ->groupBy(fn (Venta $venta) => $venta->fecha
                ->copy()
                ->setTimezone(self::ZONA_HORARIA)
                ->format('Y-m-d'))
            ->map(fn (Collection $grupo, string $fecha) => [
                'fecha' => $fecha,
                'etiqueta' => Carbon::createFromFormat(
                    'Y-m-d',
                    $fecha,
                    self::ZONA_HORARIA
                )->format('d/m'),
                'cantidad' => $grupo->count(),
                'total' => (float) $grupo->sum('total'),
            ])
            ->sortBy('fecha')
            ->values()
            ->take(-14)
            ->values();

        $maximo = max(1, (float) ($totales->max('total') ?? 0));

        return $totales->map(function (array $dia) use ($maximo) {
            $dia['porcentaje'] = max(4, ($dia['total'] / $maximo) * 100);

            return $dia;
        });
    }
}
