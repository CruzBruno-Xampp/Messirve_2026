<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reportes | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/reportes/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>

<body class="con-barra-lateral">
    @include('partials.barra-lateral')

    <main class="pagina-reportes">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Información para decidir</p>
                <h1>Reportes de Messirve</h1>
                <p class="encabezado-principal__descripcion">
                    Consultá las ventas por período, sucursal, vendedor y estado.
                </p>
            </div>

            <span class="encabezado-principal__estado">
                <span aria-hidden="true"></span>
                Datos actualizados
            </span>
        </header>

        <nav class="navegacion-reportes" aria-label="Secciones de reportes">
            <a
                href="{{ route('reportes.index') }}"
                class="navegacion-reportes__enlace navegacion-reportes__enlace--activo"
                aria-current="page"
            >
                Reporte de ventas
            </a>
            <a
                href="{{ route('reportes.stock') }}"
                class="navegacion-reportes__enlace"
            >
                Alertas de stock
            </a>
        </nav>

        @if ($errors->any())
            <section class="alerta alerta--error" role="alert">
                <strong>No se pudo aplicar el filtro.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="panel panel--filtros">
            <div class="panel__cabecera">
                <span class="panel__numero">01</span>
                <div>
                    <h2>Filtros de consulta</h2>
                    <p>Elegí qué operaciones querés analizar.</p>
                </div>
            </div>

            <form
                action="{{ route('reportes.index') }}"
                method="GET"
                class="filtros"
                id="filtros-reportes"
                data-filtros-automaticos
            >
                <div class="campo">
                    <label for="desde">Desde</label>
                    <input
                        type="date"
                        id="desde"
                        name="desde"
                        value="{{ old('desde', $filtros['desde']) }}"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="hasta">Hasta</label>
                    <input
                        type="date"
                        id="hasta"
                        name="hasta"
                        value="{{ old('hasta', $filtros['hasta']) }}"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="idsucursal">Sucursal</label>

                    @if ($esDuenoGlobal)
                        <select id="idsucursal" name="idsucursal">
                            <option value="">Todas las sucursales</option>
                            @foreach ($sucursales as $sucursal)
                                <option
                                    value="{{ $sucursal->idsucursal }}"
                                    @selected((string) ($filtros['idsucursal'] ?? '') === (string) $sucursal->idsucursal)
                                >
                                    {{ $sucursal->nombre }} — {{ $sucursal->tipo }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <div class="campo-fijo">
                            <strong>{{ $sucursalUsuario?->nombre ?? 'Sucursal asignada' }}</strong>
                            <span>Tu reporte está limitado a esta ubicación.</span>
                        </div>
                    @endif
                </div>

                <div class="campo">
                    <label for="idusuario">Vendedor</label>
                    <select id="idusuario" name="idusuario">
                        <option value="">Todos los vendedores</option>
                        @foreach ($usuarios as $vendedor)
                            <option
                                value="{{ $vendedor->id }}"
                                @selected((string) ($filtros['idusuario'] ?? '') === (string) $vendedor->id)
                            >
                                {{ $vendedor->nombre }} {{ $vendedor->apellido }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="">Todos los estados</option>
                        @foreach (['CONFIRMADA', 'COMPLETADA', 'PENDIENTE', 'ANULADA', 'CANCELADA'] as $estado)
                            <option
                                value="{{ $estado }}"
                                @selected(($filtros['estado'] ?? '') === $estado)
                            >
                                {{ ucfirst(strtolower($estado)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo campo--buscar">
                    <label for="buscar">Venta, cliente o vendedor</label>
                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $filtros['buscar'] ?? '' }}"
                        placeholder="Ejemplo: V-000001 o Ramiro"
                        maxlength="100"
                    >
                </div>

                <div class="filtros__acciones filtros__acciones--automaticas">
                    <span class="filtros__estado" id="estado-filtros" aria-live="polite">
                        Los filtros se aplican automáticamente.
                    </span>
                    <a href="{{ route('reportes.index') }}" class="boton boton--secundario">
                        Limpiar
                    </a>
                </div>
            </form>
        </section>

        <section class="resumen" aria-label="Resumen del período">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Total vendido</span>
                <strong class="resumen__valor resumen__valor--importe">
                    $ {{ number_format($resumen['totalVendido'], 2, ',', '.') }}
                </strong>
                <small>Sin contar operaciones anuladas</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Ventas válidas</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['ventasValidas'], 0, ',', '.') }}
                </strong>
                <small>{{ number_format($resumen['operaciones'], 0, ',', '.') }} operaciones encontradas</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Promedio por venta</span>
                <strong class="resumen__valor resumen__valor--importe">
                    $ {{ number_format($resumen['promedio'], 2, ',', '.') }}
                </strong>
                <small>Importe promedio del período</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Unidades vendidas</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['unidades'], 0, ',', '.') }}
                </strong>
                <small>{{ number_format($resumen['anuladas'], 0, ',', '.') }} ventas anuladas</small>
            </article>
        </section>

        <section class="panel panel--grafico">
            <div class="panel__cabecera panel__cabecera--separada">
                <div class="panel__cabecera-titulo">
                    <span class="panel__numero">02</span>
                    <div>
                        <h2>Movimiento por día</h2>
                        <p>Se muestran como máximo los últimos 14 días con ventas.</p>
                    </div>
                </div>
                <span class="panel__dato">{{ $ventasPorDia->count() }} días con actividad</span>
            </div>

            @if ($ventasPorDia->isEmpty())
                <div class="estado-vacio estado-vacio--compacto">
                    <strong>No hay ventas para graficar</strong>
                    <p>Probá con otro período o quitá alguno de los filtros.</p>
                </div>
            @else
                <div
                    class="grafico-linea-contenedor"
                    id="grafico-ventas-linea"
                    role="img"
                    aria-label="Evolución del total vendido por día"
                >
                    @foreach ($ventasPorDia as $dia)
                        <span
                            class="grafico-linea__dato"
                            data-etiqueta="{{ $dia['etiqueta'] }}"
                            data-total="{{ $dia['total'] }}"
                            data-cantidad="{{ $dia['cantidad'] }}"
                            hidden
                        ></span>
                    @endforeach
                </div>
                <div class="grafico-linea__leyenda" aria-hidden="true">
                    <span></span>
                    Total vendido por día
                </div>
            @endif
        </section>

        <section class="panel panel--ranking">
            <div class="panel__cabecera panel__cabecera--separada">
                <div class="panel__cabecera-titulo">
                    <span class="panel__numero">03</span>
                    <div>
                        <h2>Ranking de productos vendidos</h2>
                        <p>Los cinco productos con más unidades vendidas en el período.</p>
                    </div>
                </div>
                <span class="panel__dato">Sin ventas anuladas</span>
            </div>

            @if ($topProductos->isEmpty())
                <div class="estado-vacio estado-vacio--compacto">
                    <strong>No hay productos para mostrar</strong>
                    <p>El período seleccionado no tiene ventas válidas con productos.</p>
                </div>
            @else
                <div class="ranking-productos">
                    @foreach ($topProductos as $producto)
                        <article class="ranking-productos__fila">
                            <span class="ranking-productos__posicion">
                                {{ str_pad((string) $producto->posicion, 2, '0', STR_PAD_LEFT) }}
                            </span>

                            <div class="ranking-productos__producto">
                                <strong>{{ $producto->descripcion }}</strong>
                                <span>{{ $producto->codigointerno ?: 'Sin código interno' }}</span>
                                <div class="ranking-productos__barra" aria-hidden="true">
                                    <span
                                        style="width: {{ number_format($producto->porcentaje, 2, '.', '') }}%"
                                    ></span>
                                </div>
                            </div>

                            <div class="ranking-productos__dato">
                                <span>Unidades</span>
                                <strong>{{ number_format((int) $producto->unidades_vendidas, 0, ',', '.') }}</strong>
                            </div>

                            <div class="ranking-productos__dato ranking-productos__dato--ventas">
                                <span>Ventas</span>
                                <strong>{{ number_format((int) $producto->cantidad_ventas, 0, ',', '.') }}</strong>
                            </div>

                            <div class="ranking-productos__dato ranking-productos__dato--importe">
                                <span>Importe generado</span>
                                <strong>
                                    $ {{ number_format((float) $producto->importe_generado, 2, ',', '.') }}
                                </strong>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="panel panel--tabla">
            <div class="panel__cabecera panel__cabecera--separada">
                <div class="panel__cabecera-titulo">
                    <span class="panel__numero">04</span>
                    <div>
                        <h2>Ventas encontradas</h2>
                        <p>Las operaciones más recientes aparecen primero.</p>
                    </div>
                </div>

                <div class="panel__acciones">
                    <span class="panel__dato">
                        {{ $ventas->firstItem() ?? 0 }}–{{ $ventas->lastItem() ?? 0 }}
                        de {{ $ventas->total() }}
                    </span>

                    <a
                        href="{{ route('reportes.ventas.imprimir', array_filter([
                            'desde' => $filtros['desde'] ?? null,
                            'hasta' => $filtros['hasta'] ?? null,
                            'idsucursal' => $filtros['idsucursal'] ?? null,
                            'idusuario' => $filtros['idusuario'] ?? null,
                            'estado' => $filtros['estado'] ?? null,
                            'buscar' => $filtros['buscar'] ?? null,
                        ], static fn ($valor) => $valor !== null && $valor !== '')) }}"
                        class="boton boton--imprimir"
                        target="_blank"
                        rel="noopener"
                    >
                        Imprimir reporte
                    </a>

                    <a
                        href="{{ route('reportes.ventas.exportar', array_filter([
                            'desde' => $filtros['desde'] ?? null,
                            'hasta' => $filtros['hasta'] ?? null,
                            'idsucursal' => $filtros['idsucursal'] ?? null,
                            'idusuario' => $filtros['idusuario'] ?? null,
                            'estado' => $filtros['estado'] ?? null,
                            'buscar' => $filtros['buscar'] ?? null,
                        ], static fn ($valor) => $valor !== null && $valor !== '')) }}"
                        class="boton boton--exportar"
                    >
                        Descargar CSV
                    </a>
                </div>
            </div>

            @if ($ventas->isEmpty())
                <div class="estado-vacio">
                    <span class="estado-vacio__icono" aria-hidden="true">⌕</span>
                    <h3>No encontramos operaciones</h3>
                    <p>Cambiá las fechas o limpiá los filtros para ampliar la búsqueda.</p>
                </div>
            @else
                <div class="tabla-contenedor" tabindex="0">
                    <table class="tabla-reportes">
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
                                    $estadoVenta = strtoupper((string) $venta->estado);
                                    $claseEstado = match ($estadoVenta) {
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
                                    $fechaLocal = $venta->fecha
                                        ? $venta->fecha->copy()->setTimezone($zonaHoraria)
                                        : null;
                                @endphp

                                <tr>
                                    <td><span class="codigo">{{ $numeroVenta }}</span></td>
                                    <td>
                                        <strong class="dato-principal">
                                            {{ $fechaLocal?->format('d/m/Y') ?? '—' }}
                                        </strong>
                                        <span class="dato-secundario">
                                            {{ $fechaLocal?->format('H:i') ?? '' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="dato-principal">
                                            @if ($venta->cliente)
                                                {{ $venta->cliente->nombre }} {{ $venta->cliente->apellido }}
                                            @else
                                                Consumidor final
                                            @endif
                                        </strong>
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
                                        <span class="insignia {{ $claseEstado }}">
                                            {{ $estadoVenta ?: 'PENDIENTE' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a
                                            href="{{ route('ventas.detalle', $venta) }}"
                                            class="boton-detalle"
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
                    <nav class="paginacion" aria-label="Paginación del reporte">
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

    <script src="{{ asset('js/reportes/index.js') }}" defer></script>
</body>
</html>
