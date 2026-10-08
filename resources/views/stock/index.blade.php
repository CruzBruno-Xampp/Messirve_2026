<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Control de stock | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/stock/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>

<body class="con-barra-lateral">
    @php
        $usuarioAdmin = auth('web')->user();
        $puedeGestionarStock = $usuarioAdmin?->tienePermiso('stock.gestionar') ?? false;
    @endphp

    @include('partials.barra-lateral')

    <main
        class="pagina-stock"
        id="aplicacion-stock"
        data-url-ubicacion="{{ url('/stock/productos/__PRODUCTO__/sucursales/__SUCURSAL__') }}"
    >
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Administración de existencias</p>
                <h1>Control de stock</h1>
                <p class="encabezado-principal__descripcion">
                    Consultá cuántas unidades hay de cada producto y revisá los
                    artículos que necesitan reposición.
                </p>
            </div>
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

        @if ($errors->any())
            <section class="alerta alerta--error" role="alert">
                <strong>Revisá los datos ingresados.</strong>
                <span>{{ $errors->first() }}</span>
            </section>
        @endif

        @if (($notificacionesVenta ?? collect())->isNotEmpty())
            <section class="notificaciones-venta-stock" aria-labelledby="titulo-notificaciones-venta-stock">
                <div class="notificaciones-venta-stock__cabecera">
                    <div>
                        <span class="notificaciones-venta-stock__icono" aria-hidden="true">!</span>
                        <div>
                            <h2 id="titulo-notificaciones-venta-stock">Ventas de otras sucursales usando este stock</h2>
                            <p>El stock ya fue descontado automáticamente. Estos avisos sirven para preparar o coordinar la mercadería.</p>
                        </div>
                    </div>
                    <strong>{{ $notificacionesVenta->count() }} aviso{{ $notificacionesVenta->count() === 1 ? '' : 's' }}</strong>
                </div>

                <div class="notificaciones-venta-stock__lista">
                    @foreach ($notificacionesVenta as $notificacion)
                        <article class="notificacion-venta-stock">
                            <div class="notificacion-venta-stock__venta">
                                V-{{ str_pad((string) $notificacion->idventa, 6, '0', STR_PAD_LEFT) }}
                            </div>
                            <div class="notificacion-venta-stock__contenido">
                                <strong>
                                    {{ $notificacion->sucursal_venta ?? 'Otra sucursal' }} utilizó stock de
                                    {{ $notificacion->sucursal_origen ?? 'esta sucursal' }}
                                </strong>
                                <span>{{ $notificacion->productos ?: 'Productos incluidos en la venta' }}</span>
                            </div>
                            <small>{{ $notificacion->fecha }}</small>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="resumen" aria-label="Resumen general de stock">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Productos ubicados</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['registros'], 0, ',', '.') }}
                </strong>
                <small>Registros activos por sucursal</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Unidades disponibles</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['unidades'], 0, ',', '.') }}
                </strong>
                <small>Suma de las existencias actuales</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--advertencia">
                <span class="resumen__etiqueta">Bajo mínimo</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['bajoMinimo'], 0, ',', '.') }}
                </strong>
                <small>Productos que conviene reponer</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--peligro">
                <span class="resumen__etiqueta">Sin stock</span>
                <strong class="resumen__valor">
                    {{ number_format($resumen['sinStock'], 0, ',', '.') }}
                </strong>
                <small>Productos con cantidad cero</small>
            </article>
        </section>

        <section class="panel">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Buscar existencias</h2>
                        <p>
                            La lista se actualiza al escribir o seleccionar un filtro.
                        </p>
                    </div>
                </div>
            </div>

            <form
                action="{{ route('stock.index') }}"
                method="GET"
                class="filtros"
                id="formulario-filtros-stock"
            >
                <div class="campo campo--busqueda">
                    <label for="buscar">Buscar producto</label>
                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $busqueda }}"
                        placeholder="Ejemplo: esmalte, MES-0001 o 779080..."
                        maxlength="255"
                        autocomplete="off"
                    >
                </div>

                <div class="campo">
                    <label for="idsucursal">Sucursal o depósito</label>
                    <select id="idsucursal" name="idsucursal">
                        <option value="">Todas las ubicaciones</option>

                        @foreach ($sucursales as $sucursal)
                            <option
                                value="{{ $sucursal->idsucursal }}"
                                @selected(
                                    (string) $idSucursal
                                    === (string) $sucursal->idsucursal
                                )
                            >
                                {{ $sucursal->nombre }}
                                @if ($sucursal->tipo)
                                    — {{ $sucursal->tipo }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="disponibilidad">Disponibilidad</label>
                    <select id="disponibilidad" name="disponibilidad">
                        <option value="todos" @selected($disponibilidad === 'todos')>
                            Todos
                        </option>
                        <option value="con_stock" @selected($disponibilidad === 'con_stock')>
                            Con stock
                        </option>
                        <option value="sin_stock" @selected($disponibilidad === 'sin_stock')>
                            Sin stock
                        </option>
                        <option value="bajo_minimo" @selected($disponibilidad === 'bajo_minimo')>
                            Bajo mínimo
                        </option>
                    </select>
                </div>

                @if ($busqueda !== '' || $idSucursal || $disponibilidad !== 'todos')
                    <a href="{{ route('stock.index') }}" class="boton boton--secundario">
                        Limpiar
                    </a>
                @endif
            </form>
        </section>

        <section class="panel panel--tabla" id="panel-resultados-stock">
            <div class="panel__cabecera panel__cabecera--tabla">
                <div>
                    <span class="panel__numero">02</span>
                    <div>
                        <h2>Existencias registradas</h2>
                        <p>
                            Cada fila representa un producto en una sucursal o depósito.
                        </p>
                    </div>
                </div>

                <span class="resultado-cantidad">
                    {{ $stocks->firstItem() ?? 0 }}–{{ $stocks->lastItem() ?? 0 }}
                    de {{ $stocks->total() }}
                </span>
            </div>

            @if ($stocks->isEmpty())
                <div class="estado-vacio">
                    <span class="estado-vacio__icono" aria-hidden="true">!</span>
                    <h3>No encontramos existencias</h3>
                    <p>
                        Probá con otra búsqueda o limpiá los filtros seleccionados.
                    </p>
                    <a href="{{ route('stock.index') }}" class="boton boton--secundario">
                        Limpiar filtros
                    </a>
                </div>
            @else
                <div class="tabla-contenedor" tabindex="0">
                    <table class="tabla-stock">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Código interno</th>
                                <th>Código de barras</th>
                                <th>Ubicación</th>
                                <th>Actual</th>
                                <th>Mínimo</th>
                                <th>Máximo</th>
                                <th>Situación</th>
                                @if ($puedeGestionarStock)
                                    <th>Acción</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stocks as $stock)
                                @php
                                    $producto = $stock->producto;
                                    $sucursal = $stock->sucursal;
                                    $sinStock = (int) $stock->stockactual === 0;
                                    $bajoMinimo = !$sinStock
                                        && (int) $stock->stockminimo > 0
                                        && (int) $stock->stockactual <= (int) $stock->stockminimo;
                                @endphp

                                <tr>
                                    <td>
                                        <strong class="dato-principal">
                                            {{ $producto?->descripcion ?? 'Producto no disponible' }}
                                        </strong>
                                        <span class="dato-secundario">
                                            {{ $producto?->marca?->nombre ?? 'Sin marca' }}
                                            @if ($producto?->subgrupo?->nombre)
                                                · {{ $producto->subgrupo->nombre }}
                                            @endif
                                        </span>
                                    </td>

                                    <td>
                                        <span class="codigo codigo--interno">
                                            {{ $producto?->codigointerno ?? '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="codigo">
                                            {{ $producto?->codigobarra ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <strong class="dato-principal">
                                            {{ $sucursal?->nombre ?? 'Sin ubicación' }}
                                        </strong>
                                        <span class="dato-secundario">
                                            {{ $sucursal?->tipo ?? 'Sucursal' }}
                                        </span>
                                    </td>

                                    <td class="cantidad cantidad--actual">
                                        {{ number_format((int) $stock->stockactual, 0, ',', '.') }}
                                    </td>
                                    <td class="cantidad">
                                        {{ number_format((int) $stock->stockminimo, 0, ',', '.') }}
                                    </td>
                                    <td class="cantidad">
                                        @if ($stock->stockmaximo === null)
                                            <span class="dato-vacio">Sin definir</span>
                                        @else
                                            {{ number_format((int) $stock->stockmaximo, 0, ',', '.') }}
                                        @endif
                                    </td>

                                    <td>
                                        @if ($sinStock)
                                            <span class="insignia insignia--peligro">Sin stock</span>
                                        @elseif ($bajoMinimo)
                                            <span class="insignia insignia--advertencia">Bajo mínimo</span>
                                        @else
                                            <span class="insignia insignia--normal">Disponible</span>
                                        @endif
                                    </td>

                                    @if ($puedeGestionarStock)
                                        <td>
                                            <div class="acciones-stock">
                                                <button
                                                    type="button"
                                                    class="boton-accion js-abrir-transferencia"
                                                    data-action="{{ route('stock.transferencias.store', $stock) }}"
                                                    data-idproducto="{{ $producto?->idproducto }}"
                                                    data-producto="{{ $producto?->descripcion }}"
                                                    data-codigo="{{ $producto?->codigointerno }}"
                                                    data-idsucursal="{{ $sucursal?->idsucursal }}"
                                                    data-sucursal="{{ $sucursal?->nombre }}"
                                                    data-actual="{{ (int) $stock->stockactual }}"
                                                    @disabled($sinStock)
                                                    title="{{ $sinStock ? 'No hay unidades disponibles para transferir' : 'Mover unidades a otra sucursal' }}"
                                                >
                                                    Transferir a otra sucursal
                                                </button>

                                                <button
                                                    type="button"
                                                    class="boton-accion boton-accion--secundario js-abrir-ingreso"
                                                    data-action="{{ route('stock.ingresos.store', $stock) }}"
                                                    data-idproducto="{{ $producto?->idproducto }}"
                                                    data-producto="{{ $producto?->descripcion }}"
                                                    data-codigo="{{ $producto?->codigointerno }}"
                                                    data-idsucursal="{{ $sucursal?->idsucursal }}"
                                                    data-sucursal="{{ $sucursal?->nombre }}"
                                                    data-actual="{{ (int) $stock->stockactual }}"
                                                    data-minimo="{{ (int) $stock->stockminimo }}"
                                                    data-maximo="{{ $stock->stockmaximo }}"
                                                    title="Sumar mercadería nueva en esta sucursal"
                                                >
                                                    Sumar ingreso externo
                                                </button>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($stocks->hasPages())
                    @php
                        $paginaInicial = max(1, $stocks->currentPage() - 2);
                        $paginaFinal = min($stocks->lastPage(), $stocks->currentPage() + 2);
                    @endphp

                    <nav class="paginacion" aria-label="Paginación del stock">
                        @if ($stocks->onFirstPage())
                            <span class="paginacion__boton paginacion__boton--deshabilitado">
                                ← Anterior
                            </span>
                        @else
                            <a href="{{ $stocks->previousPageUrl() }}" class="paginacion__boton">
                                ← Anterior
                            </a>
                        @endif

                        <div class="paginacion__paginas">
                            @foreach ($stocks->getUrlRange($paginaInicial, $paginaFinal) as $pagina => $url)
                                @if ($pagina === $stocks->currentPage())
                                    <span class="paginacion__numero paginacion__numero--activo" aria-current="page">
                                        {{ $pagina }}
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="paginacion__numero">{{ $pagina }}</a>
                                @endif
                            @endforeach
                        </div>

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

    @if ($puedeGestionarStock)
        <div class="modal" id="modal-ingreso-stock" hidden>
            <div class="modal__fondo js-cerrar-ingreso"></div>

            <section
                class="modal__contenido"
                role="dialog"
                aria-modal="true"
                aria-labelledby="titulo-modal-ingreso"
            >
                <button
                    type="button"
                    class="modal__cerrar js-cerrar-ingreso"
                    aria-label="Cerrar formulario"
                >
                    ×
                </button>

                <p class="modal__seccion">Ingreso externo de mercadería</p>
                <h2 id="titulo-modal-ingreso">Sumar unidades recibidas</h2>

                <p>
                    <strong>Esta operación solamente suma stock.</strong>
                    Para mover unidades entre sucursales cerrá esta ventana y
                    utilizá “Transferir a otra sucursal”.
                </p>

                <div class="modal__producto">
                    <strong id="modal-producto">Producto</strong>
                    <span id="modal-ubicacion">Código interno</span>
                </div>

                <form method="POST" id="formulario-ingreso-stock">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="operacion_stock" value="ingreso">

                    <input type="hidden" name="buscar" value="{{ $busqueda }}">
                    <input type="hidden" name="idsucursal_filtro" value="{{ $idSucursal }}">
                    <input type="hidden" name="disponibilidad" value="{{ $disponibilidad }}">

                    <div class="campo-modal">
                        <label for="idsucursal_destino_ingreso">
                            Sucursal que recibirá el ingreso *
                        </label>
                        <select
                            id="idsucursal_destino_ingreso"
                            name="idsucursal_destino"
                            required
                        >
                            <option value="">Seleccionar sucursal</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->idsucursal }}">
                                    {{ $sucursal->nombre }}
                                    @if ($sucursal->tipo)
                                        — {{ $sucursal->tipo }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <small>
                            La ubicación corresponde a la fila seleccionada y no
                            puede cambiarse desde un ingreso.
                        </small>
                    </div>

                    <div class="modal__stock-actual">
                        <span>Stock actual</span>
                        <strong id="modal-stock-actual">0 unidades</strong>
                    </div>

                    <div class="campo-modal">
                        <label for="cantidad_ingreso">Cantidad recibida *</label>
                        <input
                            type="number"
                            id="cantidad_ingreso"
                            name="cantidad_ingreso"
                            min="1"
                            max="999999"
                            step="1"
                            inputmode="numeric"
                            required
                        >
                        <small>Esta cantidad se sumará al stock actual.</small>
                    </div>

                    <div class="modal__fila">
                        <div class="campo-modal">
                            <label for="stockminimo">Stock mínimo *</label>
                            <input
                                type="number"
                                id="stockminimo"
                                name="stockminimo"
                                min="0"
                                max="999999"
                                step="1"
                                inputmode="numeric"
                                required
                            >
                        </div>

                        <div class="campo-modal">
                            <label for="stockmaximo">Stock máximo</label>
                            <input
                                type="number"
                                id="stockmaximo"
                                name="stockmaximo"
                                min="0"
                                max="999999"
                                step="1"
                                inputmode="numeric"
                            >
                            <small>Podés dejarlo vacío.</small>
                        </div>
                    </div>

                    <p class="modal__error" id="error-limites-stock" hidden></p>

                    <div class="modal__acciones">
                        <button type="button" class="boton boton--secundario js-cerrar-ingreso">
                            Cancelar
                        </button>
                        <button type="submit" class="boton boton--primario">
                            Guardar ingreso
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div class="modal" id="modal-transferencia-stock" hidden>
            <div class="modal__fondo js-cerrar-transferencia"></div>

            <section
                class="modal__contenido"
                role="dialog"
                aria-modal="true"
                aria-labelledby="titulo-modal-transferencia"
            >
                <button
                    type="button"
                    class="modal__cerrar js-cerrar-transferencia"
                    aria-label="Cerrar formulario"
                >
                    ×
                </button>

                <p class="modal__seccion">Movimiento entre sucursales</p>
                <h2 id="titulo-modal-transferencia">Transferir unidades</h2>

                <div class="modal__producto">
                    <strong id="transferencia-producto">Producto</strong>
                    <span id="transferencia-codigo">Código interno</span>
                </div>

                <form method="POST" id="formulario-transferencia-stock">
                    @csrf

                    <input type="hidden" name="operacion_stock" value="transferencia">

                    <input type="hidden" name="buscar" value="{{ $busqueda }}">
                    <input type="hidden" name="idsucursal_filtro" value="{{ $idSucursal }}">
                    <input type="hidden" name="disponibilidad" value="{{ $disponibilidad }}">

                    <div class="modal__stock-actual">
                        <span id="transferencia-origen">Sucursal de origen</span>
                        <strong id="transferencia-disponible">0 disponibles</strong>
                    </div>

                    <div class="campo-modal">
                        <label for="idsucursal_destino_transferencia">
                            Sucursal de destino *
                        </label>
                        <select
                            id="idsucursal_destino_transferencia"
                            name="idsucursal_destino"
                            required
                        >
                            <option value="">Seleccionar sucursal</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->idsucursal }}">
                                    {{ $sucursal->nombre }}
                                    @if ($sucursal->tipo)
                                        — {{ $sucursal->tipo }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <small>No puede ser la misma ubicación de origen.</small>
                    </div>

                    <div class="modal__stock-actual modal__stock-actual--destino">
                        <span>Stock actual en destino</span>
                        <strong id="transferencia-stock-destino">Seleccioná una sucursal</strong>
                    </div>

                    <div class="campo-modal">
                        <label for="cantidad_transferencia">Cantidad a transferir *</label>
                        <input
                            type="number"
                            id="cantidad_transferencia"
                            name="cantidad_transferencia"
                            min="1"
                            max="999999"
                            step="1"
                            inputmode="numeric"
                            required
                        >
                    </div>

                    <div class="modal__fila">
                        <div class="campo-modal">
                            <label for="stockminimo_destino">Mínimo en destino *</label>
                            <input
                                type="number"
                                id="stockminimo_destino"
                                name="stockminimo_destino"
                                min="0"
                                max="999999"
                                step="1"
                                inputmode="numeric"
                                required
                            >
                        </div>

                        <div class="campo-modal">
                            <label for="stockmaximo_destino">Máximo en destino</label>
                            <input
                                type="number"
                                id="stockmaximo_destino"
                                name="stockmaximo_destino"
                                min="0"
                                max="999999"
                                step="1"
                                inputmode="numeric"
                            >
                            <small>Podés dejarlo vacío.</small>
                        </div>
                    </div>

                    <p class="modal__error" id="error-transferencia-stock" hidden></p>

                    <div class="modal__acciones">
                        <button type="button" class="boton boton--secundario js-cerrar-transferencia">
                            Cancelar
                        </button>
                        <button type="submit" class="boton boton--primario">
                            Confirmar transferencia
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif

    <script src="{{ asset('js/stock/index.js') }}" defer></script>
</body>
</html>
