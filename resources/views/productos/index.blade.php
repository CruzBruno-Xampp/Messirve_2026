<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Productos | Messirve</title>

    {{-- Reutiliza la apariencia general del módulo de productos. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/productos/catalogo.css') }}"
    >

    {{-- Ajustes exclusivos de la lista de productos internos. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/productos/index.css') }}"
    >

    {{-- Barra de navegación reutilizable del sector administrativo. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/admin/barra-lateral.css') }}"
    >
    <link rel="stylesheet" href="{{ asset('css/productos/crear-manual.css') }}">
</head>

<body class="con-barra-lateral">

    @php
        $usuarioAdmin = auth('web')->user();
        $puedeVerCatalogo = $usuarioAdmin?->tienePermiso('catalogos.ver') ?? false;
        $puedeImportarCatalogos = $usuarioAdmin?->tienePermiso('catalogos.importar') ?? false;
        $puedeCrearProductos = $usuarioAdmin?->tienePermiso('productos.crear') ?? false;
        $puedeEditarProductos = $usuarioAdmin?->tienePermiso('productos.editar') ?? false;
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos pagina-productos--indice">

        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">
                    Administración de productos
                </p>

                <h1>Productos de Messirve</h1>

                <p class="encabezado-principal__descripcion">
                    Consultá los productos incorporados al sistema, sus datos
                    comerciales y la clasificación confirmada por el dueño.
                </p>
            </div>

            @if ($puedeImportarCatalogos || $puedeCrearProductos)
                <div class="encabezado-principal__acciones acciones-altas-productos">
                    @if ($puedeImportarCatalogos)
                        <a
                            href="{{ route('catalogos.importar') }}"
                            class="boton boton--secundario"
                        >
                            Importar catálogo
                        </a>
                    @endif

                    @if ($puedeCrearProductos)
                        <a
                            href="{{ route('productos.catalogo') }}"
                            class="boton boton--secundario"
                        >
                            Agregar desde catálogo
                        </a>
                        <a
                            href="{{ route('productos.manual.crear') }}"
                            class="boton boton--primario"
                        >
                            Nuevo producto manual
                        </a>
                    @endif
                </div>
            @endif
        </header>

        @if (session('success'))
            <section class="alerta alerta--exito" role="status">
                <strong>Operación realizada.</strong>
                <span>{{ session('success') }}</span>
            </section>
        @endif

        @if (session('error'))
            <section class="alerta alerta--error" role="alert">
                <strong>No se pudo continuar.</strong>
                <span>{{ session('error') }}</span>
            </section>
        @endif

        <section class="resumen resumen--productos">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Productos encontrados</span>

                <strong class="resumen__valor">
                    {{ number_format($productos->total(), 0, ',', '.') }}
                </strong>

                <small>Según los filtros seleccionados</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Artículos pendientes</span>

                <strong class="resumen__valor">
                    {{ number_format($cantidadPendientes, 0, ',', '.') }}
                </strong>

                <small>Esperan revisión desde un catálogo activo</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa">
                <span class="resumen__icono">i</span>

                <div>
                    <strong>Producto interno listo para trabajar</strong>

                    <p>
                        Podés agregar productos desde un catálogo o cargarlos manualmente.
                        Comienzan con stock cero en el depósito central;
                        las existencias se registran desde Stock.
                    </p>
                </div>
            </article>
        </section>

        <section class="panel">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>

                    <div>
                        <h2>Buscar productos</h2>

                        <p>
                            Buscá por descripción, código interno, código del
                            fabricante o código de barras.
                        </p>
                    </div>
                </div>
            </div>

            <form
                id="filtros-productos"
                action="{{ route('productos.index') }}"
                method="GET"
                class="filtros filtros--productos"
            >
                <div class="campo campo--busqueda">
                    <label for="buscar">Buscar producto</label>

                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $busqueda }}"
                        placeholder="Ejemplo: esmalte, PRD-0001 o 779080..."
                        maxlength="255"
                    >
                </div>

                <div class="campo">
                    <label for="estado">Estado</label>

                    <select id="estado" name="estado">
                        <option value="1" @selected($estado === '1')>
                            Activos
                        </option>

                        <option value="0" @selected($estado === '0')>
                            Inactivos
                        </option>

                        <option value="todos" @selected($estado === 'todos')>
                            Todos
                        </option>
                    </select>
                </div>

                <div class="filtros__acciones">
                    @if ($busqueda !== '' || $estado !== '1')
                        <a
                            href="{{ route('productos.index') }}"
                            class="boton boton--secundario"
                        >
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <section class="panel panel--tabla">
            <div class="panel__cabecera panel__cabecera--tabla">
                <div>
                    <span class="panel__numero">02</span>

                    <div>
                        <h2>Listado de productos</h2>

                        <p>
                            La barra horizontal permite revisar todas las
                            columnas sin cortar la información.
                        </p>
                    </div>
                </div>

                <span class="resultado-cantidad">
                    {{ $productos->firstItem() ?? 0 }}–{{ $productos->lastItem() ?? 0 }}
                    de {{ $productos->total() }}
                </span>
            </div>

            @if ($productos->isEmpty())
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">i</span>

                    <h3>No encontramos productos</h3>

                    <p>
                        No hay productos que coincidan con los filtros actuales.
                        Podés limpiar la búsqueda o revisar los artículos que
                        todavía están pendientes en los catálogos.
                    </p>

                    <div class="estado-vacio__acciones">
                        <a
                            href="{{ route('productos.index') }}"
                            class="boton boton--secundario"
                        >
                            Limpiar filtros
                        </a>

                        @if ($puedeVerCatalogo)
                            <a
                                href="{{ route('productos.catalogo') }}"
                                class="boton boton--primario"
                            >
                                Ver artículos pendientes
                            </a>
                        @endif
                    </div>
                </div>
            @else
                <div
                    class="tabla-contenedor tabla-contenedor--productos"
                    tabindex="0"
                    aria-label="Listado desplazable de productos"
                >
                    <table class="tabla-productos tabla-productos--internos">
                        <thead>
                            <tr>
                                <th>Código interno</th>
                                <th>Imagen</th>
                                <th>Descripción</th>
                                <th>Marca</th>
                                <th>Subgrupo</th>
                                <th>Código fabricante</th>
                                <th>Código de barras</th>
                                <th>IVA</th>
                                <th>Rentabilidad</th>
                                <th>PVP</th>
                                <th>Visible en web</th>
                                <th>Destacado</th>
                                <th>Estado</th>
                                @if ($puedeEditarProductos)
                                    <th>Acciones</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($productos as $producto)
                                @php
                                    $marca = $producto->marca;
                                    $subgrupo = $producto->subgrupo;
                                    $iva = $producto->alicuotaIva;
                                @endphp

                                <tr>
                                    <td>
                                        <span class="codigo codigo--interno">
                                            {{ $producto->codigointerno }}
                                        </span>
                                    </td>

                                    <td class="celda-imagen-producto">
                                        @if ($producto->rutaimagen)
                                            <a
                                                href="{{ asset(
                                                    'storage/'
                                                    . $producto->rutaimagen
                                                ) }}"
                                                class="enlace-imagen-producto"
                                                target="_blank"
                                                rel="noopener"
                                                title="Abrir imagen en tamaño completo"
                                                aria-label="Ver imagen de {{ $producto->descripcion }}"
                                            >
                                                <img
                                                    src="{{ asset(
                                                        'storage/'
                                                        . $producto->rutaimagen
                                                    ) }}"
                                                    alt="{{ $producto->descripcion }}"
                                                    class="imagen-producto-miniatura"
                                                    loading="lazy"
                                                >
                                            </a>
                                        @else
                                            <span class="producto-sin-imagen">
                                                Sin imagen
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <strong class="descripcion-producto">
                                            {{ $producto->descripcion }}
                                        </strong>
                                    </td>

                                    <td>
                                        @if ($marca)
                                            <span class="dato-relacion">
                                                {{ $marca->nombre }}
                                            </span>
                                        @else
                                            <span class="dato-vacio">
                                                Sin marca
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($subgrupo)
                                            <span class="dato-relacion">
                                                {{ $subgrupo->nombre }}
                                            </span>
                                        @else
                                            <span class="dato-vacio">
                                                Sin subgrupo
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="codigo">
                                            {{ $producto->codigofabricante ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="codigo">
                                            {{ $producto->codigobarra ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        @if ($iva)
                                            <strong class="dato-principal">
                                                {{ $iva->nombre }}
                                            </strong>

                                            <span class="dato-secundario">
                                                {{ number_format(
                                                    (float) $iva->porcentaje,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }}%
                                            </span>
                                        @else
                                            <span class="dato-vacio">
                                                Sin IVA
                                            </span>
                                        @endif
                                    </td>

                                    <td class="porcentaje">
                                        @if ($producto->rentabilidadobjetivo !== null)
                                            {{ number_format(
                                                (float) $producto->rentabilidadobjetivo,
                                                2,
                                                ',',
                                                '.'
                                            ) }}%
                                        @else
                                            <span class="dato-vacio">—</span>
                                        @endif
                                    </td>

                                    <td class="importe importe--pvp">
                                        @if ($producto->pvp !== null)
                                            $ {{ number_format(
                                                (float) $producto->pvp,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        @else
                                            <span class="dato-vacio">
                                                Sin precio
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <span
                                            class="estado estado--{{ $producto->visibleweb ? 'si' : 'no' }}"
                                        >
                                            {{ $producto->visibleweb ? 'Sí' : 'No' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="estado estado--{{ $producto->destacado ? 'si' : 'no' }}"
                                        >
                                            {{ $producto->destacado ? 'Sí' : 'No' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="estado estado--{{ $producto->estado ? 'activo' : 'inactivo' }}"
                                        >
                                            {{ $producto->estado ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>

                                    @if ($puedeEditarProductos)
                                        <td>
                                            <a
                                                href="{{ route('productos.editar', $producto) }}"
                                                class="boton-editar-producto"
                                                aria-label="Editar {{ $producto->codigointerno }}"
                                            >
                                                Editar
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($productos->hasPages())
                    @php
                        $paginaInicial = max(
                            1,
                            $productos->currentPage() - 2
                        );

                        $paginaFinal = min(
                            $productos->lastPage(),
                            $productos->currentPage() + 2
                        );
                    @endphp

                    <nav class="paginacion" aria-label="Paginación de productos">
                        @if ($productos->onFirstPage())
                            <span class="paginacion__boton paginacion__boton--deshabilitado">
                                ← Anterior
                            </span>
                        @else
                            <a
                                href="{{ $productos->previousPageUrl() }}"
                                class="paginacion__boton"
                            >
                                ← Anterior
                            </a>
                        @endif

                        <div class="paginacion__paginas">
                            @foreach (
                                $productos->getUrlRange(
                                    $paginaInicial,
                                    $paginaFinal
                                )
                                as $pagina => $url
                            )
                                @if ($pagina === $productos->currentPage())
                                    <span
                                        class="paginacion__numero paginacion__numero--activo"
                                        aria-current="page"
                                    >
                                        {{ $pagina }}
                                    </span>
                                @else
                                    <a
                                        href="{{ $url }}"
                                        class="paginacion__numero"
                                    >
                                        {{ $pagina }}
                                    </a>
                                @endif
                            @endforeach
                        </div>

                        @if ($productos->hasMorePages())
                            <a
                                href="{{ $productos->nextPageUrl() }}"
                                class="paginacion__boton"
                            >
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formulario = document.getElementById('filtros-productos');
            const campoBusqueda = document.getElementById('buscar');
            const campoEstado = document.getElementById('estado');

            let temporizadorBusqueda = null;

            function enviarFiltros() {
                if (temporizadorBusqueda !== null) {
                    clearTimeout(temporizadorBusqueda);
                }

                formulario.requestSubmit();
            }

            campoBusqueda.addEventListener('input', function () {
                clearTimeout(temporizadorBusqueda);

                temporizadorBusqueda = setTimeout(function () {
                    formulario.requestSubmit();
                }, 500);
            });

            campoEstado.addEventListener('change', enviarFiltros);

            if (campoBusqueda.value !== '') {
                campoBusqueda.focus();

                campoBusqueda.setSelectionRange(
                    campoBusqueda.value.length,
                    campoBusqueda.value.length
                );
            }
        });
    </script>

</body>
</html>
