<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Catálogos de proveedores | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/productos/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>
<body class="con-barra-lateral">
    @php
        $usuarioAdmin = auth('web')->user();
        $puedeVerProductos = $usuarioAdmin?->tienePermiso('productos.ver') ?? false;
        $puedeImportarCatalogos = $usuarioAdmin?->tienePermiso('catalogos.importar') ?? false;
        $puedeCrearProductos = $usuarioAdmin?->tienePermiso('productos.crear') ?? false;
        $puedeCompararPrecios = $usuarioAdmin?->tienePermiso('catalogos.ver') ?? false;

        $hayFiltros = $busqueda !== ''
            || !empty($idProveedor)
            || !empty($idCatalogoNumero)
            || $estadoArticulo !== 'todos';
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">
                    Administración de productos
                </p>

                <h1>Catálogo de proveedores</h1>

                <p class="encabezado-principal__descripcion">
                    Consultá artículos pendientes y vinculados, elegí la lista del proveedor
                    y revisá tanto la versión vigente como su historial de precios.
                </p>
            </div>

            @if ($puedeImportarCatalogos || $puedeVerProductos)
                <div class="encabezado-principal__acciones">
                    @if ($puedeImportarCatalogos)
                        @php
                            $parametrosImportar = [];

                            if (
                                $proveedorSeleccionado
                                && (bool) $proveedorSeleccionado->estado
                            ) {
                                $parametrosImportar['idproveedor'] =
                                    $proveedorSeleccionado->idproveedor;
                            }
                        @endphp

                        <a
                            href="{{ route('catalogos.importar', $parametrosImportar) }}"
                            class="boton boton--secundario"
                        >
                            Importar otro catálogo
                        </a>
                    @endif

                    @if ($puedeVerProductos)
                        <a
                            href="{{ route('productos.index') }}"
                            class="boton boton--primario"
                        >
                            Ver productos
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

        <section class="resumen" id="resumen-catalogo">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">
                    Artículos encontrados
                </span>

                <strong class="resumen__valor">
                    {{ number_format($detalles->total(), 0, ',', '.') }}
                </strong>

                <small>
                    Según los filtros seleccionados
                </small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">
                    Relación con Messirve
                </span>

                <div class="resumen-vinculacion">
                    <span>
                        <strong>{{ $cantidadVinculadosResultado }}</strong>
                        Vinculados
                    </span>

                    <span>
                        <strong>{{ $cantidadPendientesResultado }}</strong>
                        Sin vincular
                    </span>
                </div>

                <small>
                    Los vinculados ya poseen código interno MES
                </small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa">
                <span class="resumen__icono">i</span>

                <div>
                    <strong>
                        @if ($proveedorSeleccionado)
                            {{ $proveedorSeleccionado->razonsocial }}
                            · {{ $cantidadListas }} lista(s) guardada(s)
                        @else
                            Una sola pantalla para todo el catálogo
                        @endif
                    </strong>

                    <p>
                        @if ($proveedorSeleccionado)
                            Elegí “Todas las listas” para comparar versiones o una lista concreta.
                            Los artículos pendientes de la lista vigente pueden revisarse desde esta misma tabla.
                        @else
                            Filtrá por proveedor, lista y estado. Los pendientes de la lista vigente
                            pueden convertirse o vincularse con un producto Messirve.
                        @endif
                    </p>
                </div>
            </article>
        </section>

        <section class="panel" id="panel-filtros-catalogo">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>

                    <div>
                        <h2>Consultar catálogos</h2>

                        <p>
                            Buscá por descripción, códigos del proveedor, código de barras
                            o código interno MES.
                        </p>
                    </div>
                </div>
            </div>

            <form
                action="{{ route('productos.catalogo') }}"
                method="GET"
                class="filtros filtros--catalogo-unificado"
                id="formulario-filtros-catalogo"
            >
                <div class="campo campo--busqueda">
                    <label for="buscar">
                        Buscar artículo
                    </label>

                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $busqueda }}"
                        placeholder="Ejemplo: esmalte, MES-000004, 779080..."
                        maxlength="255"
                        autocomplete="off"
                    >
                </div>

                <div class="campo">
                    <label for="idproveedor">
                        Proveedor
                    </label>

                    <select
                        id="idproveedor"
                        name="idproveedor"
                    >
                        <option value="">
                            Todos los proveedores
                        </option>

                        @foreach ($proveedores as $proveedor)
                            <option
                                value="{{ $proveedor->idproveedor }}"
                                @selected(
                                    (string) $idProveedor
                                    === (string) $proveedor->idproveedor
                                )
                            >
                                {{ $proveedor->razonsocial }}
                                {{ !$proveedor->estado ? ' · Inactivo' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="idcatalogo">
                        Lista del proveedor
                    </label>

                    <select
                        id="idcatalogo"
                        name="idcatalogo"
                        @disabled(
                            empty($idProveedor)
                            || $catalogosProveedor->isEmpty()
                        )
                    >
                        <option value="">
                            Todas las listas
                        </option>

                        @foreach ($catalogosProveedor as $catalogo)
                            @php
                                $estadoTemporalLista = $catalogo->estadoTemporal();
                                $textoEstadoLista = match ($estadoTemporalLista) {
                                    'vigente' => 'VIGENTE',
                                    'programada' => 'PROGRAMADA',
                                    default => 'Histórica',
                                };
                            @endphp
                            <option
                                value="{{ $catalogo->idcatalogoproveedor }}"
                                @selected(
                                    (string) $idCatalogoNumero
                                    === (string) $catalogo->idcatalogoproveedor
                                )
                            >
                                {{ $catalogo->nombre_lista }}

                                @if ($catalogo->fecha_vigencia)
                                    · {{ $catalogo->fecha_vigencia->format('d/m/Y') }}
                                @endif

                                · {{ $textoEstadoLista }}
                            </option>
                        @endforeach
                    </select>

                    @if (empty($idProveedor))
                        <small class="campo__ayuda">
                            Seleccioná primero un proveedor.
                        </small>
                    @elseif ($catalogosProveedor->isEmpty())
                        <small class="campo__ayuda">
                            Este proveedor todavía no tiene listas importadas.
                        </small>
                    @endif
                </div>

                <div class="campo">
                    <label for="estado_articulo">
                        Estado del artículo
                    </label>

                    <select
                        id="estado_articulo"
                        name="estado_articulo"
                    >
                        <option
                            value="todos"
                            @selected($estadoArticulo === 'todos')
                        >
                            Todos
                        </option>

                        <option
                            value="pendientes"
                            @selected($estadoArticulo === 'pendientes')
                        >
                            Pendientes de revisión
                        </option>

                        <option
                            value="vinculados"
                            @selected($estadoArticulo === 'vinculados')
                        >
                            Vinculados a Messirve
                        </option>
                    </select>

                    @if ($estadoArticulo === 'pendientes')
                        <small class="campo__ayuda">
                            Sólo pendientes de listas vigentes.
                        </small>
                    @endif
                </div>

                <div class="filtros__acciones">
                    <button
                        type="submit"
                        class="boton boton--primario"
                    >
                        Buscar
                    </button>

                    @if ($hayFiltros)
                        <a
                            href="{{ route('productos.catalogo') }}"
                            class="boton boton--secundario"
                        >
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
        </section>

        @if (
            $proveedorSeleccionado
            && !$catalogosProveedor->isEmpty()
        )
            <section
                class="historial-listas"
                aria-label="Listas guardadas del proveedor"
            >
                @foreach ($catalogosProveedor as $catalogo)
                    @php
                        $estadoTemporalLista = $catalogo->estadoTemporal();
                        $textoEstadoLista = match ($estadoTemporalLista) {
                            'vigente' => 'Vigente',
                            'programada' => 'Programada',
                            default => 'Histórica',
                        };

                        $parametrosLista = [
                            'idproveedor' =>
                                $proveedorSeleccionado->idproveedor,

                            'idcatalogo' =>
                                $catalogo->idcatalogoproveedor,

                            'estado_articulo' =>
                                $estadoArticulo,
                        ];

                        if ($busqueda !== '') {
                            $parametrosLista['buscar'] = $busqueda;
                        }
                    @endphp

                    <a
                        href="{{ route(
                            'productos.catalogo',
                            $parametrosLista
                        ) }}"
                        class="historial-lista{{
                            (string) $idCatalogoNumero
                            === (string) $catalogo->idcatalogoproveedor
                                ? ' historial-lista--seleccionada'
                                : ''
                        }}"
                    >
                        <span
                            class="historial-lista__estado {{
                                $estadoTemporalLista === 'vigente'
                                    ? 'historial-lista__estado--vigente'
                                    : (
                                        $estadoTemporalLista === 'programada'
                                            ? 'historial-lista__estado--programada'
                                            : ''
                                    )
                            }}"
                        >
                            {{ $textoEstadoLista }}
                        </span>

                        <strong>
                            {{ $catalogo->nombre_lista }}
                        </strong>

                        <small>
                            Vigencia:
                            {{ $catalogo->fecha_vigencia?->format('d/m/Y') ?? 'sin fecha' }}

                            · Desc. general
                            {{ number_format(
                                (float) $catalogo->descuento_total,
                                2,
                                ',',
                                '.'
                            ) }}%
                        </small>
                    </a>
                @endforeach
            </section>
        @endif

        <section
            class="panel panel--tabla"
            id="panel-resultados"
        >
            <div class="panel__cabecera panel__cabecera--tabla">
                <div>
                    <span class="panel__numero">02</span>

                    <div>
                        <h2>Artículos del catálogo</h2>

                        <p>
                            Se conserva la clasificación recibida, el precio de cada lista
                            y su relación con el producto interno de Messirve.
                        </p>
                    </div>
                </div>

                <span class="resultado-cantidad">
                    {{ $detalles->firstItem() ?? 0 }}–{{ $detalles->lastItem() ?? 0 }}
                    de {{ $detalles->total() }}
                </span>
            </div>

            @if ($detalles->isEmpty())
                <div class="estado-vacio">
                    <span class="estado-vacio__icono">i</span>

                    <h3>No encontramos artículos</h3>

                    <p>
                        No hay filas que coincidan con los filtros seleccionados.
                    </p>

                    @if ($estadoArticulo === 'pendientes')
                        <p class="estado-vacio__aclaracion">
                            Recordá que “Pendientes de revisión” consulta únicamente
                            artículos de listas vigentes.
                        </p>
                    @endif

                    <div class="estado-vacio__acciones">
                        <a
                            href="{{ route('productos.catalogo') }}"
                            class="boton boton--secundario"
                        >
                            Limpiar filtros
                        </a>

                        @if ($puedeImportarCatalogos)
                            <a
                                href="{{ route('catalogos.importar') }}"
                                class="boton boton--primario"
                            >
                                Importar catálogo
                            </a>
                        @endif
                    </div>
                </div>
            @else
                <div
                    class="tabla-contenedor"
                    tabindex="0"
                >
                    <table class="tabla-productos tabla-productos--unificada">
                        <thead>
                            <tr>
                                <th class="col-proveedor">
                                    Proveedor y lista
                                </th>

                                <th class="col-codigo">
                                    Código proveedor
                                </th>

                                <th class="col-codigo">
                                    Código fabricante
                                </th>

                                <th class="col-codigo">
                                    Código de barras
                                </th>

                                <th class="col-descripcion">
                                    Descripción recibida
                                </th>

                                <th class="col-clasificacion">
                                    Clasificación del proveedor
                                </th>

                                <th class="col-producto">
                                    Producto Messirve
                                </th>

                                <th class="col-precio">
                                    Precio de lista
                                </th>

                                <th class="col-descuento">
                                    Descuento
                                </th>

                                <th class="col-precio-neto">
                                    Precio neto
                                </th>

                                @if ($puedeCrearProductos || $puedeCompararPrecios)
                                    <th class="col-accion">
                                        Acción
                                    </th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($detalles as $detalle)
                                @php
                                    $catalogo =
                                        $detalle->catalogoProveedor;

                                    $proveedor =
                                        $catalogo?->proveedor;

                                    $producto =
                                        $detalle->producto;

                                    $estadoTemporalLista = $catalogo?->estadoTemporal() ?? 'historica';
                                    $listaVigente = $estadoTemporalLista === 'vigente';
                                    $listaProgramada = $estadoTemporalLista === 'programada';

                                    $textoEstadoLista = match ($estadoTemporalLista) {
                                        'vigente' => 'Vigente',
                                        'programada' => 'Programada',
                                        default => 'Histórica',
                                    };

                                    $clasificacion = collect([
                                        $detalle->linea_proveedor,
                                        $detalle->tipo_producto_proveedor,
                                        $detalle->grupo_proveedor,
                                        $detalle->subgrupo_proveedor,
                                    ])
                                        ->filter()
                                        ->implode(' · ');
                                @endphp

                                <tr>
                                    <td>
                                        <strong class="dato-principal">
                                            {{ $proveedor?->razonsocial ?? 'Sin proveedor' }}
                                        </strong>

                                        <span class="dato-secundario dato-secundario--lista">
                                            {{ $catalogo?->nombre_lista ?? 'Lista sin nombre' }}

                                            @if ($catalogo?->fecha_vigencia)
                                                · {{ $catalogo->fecha_vigencia->format('d/m/Y') }}
                                            @endif
                                        </span>

                                        @if ($catalogo)
                                            <span
                                                class="etiqueta-lista {{
                                                    $listaVigente
                                                        ? 'etiqueta-lista--vigente'
                                                        : (
                                                            $listaProgramada
                                                                ? 'etiqueta-lista--programada'
                                                                : ''
                                                        )
                                                }}"
                                            >
                                                {{ $textoEstadoLista }}
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="codigo">
                                            {{ $detalle->codigoproveedor ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="codigo">
                                            {{ $detalle->codigofabricante ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="codigo">
                                            {{ $detalle->codigobarra ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <strong class="descripcion-producto">
                                            {{ $detalle->descripcionproveedor }}
                                        </strong>
                                    </td>

                                    <td>
                                        @if ($clasificacion !== '')
                                            <span
                                                class="clasificacion"
                                                title="{{ $clasificacion }}"
                                            >
                                                {{ \Illuminate\Support\Str::limit(
                                                    $clasificacion,
                                                    90
                                                ) }}
                                            </span>
                                        @else
                                            <span class="dato-vacio">
                                                Sin clasificación
                                            </span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($producto)
                                            <span class="producto-vinculado">
                                                <strong>
                                                    {{ $producto->codigointerno }}
                                                </strong>

                                                <small>
                                                    {{ $producto->descripcion }}
                                                </small>
                                            </span>
                                        @else
                                            <span class="producto-pendiente">
                                                Pendiente de vincular
                                            </span>
                                        @endif
                                    </td>

                                    <td class="importe">
                                        $ {{ number_format(
                                            (float) $detalle->preciolista,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </td>

                                    <td class="porcentaje">
                                        {{ number_format(
                                            (float) $detalle->descuentoproducto,
                                            2,
                                            ',',
                                            '.'
                                        ) }}%
                                    </td>

                                    <td class="importe importe--neto">
                                        @if ($detalle->precioneto !== null)
                                            $ {{ number_format(
                                                (float) $detalle->precioneto,
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        @else
                                            <span class="dato-vacio">
                                                Calculado al comparar
                                            </span>
                                        @endif
                                    </td>

                                    @if ($puedeCrearProductos || $puedeCompararPrecios)
                                        <td>
                                            <div class="acciones-catalogo">
                                                @if (
                                                    $puedeCompararPrecios
                                                    && $listaVigente
                                                    && (
                                                        $detalle->idproducto !== null
                                                        || trim((string) $detalle->codigobarra) !== ''
                                                    )
                                                )
                                                    <a
                                                        href="{{ route(
                                                            'productos.catalogo.comparar-proveedores',
                                                            $detalle->iddetallecatalogo
                                                        ) }}"
                                                        class="boton-comparar-precios"
                                                    >
                                                        Comparar precios
                                                    </a>
                                                @endif

                                                @if (
                                                    $puedeCrearProductos
                                                    && $detalle->idproducto === null
                                                    && $listaVigente
                                                )
                                                    <a
                                                        href="{{ route(
                                                            'productos.catalogo.crear',
                                                            $detalle->iddetallecatalogo
                                                        ) }}"
                                                        class="boton-accion"
                                                    >
                                                        Revisar artículo
                                                        <span aria-hidden="true">→</span>
                                                    </a>
                                                @elseif (
                                                    $detalle->idproducto !== null
                                                    && $listaVigente
                                                )
                                                    <span class="accion-resuelta">
                                                        Vinculado
                                                    </span>
                                                @elseif ($listaProgramada)
                                                    <span class="accion-programada">
                                                        Programada
                                                    </span>
                                                @elseif (!$listaVigente)
                                                    <span class="accion-historica">
                                                        Sólo consulta
                                                    </span>
                                                @elseif (
                                                    trim((string) $detalle->codigobarra) === ''
                                                    && $detalle->idproducto === null
                                                    && !$puedeCrearProductos
                                                )
                                                    <span class="accion-sin-comparacion">
                                                        Sin coincidencia automática
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($detalles->hasPages())
                    @php
                        $paginaInicial =
                            max(
                                1,
                                $detalles->currentPage() - 2
                            );

                        $paginaFinal =
                            min(
                                $detalles->lastPage(),
                                $detalles->currentPage() + 2
                            );
                    @endphp

                    <nav
                        class="paginacion"
                        aria-label="Paginación de artículos"
                    >
                        @if ($detalles->onFirstPage())
                            <span
                                class="paginacion__boton paginacion__boton--deshabilitado"
                            >
                                ← Anterior
                            </span>
                        @else
                            <a
                                href="{{ $detalles->previousPageUrl() }}"
                                class="paginacion__boton"
                            >
                                ← Anterior
                            </a>
                        @endif

                        <div class="paginacion__paginas">
                            @foreach (
                                $detalles->getUrlRange(
                                    $paginaInicial,
                                    $paginaFinal
                                ) as $pagina => $url
                            )
                                @if (
                                    $pagina ===
                                    $detalles->currentPage()
                                )
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

                        @if ($detalles->hasMorePages())
                            <a
                                href="{{ $detalles->nextPageUrl() }}"
                                class="paginacion__boton"
                            >
                                Siguiente →
                            </a>
                        @else
                            <span
                                class="paginacion__boton paginacion__boton--deshabilitado"
                            >
                                Siguiente →
                            </span>
                        @endif
                    </nav>
                @endif
            @endif
        </section>
    </main>

    <script>
        (() => {
            const form =
                document.getElementById(
                    'formulario-filtros-catalogo'
                );

            const input =
                document.getElementById('buscar');

            const proveedor =
                document.getElementById('idproveedor');

            const catalogo =
                document.getElementById('idcatalogo');

            const estado =
                document.getElementById('estado_articulo');

            let temporizador;
            let solicitud;

            if (
                !form
                || !input
                || !proveedor
                || !estado
            ) {
                return;
            }

            const construirUrl = () => {
                const url =
                    new URL(form.action);

                const datos =
                    new FormData(form);

                for (
                    const [clave, valor]
                    of datos.entries()
                ) {
                    if (
                        String(valor).trim() !== ''
                    ) {
                        url.searchParams.set(
                            clave,
                            valor
                        );
                    }
                }

                /*
                 * "todos" es el valor por defecto. Omitirlo mantiene
                 * la URL más limpia.
                 */
                if (
                    url.searchParams.get(
                        'estado_articulo'
                    ) === 'todos'
                ) {
                    url.searchParams.delete(
                        'estado_articulo'
                    );
                }

                return url;
            };

            const cargarResultados = async () => {
                const panelActual =
                    document.getElementById(
                        'panel-resultados'
                    );

                const resumenActual =
                    document.getElementById(
                        'resumen-catalogo'
                    );

                solicitud?.abort();

                solicitud =
                    new AbortController();

                panelActual?.classList.add(
                    'panel--cargando'
                );

                panelActual?.setAttribute(
                    'aria-busy',
                    'true'
                );

                const url =
                    construirUrl();

                try {
                    const respuesta =
                        await fetch(
                            url,
                            {
                                signal:
                                    solicitud.signal,

                                headers: {
                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },
                            }
                        );

                    if (!respuesta.ok) {
                        throw new Error(
                            'No se pudo completar la búsqueda.'
                        );
                    }

                    const documento =
                        new DOMParser()
                            .parseFromString(
                                await respuesta.text(),
                                'text/html'
                            );

                    const panelNuevo =
                        documento.getElementById(
                            'panel-resultados'
                        );

                    const resumenNuevo =
                        documento.getElementById(
                            'resumen-catalogo'
                        );

                    if (!panelNuevo) {
                        throw new Error(
                            'No se encontraron los resultados.'
                        );
                    }

                    panelActual?.replaceWith(
                        panelNuevo
                    );

                    if (
                        resumenActual
                        && resumenNuevo
                    ) {
                        resumenActual.replaceWith(
                            resumenNuevo
                        );
                    }

                    history.replaceState(
                        {},
                        '',
                        url
                    );
                } catch (error) {
                    if (
                        error.name !== 'AbortError'
                    ) {
                        document
                            .getElementById(
                                'panel-resultados'
                            )
                            ?.classList.remove(
                                'panel--cargando'
                            );

                        document
                            .getElementById(
                                'panel-resultados'
                            )
                            ?.removeAttribute(
                                'aria-busy'
                            );
                    }
                }
            };

            input.addEventListener(
                'input',
                () => {
                    clearTimeout(
                        temporizador
                    );

                    temporizador =
                        setTimeout(
                            cargarResultados,
                            250
                        );
                }
            );

            estado.addEventListener(
                'change',
                cargarResultados
            );

            /*
             * Cambiar proveedor recarga la pantalla completa porque
             * también cambia qué listas se pueden elegir.
             */
            proveedor.addEventListener(
                'change',
                () => {
                    if (catalogo) {
                        catalogo.value = '';
                    }

                    form.requestSubmit();
                }
            );

            /*
             * Al cambiar de lista se actualizan también las tarjetas
             * del historial y su estado visual.
             */
            catalogo?.addEventListener(
                'change',
                () => form.requestSubmit()
            );
        })();
    </script>
</body>
</html>
