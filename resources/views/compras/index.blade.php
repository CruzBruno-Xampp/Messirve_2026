<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Compras | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/productos/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/compras/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>
<body class="con-barra-lateral">
    @php
        $usuarioAdmin = auth('web')->user();
        $puedeCrearCompras = $usuarioAdmin?->tienePermiso('compras.crear') ?? false;

        $totalCompras = (int) ($resumen->total ?? 0);
        $comprasPendientes = (int) ($resumen->pendientes ?? 0);
        $comprasParciales = (int) ($resumen->parciales ?? 0);
        $comprasRecibidas = (int) ($resumen->recibidas ?? 0);
        $comprasAnuladas = (int) ($resumen->anuladas ?? 0);

        $hayFiltros = $busqueda !== ''
            || $estado !== 'todos'
            || $idProveedor > 0
            || ($esDueno && $idSucursal > 0)
            || $fechaDesde !== ''
            || $fechaHasta !== '';

        $codigoCompra = fn ($compra) => 'COM-' . str_pad(
            (string) $compra->idcompra,
            6,
            '0',
            STR_PAD_LEFT
        );
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos pagina-compras">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Administración comercial</p>
                <h1>Compras a proveedores</h1>
                <p class="encabezado-principal__descripcion">
                    Consultá los pedidos realizados a proveedores y seguí su estado hasta la recepción de la mercadería.
                </p>
            </div>

            @if ($puedeCrearCompras)
                <div class="encabezado-principal__acciones">
                    <a
                        href="{{ route('compras.crear') }}"
                        class="boton boton--primario"
                    >
                        <span class="boton__simbolo" aria-hidden="true">+</span>
                        Nueva compra
                    </a>
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

        <section class="resumen resumen--compras">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Compras encontradas</span>
                <strong class="resumen__valor">{{ number_format($totalCompras, 0, ',', '.') }}</strong>
                <small>Según los filtros seleccionados</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Estado de las compras</span>
                <div class="resumen-estados-compras">
                    <span><strong>{{ $comprasPendientes }}</strong> Pendientes</span>
                    <span><strong>{{ $comprasParciales }}</strong> Parciales</span>
                    <span><strong>{{ $comprasRecibidas }}</strong> Recibidas</span>
                    <span><strong>{{ $comprasAnuladas }}</strong> Anuladas</span>
                </div>
                <small>El stock se actualizará cuando una compra sea recibida</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa compra-info">
                <span class="resumen__icono" aria-hidden="true">i</span>
                <div>
                    <strong>Pedido y recepción son momentos distintos</strong>
                    <p>
                        Registrar una compra no aumenta el stock. Las unidades ingresarán cuando se confirme que la mercadería llegó.
                    </p>
                </div>
            </article>
        </section>

        <section class="panel panel--filtros-compras">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Consultar compras</h2>
                        <p>Filtrá por compra, proveedor, estado, sucursal o fecha.</p>
                    </div>
                </div>
            </div>

            <form
                action="{{ route('compras.index') }}"
                method="GET"
                class="filtros filtros--compras"
            >
                <div class="campo campo--busqueda-compra">
                    <label for="buscar">Buscar compra</label>
                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $busqueda }}"
                        placeholder="Ejemplo: COM-000001, Tersuave o número de factura..."
                        maxlength="255"
                        autocomplete="off"
                    >
                </div>

                <div class="campo">
                    <label for="idproveedor">Proveedor</label>
                    <select id="idproveedor" name="idproveedor">
                        <option value="0">Todos los proveedores</option>
                        @foreach ($proveedores as $proveedor)
                            <option
                                value="{{ $proveedor->idproveedor }}"
                                @selected($idProveedor === (int) $proveedor->idproveedor)
                            >
                                {{ $proveedor->razonsocial }}{{ !$proveedor->estado ? ' (inactivo)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="todos" @selected($estado === 'todos')>Todos</option>
                        <option value="pendiente" @selected($estado === 'pendiente')>Pendientes</option>
                        <option value="parcial" @selected($estado === 'parcial')>Parciales</option>
                        <option value="recibida" @selected($estado === 'recibida')>Recibidas</option>
                        <option value="anulada" @selected($estado === 'anulada')>Anuladas</option>
                    </select>
                </div>

                @if ($esDueno)
                    <div class="campo">
                        <label for="idsucursal">Sucursal</label>
                        <select id="idsucursal" name="idsucursal">
                            <option value="0">Todas las sucursales</option>
                            @foreach ($sucursales as $sucursal)
                                <option
                                    value="{{ $sucursal->idsucursal }}"
                                    @selected($idSucursal === (int) $sucursal->idsucursal)
                                >
                                    {{ $sucursal->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="campo">
                    <label for="fecha_desde">Desde</label>
                    <input type="date" id="fecha_desde" name="fecha_desde" value="{{ $fechaDesde }}">
                </div>

                <div class="campo">
                    <label for="fecha_hasta">Hasta</label>
                    <input type="date" id="fecha_hasta" name="fecha_hasta" value="{{ $fechaHasta }}">
                </div>

                <div class="filtros__acciones filtros__acciones--compras">
                    <button type="submit" class="boton boton--primario">Buscar</button>

                    @if ($hayFiltros)
                        <a href="{{ route('compras.index') }}" class="boton boton--secundario">Limpiar</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="panel panel--tabla panel--lista-compras">
            <div class="panel__cabecera panel__cabecera--tabla">
                <div>
                    <span class="panel__numero">02</span>
                    <div>
                        <h2>Compras registradas</h2>
                        <p>Revisá proveedor, sucursal, comprobante, importe y estado de cada compra.</p>
                    </div>
                </div>

                <span class="resultado-cantidad">
                    {{ $compras->firstItem() ?? 0 }}–{{ $compras->lastItem() ?? 0 }}
                    de {{ $compras->total() }}
                </span>
            </div>

            @if ($compras->isEmpty())
                <div class="estado-vacio estado-vacio--compras">
                    <span class="estado-vacio__icono" aria-hidden="true">i</span>
                    <h3>{{ $hayFiltros ? 'No encontramos compras' : 'Todavía no hay compras registradas' }}</h3>
                    <p>
                        @if ($hayFiltros)
                            No hay compras que coincidan con los filtros seleccionados.
                        @else
                            Cuando registremos la primera compra a un proveedor aparecerá en este listado.
                        @endif
                    </p>

                    @if ($hayFiltros)
                        <div class="estado-vacio__acciones">
                            <a href="{{ route('compras.index') }}" class="boton boton--secundario">Limpiar filtros</a>
                        </div>
                    @endif
                </div>
            @else
                <div class="tabla-contenedor tabla-contenedor--compras" tabindex="0">
                    <table class="tabla-compras">
                        <thead>
                            <tr>
                                <th>Compra</th>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th>Sucursal</th>
                                <th>Comprobante</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($compras as $compra)
                                @php
                                    $estadoCompra = strtoupper((string) $compra->estado);
                                    $claseEstado = match ($estadoCompra) {
                                        'RECIBIDA' => 'estado-compra--recibida',
                                        'PARCIAL' => 'estado-compra--parcial',
                                        'ANULADA' => 'estado-compra--anulada',
                                        default => 'estado-compra--pendiente',
                                    };

                                    $comprobante = trim((string) ($compra->tipocomprobante ?? ''));
                                    $numeroComprobante = trim((string) ($compra->numerocomprobante ?? ''));
                                    if ($numeroComprobante !== '') {
                                        $comprobante .= ($comprobante !== '' ? ' · ' : '') . $numeroComprobante;
                                    }
                                @endphp

                                <tr>
                                    <td>
                                        <strong class="codigo-compra">{{ $codigoCompra($compra) }}</strong>
                                    </td>
                                    <td>
                                        <span class="fecha-compra">
                                            <strong>{{ $compra->fecha?->format('d/m/Y') ?? '—' }}</strong>
                                            <small>{{ $compra->fecha?->format('H:i') ?? '' }}</small>
                                        </span>
                                    </td>
                                    <td>{{ $compra->proveedor?->razonsocial ?? 'Proveedor no disponible' }}</td>
                                    <td>{{ $compra->sucursal?->nombre ?? 'Sucursal no disponible' }}</td>
                                    <td>{{ $comprobante !== '' ? $comprobante : 'Sin comprobante' }}</td>
                                    <td class="importe-compra">$ {{ number_format((float) $compra->total, 2, ',', '.') }}</td>
                                    <td>
                                        <span class="estado-compra {{ $claseEstado }}">{{ ucfirst(strtolower($estadoCompra)) }}</span>
                                    </td>
                                    <td>
                                        <span class="accion-proxima" title="El detalle se habilita en el próximo paso">
                                            Próximo paso
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($compras->hasPages())
                    @php
                        $paginaInicial = max(1, $compras->currentPage() - 2);
                        $paginaFinal = min($compras->lastPage(), $compras->currentPage() + 2);
                    @endphp

                    <nav class="paginacion" aria-label="Paginación de compras">
                        @if ($compras->onFirstPage())
                            <span class="paginacion__boton paginacion__boton--deshabilitado">Anterior</span>
                        @else
                            <a href="{{ $compras->previousPageUrl() }}" class="paginacion__boton">Anterior</a>
                        @endif

                        <div class="paginacion__paginas">
                            @foreach (range($paginaInicial, $paginaFinal) as $pagina)
                                <a
                                    href="{{ $compras->url($pagina) }}"
                                    class="paginacion__numero{{ $pagina === $compras->currentPage() ? ' paginacion__numero--activo' : '' }}"
                                    @if ($pagina === $compras->currentPage()) aria-current="page" @endif
                                >
                                    {{ $pagina }}
                                </a>
                            @endforeach
                        </div>

                        @if ($compras->hasMorePages())
                            <a href="{{ $compras->nextPageUrl() }}" class="paginacion__boton">Siguiente</a>
                        @else
                            <span class="paginacion__boton paginacion__boton--deshabilitado">Siguiente</span>
                        @endif
                    </nav>
                @endif
            @endif
        </section>
    </main>
</body>
</html>
