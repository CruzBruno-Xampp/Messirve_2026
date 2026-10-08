<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ventas | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/ventas/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ventas/detalle.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>

<body class="con-barra-lateral">
    @php
        $usuarioAdmin = auth('web')->user();
        $puedeCrearVentas = $usuarioAdmin?->tienePermiso('ventas.crear') ?? false;
        $mensajeExito = session('success') ?? session('exito');
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-ventas">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Administración comercial</p>
                <h1>Ventas de Messirve</h1>
                <p class="encabezado-principal__descripcion">
                    Consultá las operaciones realizadas y el movimiento comercial del día.
                </p>
            </div>

            @if ($puedeCrearVentas)
                <a href="{{ route('ventas.crear') }}" class="boton boton--primario">
                    + Nueva venta
                </a>
            @endif
        </header>

        @if ($mensajeExito)
            <section class="alerta alerta--exito" role="status">
                <strong>Operación realizada.</strong>
                <span>{{ $mensajeExito }}</span>
            </section>
        @endif

        @if (session('error'))
            <section class="alerta alerta--error" role="alert">
                <strong>No se pudo continuar.</strong>
                <span>{{ session('error') }}</span>
            </section>
        @endif

        <section class="resumen" aria-label="Resumen de ventas">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Ventas de hoy</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['cantidadHoy'], 0, ',', '.') }}
                </strong>
                <small>Operaciones no anuladas</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Total del día</span>
                <strong class="resumen__valor resumen__valor--importe">
                    $ {{ number_format($resumen['totalHoy'], 2, ',', '.') }}
                </strong>
                <small>Importe acumulado durante hoy</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Ventas pendientes</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['pendientes'], 0, ',', '.') }}
                </strong>
                <small>Operaciones que todavía deben confirmarse</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa">
                <span class="resumen__icono" aria-hidden="true">i</span>
                <div>
                    <strong>Módulo de Ventas habilitado</strong>
                    <p>
                        @if ($puedeCrearVentas)
                            Podés registrar ventas y consultar el detalle de cada operación.
                        @else
                            Tu rol permite consultar las ventas y sus productos.
                        @endif
                    </p>
                </div>
            </article>
        </section>

        <section class="panel panel--tabla">
            <div class="panel__cabecera panel__cabecera--tabla">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Últimas ventas</h2>
                        <p>Las operaciones más recientes aparecen primero.</p>
                    </div>
                </div>

                <span class="resultado-cantidad">
                    {{ $ventas->firstItem() ?? 0 }}–{{ $ventas->lastItem() ?? 0 }}
                    de {{ $ventas->total() }}
                </span>
            </div>

            @if ($ventas->isEmpty())
                <div class="estado-vacio">
                    <span class="estado-vacio__icono" aria-hidden="true">$</span>
                    <h3>Todavía no hay ventas registradas</h3>
                    <p>La primera operación aparecerá acá cuando se confirme una venta.</p>
                </div>
            @else
                <div class="tabla-contenedor" tabindex="0">
                    <table class="tabla-ventas tabla-ventas--con-accion">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Vendedor</th>
                                <th>Sucursal</th>
                                <th>Canal</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($ventas as $venta)
                                @php
                                    $estado = strtoupper((string) $venta->estado);
                                    $claseEstado = match ($estado) {
                                        'CONFIRMADA', 'COMPLETADA' => 'insignia--exito',
                                        'ANULADA', 'CANCELADA' => 'insignia--error',
                                        default => 'insignia--pendiente',
                                    };
                                    $numeroVenta = 'V-' . str_pad(
                                        (string) $venta->idventa,
                                        6,
                                        '0',
                                        STR_PAD_LEFT
                                    );
                                    $fechaVentaLocal = $venta->fecha
                                        ? $venta->fecha->copy()->setTimezone($zonaHoraria)
                                        : null;
                                @endphp

                                <tr>
                                    <td><span class="codigo">{{ $numeroVenta }}</span></td>
                                    <td>
                                        <strong class="dato-principal">
                                            {{ $fechaVentaLocal?->format('d/m/Y') ?? '—' }}
                                        </strong>
                                        <span class="dato-secundario">
                                            {{ $fechaVentaLocal?->format('H:i') ?? '' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="dato-principal">
                                            @if ($venta->cliente)
                                                {{ $venta->cliente->nombre }}
                                                {{ $venta->cliente->apellido }}
                                            @else
                                                Consumidor final
                                            @endif
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="dato-principal">
                                            {{ $venta->usuario?->nombre ?? 'Usuario no disponible' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="dato-principal">
                                            {{ $venta->sucursal?->nombre ?? 'Sin sucursal' }}
                                        </span>
                                    </td>
                                    <td>{{ $venta->canal }}</td>
                                    <td class="importe">
                                        $ {{ number_format((float) $venta->total, 2, ',', '.') }}
                                    </td>
                                    <td>
                                        <span class="insignia {{ $claseEstado }}">
                                            {{ $estado ?: 'PENDIENTE' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a
                                            href="{{ route('ventas.detalle', $venta) }}"
                                            class="boton-ver-detalle"
                                            aria-label="Ver detalle de la venta {{ $numeroVenta }}"
                                        >
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($ventas->hasPages())
                    <nav class="paginacion" aria-label="Paginación de ventas">
                        @if ($ventas->onFirstPage())
                            <span class="paginacion__boton paginacion__boton--deshabilitado">
                                ← Anterior
                            </span>
                        @else
                            <a href="{{ $ventas->previousPageUrl() }}" class="paginacion__boton">
                                ← Anterior
                            </a>
                        @endif

                        <span class="paginacion__actual">
                            Página {{ $ventas->currentPage() }} de {{ $ventas->lastPage() }}
                        </span>

                        @if ($ventas->hasMorePages())
                            <a href="{{ $ventas->nextPageUrl() }}" class="paginacion__boton">
                                Siguiente →
                            </a>
                        @else
                            <span class="paginacion__boton paginacion__boton--deshabilitado">
                                Siguiente →
                            </span>
                        @endif
                    </nav>
                @endif
            @endif
        </section>
    </main>
</body>
</html>
