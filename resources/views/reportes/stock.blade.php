<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Alertas de stock | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/reportes/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>

<body class="con-barra-lateral">
    @include('partials.barra-lateral')

    <main class="pagina-reportes">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Control de existencias</p>
                <h1>Alertas de stock</h1>
                <p class="encabezado-principal__descripcion">
                    Detectá productos agotados, por debajo del mínimo o en su capacidad máxima.
                </p>
            </div>

            <span class="encabezado-principal__estado">
                <span aria-hidden="true"></span>
                Stock actualizado
            </span>
        </header>

        <nav class="navegacion-reportes" aria-label="Secciones de reportes">
            <a
                href="{{ route('reportes.index') }}"
                class="navegacion-reportes__enlace"
            >
                Reporte de ventas
            </a>
            <a
                href="{{ route('reportes.stock') }}"
                class="navegacion-reportes__enlace navegacion-reportes__enlace--activo"
                aria-current="page"
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
                    <h2>Filtrar alertas</h2>
                    <p>Buscá un producto o revisá una ubicación determinada.</p>
                </div>
            </div>

            <form action="{{ route('reportes.stock') }}" method="GET" class="filtros filtros--stock">
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
                            <span>Solo se muestran existencias de esta ubicación.</span>
                        </div>
                    @endif
                </div>

                <div class="campo">
                    <label for="alerta">Tipo de alerta</label>
                    <select id="alerta" name="alerta">
                        <option value="">Todas las alertas</option>
                        <option value="SIN_STOCK" @selected(($filtros['alerta'] ?? '') === 'SIN_STOCK')>
                            Sin stock
                        </option>
                        <option value="BAJO_MINIMO" @selected(($filtros['alerta'] ?? '') === 'BAJO_MINIMO')>
                            Stock bajo
                        </option>
                        <option value="MAXIMO" @selected(($filtros['alerta'] ?? '') === 'MAXIMO')>
                            Máximo alcanzado
                        </option>
                    </select>
                </div>

                <div class="campo campo--buscar">
                    <label for="buscar">Producto</label>
                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $filtros['buscar'] ?? '' }}"
                        placeholder="Código, descripción o código de barras"
                        maxlength="100"
                    >
                </div>

                <div class="filtros__acciones">
                    <button type="submit" class="boton boton--primario">
                        Aplicar filtros
                    </button>
                    <a href="{{ route('reportes.stock') }}" class="boton boton--secundario">
                        Limpiar
                    </a>
                </div>
            </form>
        </section>

        <section class="resumen" aria-label="Resumen de alertas de stock">
            <article class="resumen__tarjeta resumen__tarjeta--critica">
                <span class="resumen__etiqueta">Sin stock</span>
                <strong class="resumen__valor">
                    {{ number_format($resumenStock['sinStock'], 0, ',', '.') }}
                </strong>
                <small>Productos que no poseen unidades disponibles</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--advertencia">
                <span class="resumen__etiqueta">Stock bajo</span>
                <strong class="resumen__valor">
                    {{ number_format($resumenStock['bajoMinimo'], 0, ',', '.') }}
                </strong>
                <small>Productos que alcanzaron o bajaron su mínimo</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--maximo">
                <span class="resumen__etiqueta">Máximo alcanzado</span>
                <strong class="resumen__valor">
                    {{ number_format($resumenStock['maximo'], 0, ',', '.') }}
                </strong>
                <small>Conviene evitar nuevas compras por el momento</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Alertas detectadas</span>
                <strong class="resumen__valor">
                    {{ number_format($resumenStock['total'], 0, ',', '.') }}
                </strong>
                <small>Total según la sucursal y búsqueda seleccionadas</small>
            </article>
        </section>

        <section class="panel panel--tabla">
            <div class="panel__cabecera panel__cabecera--separada">
                <div class="panel__cabecera-titulo">
                    <span class="panel__numero">02</span>
                    <div>
                        <h2>Productos que necesitan atención</h2>
                        <p>Las situaciones más urgentes aparecen primero.</p>
                    </div>
                </div>

                <span class="panel__dato">
                    {{ $stocks->firstItem() ?? 0 }}–{{ $stocks->lastItem() ?? 0 }}
                    de {{ $stocks->total() }}
                </span>
            </div>

            @if ($stocks->isEmpty())
                <div class="estado-vacio">
                    <span class="estado-vacio__icono estado-vacio__icono--exito" aria-hidden="true">✓</span>
                    <h3>No hay alertas con estos filtros</h3>
                    <p>Las existencias encontradas se encuentran dentro de los límites configurados.</p>
                </div>
            @else
                <div class="tabla-contenedor" tabindex="0">
                    <table class="tabla-reportes tabla-reportes--stock">
                        <thead>
                            <tr>
                                <th>Alerta</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Sucursal</th>
                                <th>Actual</th>
                                <th>Mínimo</th>
                                <th>Máximo</th>
                                <th>Recomendación</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stocks as $stock)
                                @php
                                    $actual = (int) $stock->stockactual;
                                    $minimo = (int) $stock->stockminimo;
                                    $maximo = (int) $stock->stockmaximo;

                                    if ($actual <= 0) {
                                        $tipoAlerta = 'Sin stock';
                                        $claseAlerta = 'alerta-stock--critica';
                                        $recomendacion = $maximo > 0
                                            ? 'Reponer hasta ' . $maximo . ' unidades'
                                            : ($minimo > 0
                                                ? 'Reponer al menos ' . $minimo . ' unidades'
                                                : 'Configurar mínimo y reponer');
                                    } elseif ($minimo > 0 && $actual <= $minimo) {
                                        $tipoAlerta = 'Stock bajo';
                                        $claseAlerta = 'alerta-stock--advertencia';
                                        $cantidadSugerida = $maximo > $actual
                                            ? $maximo - $actual
                                            : max(1, $minimo - $actual + 1);
                                        $recomendacion = 'Reponer ' . $cantidadSugerida . ' unidades';
                                    } else {
                                        $tipoAlerta = 'Máximo alcanzado';
                                        $claseAlerta = 'alerta-stock--maximo';
                                        $recomendacion = 'Evitar nuevas compras';
                                    }
                                @endphp

                                <tr>
                                    <td>
                                        <span class="alerta-stock {{ $claseAlerta }}">
                                            {{ $tipoAlerta }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="codigo">
                                            {{ $stock->producto?->codigointerno ?? 'Sin código' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="dato-principal">
                                            {{ $stock->producto?->descripcion ?? 'Producto no disponible' }}
                                        </strong>
                                        <span class="dato-secundario">
                                            {{ $stock->producto?->codigobarra ?: 'Sin código de barras' }}
                                        </span>
                                    </td>
                                    <td>{{ $stock->sucursal?->nombre ?? 'Sin sucursal' }}</td>
                                    <td class="stock-numero stock-numero--actual">{{ $actual }}</td>
                                    <td class="stock-numero">{{ $minimo }}</td>
                                    <td class="stock-numero">{{ $maximo }}</td>
                                    <td>
                                        <strong class="recomendacion-stock">
                                            {{ $recomendacion }}
                                        </strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($stocks->hasPages())
                    <nav class="paginacion" aria-label="Paginación de alertas">
                        @if ($stocks->onFirstPage())
                            <span class="paginacion__boton paginacion__boton--deshabilitado">
                                ← Anterior
                            </span>
                        @else
                            <a href="{{ $stocks->previousPageUrl() }}" class="paginacion__boton">
                                ← Anterior
                            </a>
                        @endif

                        <span class="paginacion__actual">
                            Página {{ $stocks->currentPage() }} de {{ $stocks->lastPage() }}
                        </span>

                        @if ($stocks->hasMorePages())
                            <a href="{{ $stocks->nextPageUrl() }}" class="paginacion__boton">
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
