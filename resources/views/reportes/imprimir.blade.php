<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reporte de ventas | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/reportes/imprimir.css') }}">
</head>

<body>
    @php
        $parametros = array_filter([
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
            'idsucursal' => $filtros['idsucursal'] ?? null,
            'idusuario' => $filtros['idusuario'] ?? null,
            'estado' => $filtros['estado'] ?? null,
            'buscar' => $filtros['buscar'] ?? null,
        ], static fn ($valor) => $valor !== null && $valor !== '');
    @endphp

    <div class="acciones no-imprimir">
        <a href="{{ route('reportes.index', $parametros) }}" class="boton boton--volver">
            Volver a reportes
        </a>
        <button type="button" class="boton boton--imprimir" onclick="window.print()">
            Imprimir / Guardar PDF
        </button>
    </div>

    <main class="hoja">
        <header class="encabezado">
            <div>
                <p class="marca">MES.SIRVE PINTURERÍA</p>
                <h1>Reporte de ventas</h1>
                <p class="periodo">
                    Período del
                    <strong>{{ \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') }}</strong>
                    al
                    <strong>{{ \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') }}</strong>
                </p>
            </div>

            <div class="emision">
                <span>Emitido</span>
                <strong>{{ $fechaEmision->format('d/m/Y H:i') }}</strong>
                <small>Por {{ $generadoPor ?: 'Usuario del sistema' }}</small>
            </div>
        </header>

        <section class="filtros" aria-label="Filtros utilizados">
            <div>
                <span>Sucursal</span>
                <strong>{{ $sucursalFiltro }}</strong>
            </div>
            <div>
                <span>Vendedor</span>
                <strong>{{ $vendedorFiltro }}</strong>
            </div>
            <div>
                <span>Estado</span>
                <strong>{{ $filtros['estado'] ?? 'Todos los estados' }}</strong>
            </div>
            <div>
                <span>Búsqueda</span>
                <strong>{{ ($filtros['buscar'] ?? '') !== '' ? $filtros['buscar'] : 'Sin búsqueda' }}</strong>
            </div>
        </section>

        <section class="resumen" aria-label="Resumen del período">
            <article>
                <span>Total vendido</span>
                <strong>$ {{ number_format($resumen['totalVendido'], 2, ',', '.') }}</strong>
                <small>Sin operaciones anuladas</small>
            </article>
            <article>
                <span>Ventas válidas</span>
                <strong>{{ number_format($resumen['ventasValidas'], 0, ',', '.') }}</strong>
                <small>De {{ number_format($resumen['operaciones'], 0, ',', '.') }} operaciones</small>
            </article>
            <article>
                <span>Promedio por venta</span>
                <strong>$ {{ number_format($resumen['promedio'], 2, ',', '.') }}</strong>
                <small>Importe promedio</small>
            </article>
            <article>
                <span>Unidades vendidas</span>
                <strong>{{ number_format($resumen['unidades'], 0, ',', '.') }}</strong>
                <small>{{ number_format($resumen['anuladas'], 0, ',', '.') }} anuladas</small>
            </article>
        </section>

        <section class="ranking-impresion">
            <div class="detalle__cabecera">
                <div>
                    <h2>Productos más vendidos</h2>
                    <p>Ranking por unidades, sin contar operaciones anuladas.</p>
                </div>
                <strong>Top {{ $topProductos->count() }}</strong>
            </div>

            @if ($topProductos->isEmpty())
                <div class="sin-resultados sin-resultados--compacto">
                    No hay productos vendidos en el período seleccionado.
                </div>
            @else
                <table class="tabla-ranking">
                    <thead>
                        <tr>
                            <th>Posición</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Unidades</th>
                            <th>Ventas</th>
                            <th>Importe generado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topProductos as $producto)
                            <tr>
                                <td class="numero">#{{ $producto->posicion }}</td>
                                <td>{{ $producto->codigointerno ?: '—' }}</td>
                                <td>{{ $producto->descripcion }}</td>
                                <td>{{ number_format((int) $producto->unidades_vendidas, 0, ',', '.') }}</td>
                                <td>{{ number_format((int) $producto->cantidad_ventas, 0, ',', '.') }}</td>
                                <td class="importe">
                                    $ {{ number_format((float) $producto->importe_generado, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="detalle">
            <div class="detalle__cabecera">
                <div>
                    <h2>Operaciones encontradas</h2>
                    <p>Listado completo según los filtros seleccionados.</p>
                </div>
                <strong>{{ number_format($ventas->count(), 0, ',', '.') }} registros</strong>
            </div>

            @if ($ventas->isEmpty())
                <div class="sin-resultados">
                    No se encontraron ventas para los filtros seleccionados.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Fecha y hora</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Sucursal</th>
                            <th>Canal</th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ventas as $venta)
                            @php
                                $fechaLocal = $venta->fecha
                                    ? $venta->fecha->copy()->setTimezone($zonaHoraria)
                                    : null;
                                $estadoVenta = strtoupper((string) ($venta->estado ?: 'PENDIENTE'));
                                $claseEstado = in_array(
                                    $estadoVenta,
                                    ['ANULADA', 'CANCELADA'],
                                    true
                                ) ? 'estado--anulada' : 'estado--valida';
                            @endphp

                            <tr>
                                <td class="numero">
                                    V-{{ str_pad((string) $venta->idventa, 6, '0', STR_PAD_LEFT) }}
                                </td>
                                <td>
                                    {{ $fechaLocal?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td>
                                    @if ($venta->cliente)
                                        {{ $venta->cliente->nombre }} {{ $venta->cliente->apellido }}
                                    @else
                                        Consumidor final
                                    @endif
                                </td>
                                <td>
                                    {{ $venta->usuario?->nombre ?? 'Usuario no disponible' }}
                                    {{ $venta->usuario?->apellido ?? '' }}
                                </td>
                                <td>{{ $venta->sucursal?->nombre ?? 'Sin sucursal' }}</td>
                                <td>{{ $venta->canal ?: '—' }}</td>
                                <td class="importe">
                                    $ {{ number_format((float) $venta->total, 2, ',', '.') }}
                                </td>
                                <td>
                                    <span class="estado {{ $claseEstado }}">{{ $estadoVenta }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <footer>
            <span>Messirve · Reporte generado desde el sistema administrativo</span>
            <span>{{ $fechaEmision->format('d/m/Y H:i') }}</span>
        </footer>
    </main>
</body>
</html>
