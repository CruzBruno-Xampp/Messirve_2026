<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body class="con-barra-lateral">
    @include('partials.barra-lateral')

    @php
        $puedeCrearVenta = $usuario?->tienePermiso('ventas.crear') ?? false;
        $puedeVerVentas = $usuario?->tienePermiso('ventas.ver') ?? false;
        $puedeVerCompras = $usuario?->tienePermiso('compras.ver') ?? false;
        $puedeCrearCompra = $usuario?->tienePermiso('compras.crear') ?? false;
        $puedeVerStock = $usuario?->tienePermiso('stock.ver') ?? false;
    @endphp

    <main class="dashboard">
        <header class="dashboard__encabezado">
            <div>
                <p class="dashboard__seccion">Panel administrativo</p>
                <h1>Dashboard</h1>
                <p class="dashboard__descripcion">
                    Resumen de la actividad de Messirve al {{ $fechaActual->format('d/m/Y') }}.
                </p>
            </div>

            <div class="dashboard__alcance">
                <span>Vista actual</span>
                <strong>
                    {{ $esDuenoGlobal ? 'Todas las sucursales' : ($sucursalActual?->nombre ?? 'Sin sucursal') }}
                </strong>
            </div>
        </header>

        <section class="metricas" aria-label="Resumen del día">
            <article class="metrica">
                <div class="metrica__icono metrica__icono--azul" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4 5h16v14H4V5Zm2 2v10h12V7H6Zm2 2h3v2H8V9Zm5 0h3v2h-3V9Zm-5 4h3v2H8v-2Zm5 0h3v2h-3v-2Z"/>
                    </svg>
                </div>
                <div>
                    <span>Ventas de hoy</span>
                    <strong>{{ $resumen['ventasHoy'] === null ? '—' : number_format($resumen['ventasHoy'], 0, ',', '.') }}</strong>
                    <small>{{ $resumen['ventasHoy'] === null ? 'Sin permiso de consulta' : 'Operaciones no anuladas' }}</small>
                </div>
            </article>

            <article class="metrica">
                <div class="metrica__icono metrica__icono--verde" aria-hidden="true">↗</div>
                <div>
                    <span>Total vendido hoy</span>
                    <strong>{{ $resumen['totalHoy'] === null ? '—' : '$ ' . number_format($resumen['totalHoy'], 2, ',', '.') }}</strong>
                    <small>{{ $resumen['totalHoy'] === null ? 'Sin permiso de consulta' : 'Importe cobrado' }}</small>
                </div>
            </article>

            <article class="metrica">
                <div class="metrica__icono metrica__icono--amarillo" aria-hidden="true">C</div>
                <div>
                    <span>Compras pendientes</span>
                    <strong>{{ $resumen['comprasPendientes'] === null ? '—' : number_format($resumen['comprasPendientes'], 0, ',', '.') }}</strong>
                    <small>{{ $resumen['comprasPendientes'] === null ? 'Sin permiso de consulta' : 'Pendientes o parciales' }}</small>
                </div>
            </article>

            <article class="metrica">
                <div class="metrica__icono metrica__icono--rojo" aria-hidden="true">!</div>
                <div>
                    <span>Stock bajo</span>
                    <strong>{{ $resumen['stockBajo'] === null ? '—' : number_format($resumen['stockBajo'], 0, ',', '.') }}</strong>
                    <small>{{ $resumen['stockBajo'] === null ? 'Sin permiso de consulta' : 'En mínimo o por debajo' }}</small>
                </div>
            </article>
        </section>

        @if ($puedeCrearVenta || $puedeCrearCompra || $puedeVerStock)
            <section class="accesos" aria-label="Accesos rápidos">
                <div>
                    <span class="accesos__etiqueta">Accesos rápidos</span>
                    <strong>Ir directo a una tarea</strong>
                </div>

                <div class="accesos__botones">
                    @if ($puedeCrearVenta)
                        <a href="{{ route('ventas.crear') }}" class="acceso acceso--principal">+ Nueva venta</a>
                    @endif

                    @if ($puedeCrearCompra)
                        <a href="{{ route('compras.crear') }}" class="acceso">+ Nueva compra</a>
                    @endif

                    @if ($puedeVerStock)
                        <a href="{{ route('stock.index') }}" class="acceso">Ver stock</a>
                    @endif
                </div>
            </section>
        @endif

        @if ($puedeVerVentas)
        <section class="panel panel--grafico">
            <header class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Ventas de los últimos 7 días</h2>
                        <p>Importe total por día, sin operaciones anuladas.</p>
                    </div>
                </div>

                @if ($puedeVerVentas)
                    <a href="{{ route('ventas.index') }}" class="panel__enlace">Ver ventas</a>
                @endif
            </header>

            <div class="grafico-lineal" id="grafico-ventas" aria-label="Gráfico lineal de ventas de los últimos siete días">
                <div class="grafico-lineal__area">
                    <svg class="grafico-lineal__svg" viewBox="0 0 700 230" preserveAspectRatio="none" aria-hidden="true">
                        <g class="grafico-lineal__grilla"></g>
                        <path class="grafico-lineal__area-relleno" d=""></path>
                        <polyline class="grafico-lineal__linea" points=""></polyline>
                        <g class="grafico-lineal__puntos"></g>
                    </svg>
                </div>

                <div class="grafico-lineal__datos">
                    @foreach ($ventasPorDia as $dia)
                        <div
                            class="grafico-lineal__dato"
                            data-valor="{{ (float) $dia['total'] }}"
                            data-etiqueta="{{ $dia['etiqueta'] }}"
                            data-importe="$ {{ number_format($dia['total'], 0, ',', '.') }}"
                        >
                            <strong>$ {{ number_format($dia['total'], 0, ',', '.') }}</strong>
                            <span>{{ $dia['etiqueta'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @endif

        @if ($puedeVerVentas || $puedeVerStock)
        <div class="dashboard__grilla">
            @if ($puedeVerVentas)
                <section class="panel">
                <header class="panel__cabecera">
                    <div>
                        <span class="panel__numero">02</span>
                        <div>
                            <h2>Últimas ventas</h2>
                            <p>Las operaciones más recientes.</p>
                        </div>
                    </div>

                    @if ($puedeVerVentas)
                        <a href="{{ route('ventas.index') }}" class="panel__enlace">Ver todas</a>
                    @endif
                </header>

                <div class="lista lista--ventas">
                    @forelse ($ultimasVentas as $venta)
                        @php
                            $cliente = $venta->cliente
                                ? trim($venta->cliente->nombre . ' ' . $venta->cliente->apellido)
                                : 'Consumidor final';
                        @endphp

                        <article class="fila">
                            <div class="fila__principal">
                                <strong>V-{{ str_pad((string) $venta->idventa, 6, '0', STR_PAD_LEFT) }}</strong>
                                <span>{{ $cliente }}</span>
                            </div>
                            <div class="fila__secundario">
                                <strong>$ {{ number_format((float) $venta->total, 2, ',', '.') }}</strong>
                                <small>{{ $venta->fecha?->format('d/m H:i') }}</small>
                            </div>
                            <span class="estado estado--{{ strtolower((string) $venta->estado) }}">
                                {{ $venta->estado }}
                            </span>
                        </article>
                    @empty
                        <div class="vacio">
                            <strong>No hay ventas registradas.</strong>
                            <span>Cuando se confirme una venta aparecerá acá.</span>
                        </div>
                    @endforelse
                </div>
            </section>

            @endif

            @if ($puedeVerStock)
                <section class="panel">
                <header class="panel__cabecera">
                    <div>
                        <span class="panel__numero">03</span>
                        <div>
                            <h2>Alertas de stock</h2>
                            <p>Productos en mínimo o por debajo.</p>
                        </div>
                    </div>

                    @if ($puedeVerStock)
                        <a href="{{ route('stock.index') }}" class="panel__enlace">Ver stock</a>
                    @endif
                </header>

                <div class="lista">
                    @forelse ($alertasStock as $stock)
                        <article class="fila fila--stock">
                            <div class="fila__principal">
                                <strong>{{ $stock->producto?->codigointerno ?? 'Sin código' }}</strong>
                                <span>{{ $stock->producto?->descripcion ?? 'Producto no disponible' }}</span>
                                @if ($esDuenoGlobal)
                                    <small>{{ $stock->sucursal?->nombre ?? 'Sin ubicación' }}</small>
                                @endif
                            </div>

                            <div class="stock-cantidad {{ $stock->stockactual <= 0 ? 'stock-cantidad--critico' : '' }}">
                                <strong>{{ $stock->stockactual }}</strong>
                                <span>mín. {{ $stock->stockminimo }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="vacio vacio--correcto">
                            <strong>Stock en orden.</strong>
                            <span>No hay productos en el mínimo o por debajo.</span>
                        </div>
                    @endforelse
                </div>
            </section>
            @endif
        </div>
        @endif

        @if ($puedeVerCompras)
        <section class="panel panel--compras">
            <header class="panel__cabecera">
                <div>
                    <span class="panel__numero">04</span>
                    <div>
                        <h2>Compras pendientes</h2>
                        <p>Órdenes que todavía requieren recepción o resolución.</p>
                    </div>
                </div>

                @if ($puedeVerCompras)
                    <a href="{{ route('compras.index') }}" class="panel__enlace">Ver compras</a>
                @endif
            </header>

            <div class="compras-grid">
                @forelse ($comprasPendientes as $compra)
                    <article class="compra-card">
                        <div class="compra-card__cabecera">
                            <strong>COM-{{ str_pad((string) $compra->idcompra, 6, '0', STR_PAD_LEFT) }}</strong>
                            <span class="estado estado--{{ strtolower((string) $compra->estado) }}">{{ $compra->estado }}</span>
                        </div>
                        <span>{{ $compra->proveedor?->razonsocial ?? 'Proveedor no disponible' }}</span>
                        <small>
                            {{ $compra->fecha?->format('d/m/Y H:i') }}
                            @if ($esDuenoGlobal && $compra->sucursal)
                                · {{ $compra->sucursal->nombre }}
                            @endif
                        </small>
                        <strong class="compra-card__total">$ {{ number_format((float) $compra->total, 2, ',', '.') }}</strong>
                    </article>
                @empty
                    <div class="vacio vacio--correcto compras-grid__vacio">
                        <strong>No hay compras pendientes.</strong>
                        <span>Las órdenes pendientes o parciales aparecerán acá.</span>
                    </div>
                @endforelse
            </div>
        </section>
        @endif
    </main>

    <script src="{{ asset('js/dashboard.js') }}" defer></script>
</body>
</html>
