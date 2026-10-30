<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Nueva venta | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/ventas/crear.css') }}?v=20261030-ui2">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>

<body class="con-barra-lateral">
    @include('partials.barra-lateral')

    <main
        class="pagina-venta"
        id="aplicacion-venta"
        data-url-productos="{{ route('ventas.productos.buscar') }}"
        data-url-producto-codigo="{{ route('ventas.productos.codigo') }}"
        data-url-clientes="{{ route('ventas.clientes.buscar') }}"
        data-url-guardar="{{ route('ventas.guardar') }}"
        data-url-mercadopago="{{ route('ventas.qr.crear') }}"
        data-url-mercadopago-consultar="{{ route('ventas.qr.consultar') }}"
        data-url-mercadopago-confirmar="{{ route('ventas.qr.confirmar') }}"
        data-url-mercadopago-recuperar="{{ route('ventas.qr.recuperar') }}"
    >
        <header class="encabezado-principal encabezado-principal--compacto">
            <div>
                <p class="encabezado-principal__seccion">Administración comercial</p>
                <h1>Nueva venta</h1>
            </div>

            <a
                href="{{ route('ventas.index') }}"
                class="boton boton--secundario"
                id="volver-ventas"
            >
                Volver a ventas
            </a>
        </header>

        <section class="aviso aviso--compacto" role="status">
            <span class="aviso__icono" aria-hidden="true">i</span>
            <div>
                <strong>Venta rápida</strong>
                <p>
                    F2 busca clientes. Para vender, escribí o escaneá el código de barras y presioná Enter.
                    Si no tenés el código, F3 abre la búsqueda manual de productos.
                </p>
            </div>
        </section>

        <div class="venta-principal venta-principal--compacta">
            <div class="venta-configuracion">
                <section class="panel panel--compacto panel--sucursal">
                    <div class="panel__cabecera panel__cabecera--compacta">
                        <span class="panel__numero">01</span>
                        <div>
                            <h2>Elegir sucursal</h2>
                            <p>Es la sucursal que registra la venta.</p>
                        </div>
                    </div>

                    <div class="panel__contenido panel__contenido--compacto">
                        <div class="campo">
                            @if ($puedeElegirSucursal)
                                <label for="idsucursal">Sucursal o depósito</label>
                                <select id="idsucursal" name="idsucursal">
                                    @foreach ($sucursales as $sucursal)
                                        <option
                                            value="{{ $sucursal->idsucursal }}"
                                            @selected(
                                                (int) $idSucursalInicial
                                                === (int) $sucursal->idsucursal
                                            )
                                        >
                                            {{ $sucursal->nombre }}
                                            @if ($sucursal->tipo)
                                                — {{ $sucursal->tipo }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                @php($sucursalAsignada = $sucursales->first())

                                <span class="campo__etiqueta">Sucursal o depósito</span>
                                <input
                                    type="hidden"
                                    id="idsucursal"
                                    name="idsucursal"
                                    value="{{ $idSucursalInicial }}"
                                >

                                <div class="sucursal-fija sucursal-fija--compacta" aria-label="Sucursal asignada">
                                    <div>
                                        <strong>{{ $sucursalAsignada->nombre }}</strong>
                                        @if ($sucursalAsignada->tipo)
                                            <span>{{ $sucursalAsignada->tipo }}</span>
                                        @endif
                                    </div>
                                    <em>Asignada</em>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="panel panel--compacto panel--cliente">
                    <div class="panel__cabecera panel__cabecera--compacta">
                        <span class="panel__numero">02</span>
                        <div>
                            <h2>Identificar cliente</h2>
                            <p>1 = Consumidor Final.</p>
                        </div>
                    </div>

                    <div class="panel__contenido panel__contenido--compacto">
                        <div class="cliente-formulario cliente-formulario--compacto">
                            <div class="campo">
                                <label for="codigo-cliente">Código del cliente</label>
                                <div class="campo-con-accion">
                                    <input
                                        type="text"
                                        id="codigo-cliente"
                                        value="1"
                                        maxlength="20"
                                        autocomplete="off"
                                        spellcheck="false"
                                        aria-describedby="ayuda-codigo-cliente estado-cliente"
                                    >
                                    <button
                                        type="button"
                                        class="boton boton--secundario boton-busqueda-cliente"
                                        id="abrir-buscador-clientes"
                                    >
                                        Buscar <kbd>F2</kbd>
                                    </button>
                                </div>
                                <small id="ayuda-codigo-cliente">
                                    Ejemplo: 1 o CLI-000001. Presioná Enter para comprobarlo.
                                </small>
                            </div>

                            <div class="campo campo--cliente-seleccionado">
                                <label for="nombre-cliente">Cliente seleccionado</label>
                                <input
                                    type="text"
                                    id="nombre-cliente"
                                    value="Consumidor Final"
                                    readonly
                                    tabindex="-1"
                                >
                                <input type="hidden" id="idcliente" name="idcliente" value="">
                                <input
                                    type="hidden"
                                    id="tipo-cliente"
                                    name="tipo_cliente"
                                    value="consumidor_final"
                                >
                            </div>
                        </div>

                        <p
                            class="estado-cliente estado-cliente--correcto estado-cliente--compacto"
                            id="estado-cliente"
                            role="status"
                            aria-live="polite"
                        >
                            Consumidor Final seleccionado.
                        </p>
                    </div>
                </section>
            </div>

            <section class="panel panel--productos">
                <div class="panel__cabecera panel__cabecera--compacta">
                    <span class="panel__numero">03</span>
                    <div>
                        <h2>Agregar producto</h2>
                        <p>Código de barras para venta rápida. También podés escanearlo con la cámara del celular.</p>
                    </div>
                </div>

                <div class="panel__contenido panel__contenido--productos">
                    <div class="entrada-producto-rapida">
                        <div class="campo campo--codigo-barras">
                            <label for="codigo-barras-producto">Código de barras</label>
                            <input
                                type="text"
                                id="codigo-barras-producto"
                                placeholder="Escaneá o escribí el código y presioná Enter"
                                maxlength="100"
                                autocomplete="off"
                                spellcheck="false"
                                inputmode="numeric"
                                aria-describedby="ayuda-codigo-barras estado-producto"
                            >
                            <small id="ayuda-codigo-barras">
                                El código se trata como texto y conserva todos los ceros iniciales. La búsqueda prioriza la coincidencia exacta.
                            </small>
                        </div>

                        <div class="acciones-producto-rapida">
                            <button
                                type="button"
                                class="boton boton--primario boton-escanear-camara"
                                id="abrir-escaner-productos"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M9 4.5 10.2 3h3.6L15 4.5h3A3 3 0 0 1 21 7.5v9A3 3 0 0 1 18 19.5H6A3 3 0 0 1 3 16.5v-9a3 3 0 0 1 3-3h3Zm3 3A4.5 4.5 0 1 0 12 16.5 4.5 4.5 0 0 0 12 7.5Zm0 2A2.5 2.5 0 1 1 12 14.5 2.5 2.5 0 0 1 12 9.5Z"/>
                                </svg>
                                Escanear con cámara
                            </button>

                            <div class="busqueda-manual-producto">
                                <span>¿No tenés el código?</span>
                                <button
                                    type="button"
                                    class="boton boton--secundario boton-busqueda-producto"
                                    id="abrir-buscador-productos"
                                >
                                    Buscar producto <kbd>F3</kbd>
                                </button>
                            </div>
                        </div>
                    </div>

                    <p
                        class="estado-producto estado-producto--listo"
                        id="estado-producto"
                        role="status"
                        aria-live="polite"
                    >
                        Listo para ingresar o escanear un código de barras.
                    </p>
                </div>
            </section>

            <section class="carrito panel carrito--visible" aria-labelledby="titulo-carrito">
                <div class="panel__cabecera panel__cabecera--compacta carrito__cabecera">
                    <span class="panel__numero">04</span>
                    <div>
                        <h2 id="titulo-carrito">Productos de la venta</h2>
                        <p id="cantidad-productos-carrito">0 productos agregados</p>
                    </div>
                </div>

                <div class="carrito__contenido carrito__contenido--compacto">
                    <div class="tabla-carrito-contenedor" id="tabla-carrito">
                        <table class="tabla-carrito">
                            <thead>
                                <tr>
                                    <th scope="col">Código</th>
                                    <th scope="col">Descripción</th>
                                    <th scope="col">Stock</th>
                                    <th scope="col">Precio unitario</th>
                                    <th scope="col">Cantidad</th>
                                    <th scope="col">Subtotal</th>
                                    <th scope="col">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="carrito-lista" id="carrito-lista"></tbody>
                        </table>
                    </div>
                </div>

                <div class="carrito__pie carrito__pie--fijo">
                    <div class="carrito__resumen-venta">
                        <div class="carrito__descuento-adicional">
                            <div class="carrito__descuento-cabecera">
                                <span class="carrito__descuento-icono" aria-hidden="true">%</span>
                                <div>
                                    <label for="descuento-adicional">Descuento adicional</label>
                                    <small id="ayuda-descuento-adicional">
                                        Se aplica después del descuento automático del cliente.
                                    </small>
                                </div>
                            </div>

                            <div class="carrito__descuento-control">
                                <input
                                    type="number"
                                    id="descuento-adicional"
                                    name="descuento_adicional"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="0"
                                    inputmode="decimal"
                                    aria-describedby="ayuda-descuento-adicional"
                                >
                                <span aria-hidden="true">%</span>
                            </div>
                        </div>

                        <div class="carrito__medio-pago">
                            <div>
                                <label for="metodo-pago">Medio de pago *</label>
                                <small>El recargo se toma de la configuración vigente.</small>
                            </div>

                            <select id="metodo-pago" name="idmetodopago" @disabled($metodosPago->isEmpty())>
                                @forelse ($metodosPago as $metodoPago)
                                    <option
                                        value="{{ $metodoPago->idmetodopago }}"
                                        data-recargo="{{ number_format((float) $metodoPago->recargo, 2, '.', '') }}"
                                        @selected((int) $metodoPago->idmetodopago === (int) $idMetodoPagoInicial)
                                    >
                                        {{ $metodoPago->nombre }}
                                        @if ((float) $metodoPago->recargo > 0)
                                            — +{{ number_format((float) $metodoPago->recargo, 2, ',', '.') }}%
                                        @endif
                                    </option>
                                @empty
                                    <option value="">Sin medios de pago activos</option>
                                @endforelse
                            </select>
                        </div>

                        <div class="carrito__total carrito__total--desglosado">
                            <div class="carrito__total-linea">
                                <span>Después de descuentos</span>
                                <strong id="base-pago">$ 0,00</strong>
                            </div>
                            <div class="carrito__total-linea">
                                <span id="etiqueta-recargo">Recargo 0%</span>
                                <strong id="importe-recargo">$ 0,00</strong>
                            </div>
                            <div class="carrito__total-linea carrito__total-linea--final">
                                <span>Total a cobrar</span>
                                <strong id="total-venta">$ 0,00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="carrito__acciones">
                        <button
                            type="button"
                            class="boton boton--primario boton--ancho"
                            id="confirmar-venta"
                            disabled
                        >
                            Confirmar venta
                        </button>

                        <small id="estado-confirmacion" role="status" aria-live="polite">
                            Agregá al menos un producto para confirmar la venta.
                        </small>
                    </div>
                </div>
                <section id="panel-mercadopago" class="panel-qr-seguimiento" hidden aria-label="Seguimiento del cobro con Mercado Pago">
                    <div class="panel-qr-seguimiento__encabezado">
                        <div>
                            <span class="panel-qr-seguimiento__etiqueta">MERCADO PAGO · CAJA 1</span>
                            <h3>Cobro con QR fijo <span class="panel-qr-seguimiento__prueba">Pruebas</span></h3>
                        </div>
                        <div class="panel-qr-seguimiento__importe">
                            <span>Importe de la orden</span>
                            <strong id="importe-mercadopago"></strong>
                        </div>
                    </div>
                    <div class="panel-qr-seguimiento__cuerpo">
                        <div class="panel-qr-seguimiento__instrucciones">
                            <p>El cliente escanea el QR fijo impreso de Caja 1. Messirve verifica el pago mientras esta pantalla permanece abierta.</p>
                            <a id="enlace-mercadopago" class="boton boton--primario" href="#" hidden>QR fijo impreso en mostrador</a>
                            <p id="estado-mercadopago" class="panel-qr-seguimiento__estado" role="status" aria-live="polite"></p>
                        </div>
                        <div class="panel-qr-seguimiento__detalle">
                            <label for="payment-id-mercadopago">ID de orden</label>
                            <input id="payment-id-mercadopago" type="text" autocomplete="off" placeholder="Orden QR" readonly>
                            <div class="panel-qr-seguimiento__referencia">Referencia: <code id="referencia-mercadopago"></code></div>
                            <button type="button" class="boton boton--secundario" id="consultar-mercadopago">Consultar estado</button>
                        </div>
                    </div>
                    <p class="panel-qr-seguimiento__advertencia">Solo se registra una venta con el pago acreditado y verificado. Si falla el registro, no vuelvas a cobrar: conciliá primero la orden.</p>
                </section>
            </section>
        </div>
    </main>

    <div
        class="modal-clientes"
        id="modal-clientes"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-modal-clientes"
        hidden
    >
        <button
            type="button"
            class="modal-clientes__fondo"
            data-cerrar-modal-clientes
            aria-label="Cerrar buscador de clientes"
        ></button>

        <section class="modal-clientes__contenido">
            <header class="modal-clientes__cabecera">
                <div>
                    <p>Selección de cliente</p>
                    <h2 id="titulo-modal-clientes">Buscar clientes</h2>
                </div>
                <button
                    type="button"
                    class="modal-clientes__cerrar"
                    data-cerrar-modal-clientes
                    aria-label="Cerrar"
                >×</button>
            </header>

            <div class="modal-clientes__cuerpo">
                <div class="campo">
                    <label for="buscar-cliente-modal">Código, nombre, documento, email o teléfono</label>
                    <input
                        type="search"
                        id="buscar-cliente-modal"
                        maxlength="100"
                        placeholder="Ejemplo: CLI-000001, Ramiro o DNI"
                        autocomplete="off"
                    >
                    <small>Ingresá al menos dos caracteres. El código 1 también está disponible.</small>
                </div>

                <div
                    class="clientes-resultados"
                    id="clientes-resultados"
                    aria-live="polite"
                >
                    <div class="mensaje-clientes">
                        <span aria-hidden="true">⌕</span>
                        <p>Escribí para buscar un cliente.</p>
                    </div>
                </div>
            </div>

            <footer class="modal-clientes__pie">
                <button
                    type="button"
                    class="boton boton--secundario"
                    data-cerrar-modal-clientes
                >Cancelar</button>
            </footer>
        </section>
    </div>


    <div
        class="modal-clientes modal-productos"
        id="modal-productos"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-modal-productos"
        hidden
    >
        <button
            type="button"
            class="modal-clientes__fondo"
            data-cerrar-modal-productos
            aria-label="Cerrar buscador de productos"
        ></button>

        <section class="modal-clientes__contenido modal-productos__contenido">
            <header class="modal-clientes__cabecera">
                <div>
                    <p>Selección de producto</p>
                    <h2 id="titulo-modal-productos">Buscar productos</h2>
                </div>
                <button
                    type="button"
                    class="modal-clientes__cerrar"
                    data-cerrar-modal-productos
                    aria-label="Cerrar"
                >×</button>
            </header>

            <div class="modal-clientes__cuerpo">
                <div class="campo">
                    <label for="buscar-producto-modal">Nombre, código interno, fabricante, marca o código de barras</label>
                    <input
                        type="search"
                        id="buscar-producto-modal"
                        maxlength="100"
                        placeholder="Ejemplo: rodillo, MES-000005 o Tersuave"
                        autocomplete="off"
                    >
                    <small>Ingresá al menos dos caracteres. Se muestran hasta 20 productos con stock disponible.</small>
                </div>

                <div
                    class="clientes-resultados productos-resultados"
                    id="productos-resultados"
                    aria-live="polite"
                >
                    <div class="mensaje-clientes">
                        <span aria-hidden="true">⌕</span>
                        <p>Escribí para buscar un producto.</p>
                    </div>
                </div>
            </div>

            <footer class="modal-clientes__pie">
                <button
                    type="button"
                    class="boton boton--secundario"
                    data-cerrar-modal-productos
                >Cancelar</button>
            </footer>
        </section>
    </div>


    <div
        class="modal-clientes modal-escaner"
        id="modal-escaner-productos"
        role="dialog"
        aria-modal="true"
        aria-labelledby="titulo-modal-escaner-productos"
        hidden
    >
        <button
            type="button"
            class="modal-clientes__fondo"
            data-cerrar-modal-escaner
            aria-label="Cerrar escáner de código de barras"
        ></button>

        <section class="modal-clientes__contenido modal-escaner__contenido">
            <header class="modal-clientes__cabecera">
                <div>
                    <p>Escaneo de producto</p>
                    <h2 id="titulo-modal-escaner-productos">Escanear código de barras</h2>
                </div>
                <button
                    type="button"
                    class="modal-clientes__cerrar"
                    data-cerrar-modal-escaner
                    aria-label="Cerrar"
                >×</button>
            </header>

            <div class="modal-clientes__cuerpo modal-escaner__cuerpo">
                <p class="modal-escaner__instruccion">
                    Apuntá la cámara al código de barras y dejá visibles los márgenes blancos de los costados.
                    El lector detectará el formato, conservará los ceros iniciales y agregará el producto automáticamente.
                </p>

                <div id="lector-codigo-barras" class="lector-codigo-barras"></div>

                <p
                    id="estado-escaner-productos"
                    class="estado-escaner estado-escaner--listo"
                    role="status"
                    aria-live="polite"
                >
                    Preparando cámara…
                </p>

                <div class="modal-escaner__alternativa">
                    <div>
                        <strong>¿La cámara en vivo no abre?</strong>
                        <span>Podés tomar una foto del código y analizarla desde el celular.</span>
                    </div>
                    <button
                        type="button"
                        class="boton boton--secundario"
                        id="usar-foto-codigo-barras"
                    >
                        Tomar foto
                    </button>
                    <input
                        type="file"
                        id="archivo-codigo-barras"
                        accept="image/*"
                        capture="environment"
                        hidden
                    >
                </div>
            </div>

            <footer class="modal-clientes__pie">
                <button
                    type="button"
                    class="boton boton--secundario"
                    data-cerrar-modal-escaner
                >Cancelar</button>
            </footer>
        </section>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js" defer></script>
    <script src="{{ asset('js/ventas/crear.js') }}?v=20261030-ui2" defer></script>
</body>
</html>
