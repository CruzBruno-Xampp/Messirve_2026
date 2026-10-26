(() => {
    'use strict';

    const aplicacion = document.getElementById('aplicacion-venta');
    const sucursal = document.getElementById('idsucursal');
    const codigoBarrasProducto = document.getElementById('codigo-barras-producto');
    const estadoProducto = document.getElementById('estado-producto');
    const abrirBuscadorProductos = document.getElementById('abrir-buscador-productos');
    const abrirEscanerProductos = document.getElementById('abrir-escaner-productos');
    const modalEscanerProductos = document.getElementById('modal-escaner-productos');
    const lectorCodigoBarras = document.getElementById('lector-codigo-barras');
    const estadoEscanerProductos = document.getElementById('estado-escaner-productos');
    const usarFotoCodigoBarras = document.getElementById('usar-foto-codigo-barras');
    const archivoCodigoBarras = document.getElementById('archivo-codigo-barras');
    const modalProductos = document.getElementById('modal-productos');
    const buscarProductoModal = document.getElementById('buscar-producto-modal');
    const productosResultados = document.getElementById('productos-resultados');
    const carritoLista = document.getElementById('carrito-lista');
    const cantidadCarrito = document.getElementById('cantidad-productos-carrito');
    const totalVenta = document.getElementById('total-venta');
    const basePago = document.getElementById('base-pago');
    const etiquetaRecargo = document.getElementById('etiqueta-recargo');
    const importeRecargo = document.getElementById('importe-recargo');
    const metodoPago = document.getElementById('metodo-pago');
    const descuentoAdicional = document.getElementById('descuento-adicional');
    const botonConfirmarVenta = document.getElementById('confirmar-venta');
    const estadoConfirmacion = document.getElementById('estado-confirmacion');
    const codigoCliente = document.getElementById('codigo-cliente');
    const nombreCliente = document.getElementById('nombre-cliente');
    const idCliente = document.getElementById('idcliente');
    const tipoCliente = document.getElementById('tipo-cliente');
    const estadoCliente = document.getElementById('estado-cliente');
    const abrirBuscadorClientes = document.getElementById('abrir-buscador-clientes');
    const modalClientes = document.getElementById('modal-clientes');
    const buscarClienteModal = document.getElementById('buscar-cliente-modal');
    const clientesResultados = document.getElementById('clientes-resultados');
    const volverVentas = document.getElementById('volver-ventas');

    if (
        !aplicacion
        || !sucursal
        || !codigoBarrasProducto
        || !estadoProducto
        || !abrirBuscadorProductos
        || !abrirEscanerProductos
        || !modalEscanerProductos
        || !lectorCodigoBarras
        || !estadoEscanerProductos
        || !usarFotoCodigoBarras
        || !archivoCodigoBarras
        || !modalProductos
        || !buscarProductoModal
        || !productosResultados
        || !carritoLista
        || !cantidadCarrito
        || !totalVenta
        || !basePago
        || !etiquetaRecargo
        || !importeRecargo
        || !metodoPago
        || !descuentoAdicional
        || !botonConfirmarVenta
        || !estadoConfirmacion
        || !codigoCliente
        || !nombreCliente
        || !idCliente
        || !tipoCliente
        || !estadoCliente
        || !abrirBuscadorClientes
        || !modalClientes
        || !buscarClienteModal
        || !clientesResultados
        || !volverVentas
    ) {
        return;
    }

    const urlProductos = aplicacion.dataset.urlProductos;
    const urlProductoCodigo = aplicacion.dataset.urlProductoCodigo;
    const urlClientes = aplicacion.dataset.urlClientes;
    const urlGuardar = aplicacion.dataset.urlGuardar;
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const carrito = new Map();
    const formatoMoneda = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2,
    });

    let temporizadorProductos;
    let solicitudProductosActiva;
    let solicitudCodigoActiva;
    let productosModalActuales = [];
    let temporizadorClientes;
    let solicitudClientesActiva;
    let codigoClienteConfirmado = '1';
    let tipoClienteSeleccionadoNombre = 'CONSUMIDOR FINAL';
    let descuentoClienteLocal = 0;
    let elementoConFocoAntesDelModal = null;
    let elementoConFocoAntesDelModalProductos = null;
    let confirmandoVenta = false;
    let permitirSalida = false;
    let sucursalAnterior = sucursal.value;
    let escanerCodigoBarras = null;
    let escanerCamaraActivo = false;
    let procesandoCodigoEscaneado = false;
    let elementoConFocoAntesDelEscaner = null;

    function normalizarCodigoCliente(valor) {
        return valor.trim().toUpperCase();
    }

    function normalizarPorcentaje(valor) {
        const numero = Number(valor);

        if (!Number.isFinite(numero)) {
            return 0;
        }

        return Math.max(0, Math.min(100, numero));
    }

    function formatearPorcentaje(valor) {
        return new Intl.NumberFormat('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(normalizarPorcentaje(valor));
    }

    function redondearMoneda(valor) {
        return Math.round((Number(valor) + Number.EPSILON) * 100) / 100;
    }

    function porcentajeDescuentoAdicionalActual() {
        if (descuentoAdicional.value === '') {
            return 0;
        }

        const numero = Number(descuentoAdicional.value);

        if (!Number.isFinite(numero)) {
            return 0;
        }

        return Math.max(0, Math.min(100, numero));
    }

    function descuentoAdicionalValido() {
        if (descuentoAdicional.value === '') {
            return true;
        }

        const numero = Number(descuentoAdicional.value);
        return Number.isFinite(numero) && numero >= 0 && numero <= 100;
    }

    function porcentajeRecargoActual() {
        const opcion = metodoPago.options[metodoPago.selectedIndex];

        if (!opcion || metodoPago.value === '') {
            return 0;
        }

        const numero = Number(opcion.dataset.recargo ?? 0);
        return Number.isFinite(numero) ? Math.max(0, Math.min(100, numero)) : 0;
    }

    function metodoPagoValido() {
        if (metodoPago.value === '') {
            return false;
        }

        const opcion = metodoPago.options[metodoPago.selectedIndex];
        return Boolean(opcion && !opcion.disabled);
    }

    function calcularImportesProducto(producto, cantidad = 1) {
        const pvp = redondearMoneda(producto?.pvp ?? 0);
        const unidades = Math.max(1, Number(cantidad) || 1);
        const subtotal = redondearMoneda(pvp * unidades);
        const descuentoCliente = redondearMoneda(
            subtotal * (descuentoClienteLocal / 100)
        );
        const subtotalTrasCliente = redondearMoneda(subtotal - descuentoCliente);
        const porcentajeAdicional = porcentajeDescuentoAdicionalActual();
        const descuentoAdicionalImporte = redondearMoneda(
            subtotalTrasCliente * (porcentajeAdicional / 100)
        );
        const descuento = redondearMoneda(
            descuentoCliente + descuentoAdicionalImporte
        );
        const total = redondearMoneda(subtotal - descuento);

        return {
            pvp,
            subtotal,
            descuentoCliente,
            descuentoAdicional: descuentoAdicionalImporte,
            descuento,
            total,
        };
    }

    function precioUnitarioAplicado(producto) {
        return calcularImportesProducto(producto, 1).total;
    }

    function actualizarBloqueoModal() {
        const hayModalAbierto = !modalClientes.hidden
            || !modalProductos.hidden
            || !modalEscanerProductos.hidden;
        document.body.classList.toggle('modal-abierto', hayModalAbierto);
    }

    function mostrarEstadoProducto(tipo, mensaje) {
        estadoProducto.className = `estado-producto estado-producto--${tipo}`;
        estadoProducto.textContent = mensaje;
    }

    function mostrarEstadoEscaner(tipo, mensaje) {
        estadoEscanerProductos.className = `estado-escaner estado-escaner--${tipo}`;
        estadoEscanerProductos.textContent = mensaje;
    }

    function hayVentaEnCurso() {
        return carrito.size > 0;
    }

    function seleccionarCliente(cliente) {
        const esConsumidorFinal = cliente.consumidor_final === true;
        const codigo = esConsumidorFinal
            ? '1'
            : normalizarCodigoCliente(String(cliente.codigo ?? ''));

        codigoCliente.value = codigo;
        codigoClienteConfirmado = codigo;
        nombreCliente.value = String(cliente.nombre_completo ?? '');
        idCliente.value = esConsumidorFinal ? '' : String(cliente.idcliente ?? '');
        tipoCliente.value = esConsumidorFinal
            ? 'consumidor_final'
            : 'registrado';

        tipoClienteSeleccionadoNombre = String(
            cliente.tipo_cliente_nombre
            ?? (esConsumidorFinal ? 'CONSUMIDOR FINAL' : 'SIN TIPO')
        );
        descuentoClienteLocal = esConsumidorFinal
            ? 0
            : normalizarPorcentaje(cliente.descuento_local);

        codigoCliente.removeAttribute('aria-invalid');
        estadoCliente.className = 'estado-cliente estado-cliente--correcto';

        if (esConsumidorFinal) {
            estadoCliente.textContent = 'Consumidor Final seleccionado · Sin descuento.';
        } else if (descuentoClienteLocal > 0) {
            estadoCliente.textContent = `${cliente.nombre_completo} seleccionado · ${tipoClienteSeleccionadoNombre} · ${formatearPorcentaje(descuentoClienteLocal)}% de descuento local.`;
        } else if (cliente.aplica_local === false && Number(cliente.descuento_tipo ?? 0) > 0) {
            estadoCliente.textContent = `${cliente.nombre_completo} seleccionado · ${tipoClienteSeleccionadoNombre} · El beneficio de este tipo no aplica en ventas locales.`;
        } else {
            estadoCliente.textContent = `${cliente.nombre_completo} seleccionado · ${tipoClienteSeleccionadoNombre} · Sin descuento local.`;
        }

        dibujarCarrito();
        actualizarEstadoConfirmacion();
    }

    function marcarClienteInvalido(mensaje) {
        codigoCliente.setAttribute('aria-invalid', 'true');
        nombreCliente.value = 'Cliente sin validar';
        idCliente.value = '';
        tipoCliente.value = '';
        tipoClienteSeleccionadoNombre = '';
        descuentoClienteLocal = 0;
        dibujarCarrito();
        estadoCliente.className = 'estado-cliente estado-cliente--error';
        estadoCliente.textContent = mensaje;
        actualizarEstadoConfirmacion();
    }

    async function solicitarClientes(termino, signal) {
        const url = new URL(urlClientes, window.location.origin);
        url.searchParams.set('buscar', termino);

        const respuesta = await fetch(url, {
            signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!respuesta.ok) {
            let mensaje = 'No se pudo buscar el cliente.';

            if (respuesta.status === 422) {
                const datosError = await respuesta.json().catch(() => null);
                mensaje = datosError?.errors?.buscar?.[0] ?? mensaje;
            }

            throw new Error(mensaje);
        }

        const datos = await respuesta.json();
        return Array.isArray(datos.clientes) ? datos.clientes : [];
    }

    async function resolverCodigoCliente() {
        const termino = normalizarCodigoCliente(codigoCliente.value);

        if (termino === codigoClienteConfirmado && tipoCliente.value !== '') {
            return true;
        }

        if (termino === '') {
            marcarClienteInvalido('Ingresá 1 o el código de un cliente registrado.');
            return false;
        }

        if (termino !== '1' && !/^CLI-\d{1,10}$/.test(termino)) {
            marcarClienteInvalido('El código debe ser 1 o tener el formato CLI-000001.');
            return false;
        }

        solicitudClientesActiva?.abort();
        solicitudClientesActiva = new AbortController();
        estadoCliente.className = 'estado-cliente estado-cliente--cargando';
        estadoCliente.textContent = 'Comprobando cliente…';

        try {
            const clientes = await solicitarClientes(
                termino,
                solicitudClientesActiva.signal
            );
            const clienteExacto = clientes.find(
                (cliente) => normalizarCodigoCliente(String(cliente.codigo)) === termino
            );

            if (!clienteExacto) {
                marcarClienteInvalido('No existe un cliente activo con ese código.');
                return false;
            }

            seleccionarCliente(clienteExacto);
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                marcarClienteInvalido(error.message || 'No se pudo comprobar el cliente.');
            }

            return false;
        }
    }

    function abrirModalClientes() {
        if (!modalProductos.hidden) {
            cerrarModalProductos(false);
        }

        elementoConFocoAntesDelModal = document.activeElement;
        modalClientes.hidden = false;
        actualizarBloqueoModal();
        buscarClienteModal.value = '';
        mostrarMensajeClientes('⌕', 'Escribí para buscar un cliente.');
        window.setTimeout(() => buscarClienteModal.focus(), 0);
    }

    function cerrarModalClientes() {
        solicitudClientesActiva?.abort();
        modalClientes.hidden = true;
        actualizarBloqueoModal();

        if (elementoConFocoAntesDelModal instanceof HTMLElement) {
            elementoConFocoAntesDelModal.focus();
        }
    }

    function mostrarMensajeClientes(icono, texto) {
        clientesResultados.replaceChildren();

        const contenedor = document.createElement('div');
        contenedor.className = 'mensaje-clientes';

        const simbolo = document.createElement('span');
        simbolo.setAttribute('aria-hidden', 'true');
        simbolo.textContent = icono;

        const mensaje = document.createElement('p');
        mensaje.textContent = texto;

        contenedor.append(simbolo, mensaje);
        clientesResultados.append(contenedor);
    }

    function dibujarClientes(clientes) {
        clientesResultados.replaceChildren();

        if (clientes.length === 0) {
            mostrarMensajeClientes('!', 'No encontramos clientes activos.');
            return;
        }

        const lista = document.createElement('div');
        lista.className = 'lista-clientes';

        clientes.forEach((cliente) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'cliente-resultado';
            boton.dataset.cliente = JSON.stringify(cliente);

            const codigo = document.createElement('strong');
            codigo.textContent = cliente.codigo;

            const datos = document.createElement('span');
            datos.className = 'cliente-resultado__datos';

            const nombre = document.createElement('b');
            nombre.textContent = cliente.nombre_completo;

            const detalle = document.createElement('small');
            const partes = [
                cliente.documento ? `Documento: ${cliente.documento}` : null,
                cliente.email || null,
                cliente.telefono || null,
            ].filter(Boolean);
            detalle.textContent = partes.join(' · ') || 'Sin datos adicionales';

            datos.append(nombre, detalle);

            if (!cliente.consumidor_final) {
                const beneficio = document.createElement('small');
                beneficio.className = 'cliente-resultado__beneficio';
                const tipoNombre = String(cliente.tipo_cliente_nombre ?? 'SIN TIPO');
                const descuentoLocal = normalizarPorcentaje(cliente.descuento_local);

                beneficio.textContent = descuentoLocal > 0
                    ? `${tipoNombre} · ${formatearPorcentaje(descuentoLocal)}% descuento local`
                    : `${tipoNombre} · Sin descuento local`;
                datos.append(beneficio);
            }

            const accion = document.createElement('em');
            accion.textContent = 'Seleccionar';

            boton.append(codigo, datos, accion);
            lista.append(boton);
        });

        clientesResultados.append(lista);
    }

    async function buscarClientesEnModal() {
        const termino = buscarClienteModal.value.trim();

        if (termino !== '1' && termino.length < 2) {
            solicitudClientesActiva?.abort();
            mostrarMensajeClientes('⌕', 'Ingresá al menos dos caracteres para buscar.');
            return;
        }

        solicitudClientesActiva?.abort();
        solicitudClientesActiva = new AbortController();
        clientesResultados.classList.add('clientes-resultados--cargando');

        try {
            const clientes = await solicitarClientes(
                termino,
                solicitudClientesActiva.signal
            );
            dibujarClientes(clientes);
        } catch (error) {
            if (error.name !== 'AbortError') {
                mostrarMensajeClientes('!', error.message || 'No se pudo buscar.');
            }
        } finally {
            clientesResultados.classList.remove('clientes-resultados--cargando');
        }
    }

    function mostrarMensajeProductos(icono, texto) {
        productosResultados.replaceChildren();

        const contenedor = document.createElement('div');
        contenedor.className = 'mensaje-clientes';

        const simbolo = document.createElement('span');
        simbolo.setAttribute('aria-hidden', 'true');
        simbolo.textContent = icono;

        const mensaje = document.createElement('p');
        mensaje.textContent = texto;

        contenedor.append(simbolo, mensaje);
        productosResultados.append(contenedor);
    }

    function detalleStockProducto(producto) {
        if (!Array.isArray(producto.sucursales)) {
            return '';
        }

        return producto.sucursales
            .filter((ubicacion) => Number(ubicacion.stockactual) > 0)
            .map((ubicacion) => `${ubicacion.nombre}: ${ubicacion.stockactual}`)
            .join(' · ');
    }

    function dibujarProductosModal(productos) {
        productosResultados.replaceChildren();
        productosModalActuales = Array.isArray(productos) ? productos : [];

        if (productosModalActuales.length === 0) {
            mostrarMensajeProductos('!', 'No encontramos productos con stock disponible.');
            return;
        }

        const lista = document.createElement('div');
        lista.className = 'lista-productos-modal';

        productosModalActuales.forEach((producto) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'cliente-resultado producto-modal-resultado';
            boton.dataset.idproducto = producto.idproducto;

            const codigo = document.createElement('strong');
            codigo.textContent = producto.codigointerno || 'Sin código';

            const datos = document.createElement('span');
            datos.className = 'cliente-resultado__datos producto-modal-resultado__datos';

            const nombre = document.createElement('b');
            nombre.textContent = producto.descripcion;

            const detalle = document.createElement('small');
            const barras = producto.codigobarra ? `Barras: ${producto.codigobarra}` : 'Sin código de barras';
            const stock = `Local: ${Number(producto.stocklocal)} · Total: ${Number(producto.stocktotal)}`;
            const sucursales = detalleStockProducto(producto);
            detalle.textContent = [barras, stock, sucursales]
                .filter(Boolean)
                .join(' · ');

            datos.append(nombre, detalle);

            const accion = document.createElement('em');
            const existente = carrito.get(Number(producto.idproducto));
            const cantidadActual = existente?.cantidad ?? 0;
            const sinLugar = cantidadActual >= Number(producto.stocktotal);

            accion.textContent = sinLugar ? 'Stock completo' : 'Agregar';
            boton.disabled = sinLugar;
            boton.append(codigo, datos, accion);
            lista.append(boton);
        });

        productosResultados.append(lista);
    }

    function abrirModalProductos(productosIniciales = null) {
        if (!modalClientes.hidden) {
            cerrarModalClientes();
        }

        elementoConFocoAntesDelModalProductos = document.activeElement;
        modalProductos.hidden = false;
        actualizarBloqueoModal();
        buscarProductoModal.value = '';

        if (Array.isArray(productosIniciales) && productosIniciales.length > 0) {
            dibujarProductosModal(productosIniciales);
        } else {
            productosModalActuales = [];
            mostrarMensajeProductos('⌕', 'Escribí para buscar un producto.');
        }

        window.setTimeout(() => buscarProductoModal.focus(), 0);
    }

    function cerrarModalProductos(devolverFoco = true) {
        solicitudProductosActiva?.abort();
        modalProductos.hidden = true;
        actualizarBloqueoModal();

        if (devolverFoco) {
            codigoBarrasProducto.focus();
        }
    }

    async function solicitarProductosManual(termino, signal) {
        const url = new URL(urlProductos, window.location.origin);
        url.searchParams.set('buscar', termino);
        url.searchParams.set('idsucursal', sucursal.value);

        const respuesta = await fetch(url, {
            signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!respuesta.ok) {
            let mensaje = 'No se pudo buscar el producto.';
            const datosError = await respuesta.json().catch(() => null);
            mensaje = datosError?.errors?.buscar?.[0]
                || datosError?.message
                || mensaje;
            throw new Error(mensaje);
        }

        const datos = await respuesta.json();
        return Array.isArray(datos.productos) ? datos.productos : [];
    }

    async function buscarProductosEnModal() {
        const termino = buscarProductoModal.value.trim();

        if (termino.length < 2) {
            solicitudProductosActiva?.abort();
            productosModalActuales = [];
            mostrarMensajeProductos('⌕', 'Ingresá al menos dos caracteres para buscar.');
            return;
        }

        solicitudProductosActiva?.abort();
        solicitudProductosActiva = new AbortController();
        productosResultados.classList.add('clientes-resultados--cargando');

        try {
            const productos = await solicitarProductosManual(
                termino,
                solicitudProductosActiva.signal
            );
            dibujarProductosModal(productos);
        } catch (error) {
            if (error.name !== 'AbortError') {
                productosModalActuales = [];
                mostrarMensajeProductos('!', error.message || 'No se pudo buscar el producto.');
            }
        } finally {
            productosResultados.classList.remove('clientes-resultados--cargando');
        }
    }

    async function resolverCodigoBarras(formatoDetectado = '') {
        // Siempre se maneja como texto. No se usa Number(), parseInt() ni ninguna
        // conversión que pueda eliminar ceros iniciales.
        const codigo = String(codigoBarrasProducto.value ?? '').trim();
        const formato = String(formatoDetectado ?? '').trim().toUpperCase();

        if (codigo === '') {
            mostrarEstadoProducto('error', 'Ingresá o escaneá un código de barras.');
            codigoBarrasProducto.focus();
            return;
        }

        if (!urlProductoCodigo) {
            mostrarEstadoProducto('error', 'No se pudo preparar la búsqueda por código de barras.');
            return;
        }

        solicitudCodigoActiva?.abort();
        solicitudCodigoActiva = new AbortController();
        mostrarEstadoProducto('cargando', 'Buscando código de barras…');

        const url = new URL(urlProductoCodigo, window.location.origin);
        url.searchParams.set('codigo', codigo);
        url.searchParams.set('idsucursal', sucursal.value);

        if (formato !== '') {
            url.searchParams.set('formato', formato);
        }

        try {
            const respuesta = await fetch(url, {
                signal: solicitudCodigoActiva.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const datos = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                throw new Error(
                    datos?.errors?.codigo?.[0]
                    || datos?.message
                    || 'No se pudo buscar el código de barras.'
                );
            }

            if (datos.producto) {
                const agregado = agregarProducto(datos.producto);

                if (agregado) {
                    codigoBarrasProducto.value = '';

                    const usoEquivalencia = datos.equivalencia_aplicada === true
                        && datos.codigo_encontrado
                        && datos.codigo_encontrado !== datos.codigo_recibido;

                    const detalleEquivalencia = usoEquivalencia
                        ? ` Código leído: ${datos.codigo_recibido}; equivalente guardado: ${datos.codigo_encontrado}.`
                        : '';

                    mostrarEstadoProducto(
                        'correcto',
                        `${datos.producto.descripcion} agregado a la venta.${detalleEquivalencia}`
                    );
                }

                codigoBarrasProducto.focus();
                return;
            }

            const coincidencias = Array.isArray(datos.productos)
                ? datos.productos
                : [];

            if (datos.ambiguo && coincidencias.length > 1) {
                mostrarEstadoProducto(
                    'advertencia',
                    'Ese código coincide con más de un producto. Elegí el correcto.'
                );
                abrirModalProductos(coincidencias);
                return;
            }

            const detalleFormato = formato !== '' ? ` (${formato})` : '';
            mostrarEstadoProducto(
                'error',
                `No hay un producto activo con stock para el código ${codigo}${detalleFormato}.`
            );
            codigoBarrasProducto.select();
        } catch (error) {
            if (error.name !== 'AbortError') {
                mostrarEstadoProducto(
                    'error',
                    error.message || 'No se pudo buscar el código de barras.'
                );
            }
        }
    }


    function formatosCodigoBarrasCompatibles() {
        const formatos = window.Html5QrcodeSupportedFormats;

        if (!formatos) {
            return [];
        }

        return [
            // Formatos comerciales 1D más habituales.
            formatos.EAN_13,
            formatos.EAN_8,
            formatos.UPC_A,
            formatos.UPC_E,
            formatos.CODE_128,
            formatos.CODE_39,
            formatos.CODE_93,
            formatos.ITF,
            formatos.CODABAR,
            formatos.RSS_14,
            formatos.RSS_EXPANDED,
            formatos.UPC_EAN_EXTENSION,
            // También se aceptan formatos 2D usados en logística/GS1.
            formatos.DATA_MATRIX,
            formatos.PDF_417,
        ].filter((formato) => Number.isInteger(formato));
    }

    function obtenerEscanerCodigoBarras() {
        if (escanerCodigoBarras) {
            return escanerCodigoBarras;
        }

        if (typeof window.Html5Qrcode !== 'function') {
            return null;
        }

        const formatos = formatosCodigoBarrasCompatibles();
        const configuracion = formatos.length > 0
            ? {
                formatsToSupport: formatos,
                useBarCodeDetectorIfSupported: true,
                verbose: false,
            }
            : {
                useBarCodeDetectorIfSupported: true,
                verbose: false,
            };

        escanerCodigoBarras = new window.Html5Qrcode(
            'lector-codigo-barras',
            configuracion
        );

        return escanerCodigoBarras;
    }

    async function detenerEscanerCamara() {
        if (!escanerCodigoBarras) {
            escanerCamaraActivo = false;
            return;
        }

        if (escanerCamaraActivo) {
            try {
                await escanerCodigoBarras.stop();
            } catch (error) {
                // La cámara puede haberse cerrado antes desde el navegador.
            }
        }

        escanerCamaraActivo = false;

        try {
            escanerCodigoBarras.clear();
        } catch (error) {
            // Si todavía no se dibujó nada, no hay nada que limpiar.
        }
    }

    async function cerrarModalEscaner(devolverFoco = true) {
        await detenerEscanerCamara();
        modalEscanerProductos.hidden = true;
        actualizarBloqueoModal();
        archivoCodigoBarras.value = '';

        if (
            devolverFoco
            && elementoConFocoAntesDelEscaner instanceof HTMLElement
        ) {
            elementoConFocoAntesDelEscaner.focus();
        }
    }

    function obtenerNombreFormato(resultadoDecodificado) {
        return String(
            resultadoDecodificado?.result?.format?.formatName
            ?? ''
        ).trim().toUpperCase();
    }

    async function procesarCodigoEscaneado(codigo, formatoDetectado = '') {
        // El texto se conserva exactamente como lo entrega el lector, salvo
        // espacios externos. Esto mantiene todos los ceros iniciales.
        const codigoLimpio = String(codigo ?? '').trim();
        const formato = String(formatoDetectado ?? '').trim().toUpperCase();

        if (!codigoLimpio || procesandoCodigoEscaneado) {
            return;
        }

        procesandoCodigoEscaneado = true;
        const detalleFormato = formato !== '' ? ` · Formato: ${formato}` : '';
        mostrarEstadoEscaner(
            'correcto',
            `Código detectado: ${codigoLimpio}${detalleFormato}`
        );

        await cerrarModalEscaner(false);

        codigoBarrasProducto.value = codigoLimpio;
        mostrarEstadoProducto(
            'cargando',
            `Código ${codigoLimpio}${detalleFormato} detectado. Buscando producto…`
        );

        try {
            await resolverCodigoBarras(formato);
        } finally {
            procesandoCodigoEscaneado = false;
        }
    }

    async function iniciarCamaraEscaner() {
        const lector = obtenerEscanerCodigoBarras();

        if (!lector) {
            mostrarEstadoEscaner(
                'error',
                'No se pudo cargar el lector de códigos. Revisá la conexión a Internet y recargá la página.'
            );
            return;
        }

        if (!window.isSecureContext) {
            mostrarEstadoEscaner(
                'advertencia',
                'La cámara en vivo necesita HTTPS. Podés usar “Tomar foto” para probar desde este dispositivo.'
            );
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            mostrarEstadoEscaner(
                'error',
                'Este navegador no permite acceder a la cámara. Probá con “Tomar foto”.'
            );
            return;
        }

        mostrarEstadoEscaner('cargando', 'Solicitando permiso para usar la cámara…');

        try {
            await lector.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: (ancho, alto) => ({
                        width: Math.max(200, Math.min(Math.floor(ancho * 0.92), 520)),
                        height: Math.max(100, Math.min(Math.floor(alto * 0.38), 190)),
                    }),
                    aspectRatio: 1.7777778,
                },
                (textoDecodificado, resultadoDecodificado) => {
                    procesarCodigoEscaneado(
                        textoDecodificado,
                        obtenerNombreFormato(resultadoDecodificado)
                    );
                },
                () => {
                    // Mientras no haya coincidencia se continúa escaneando.
                }
            );

            escanerCamaraActivo = true;
            mostrarEstadoEscaner(
                'listo',
                'Cámara activa. Centrá el código de barras dentro del recuadro.'
            );
        } catch (error) {
            escanerCamaraActivo = false;
            mostrarEstadoEscaner(
                'error',
                'No se pudo abrir la cámara. Revisá el permiso del navegador o usá “Tomar foto”.'
            );
        }
    }

    async function abrirModalEscaner() {
        if (!modalClientes.hidden) {
            cerrarModalClientes();
        }

        if (!modalProductos.hidden) {
            cerrarModalProductos(false);
        }

        elementoConFocoAntesDelEscaner = document.activeElement;
        modalEscanerProductos.hidden = false;
        actualizarBloqueoModal();
        lectorCodigoBarras.replaceChildren();
        mostrarEstadoEscaner('cargando', 'Preparando cámara…');

        await iniciarCamaraEscaner();
    }

    async function escanearFotoCodigoBarras() {
        const archivo = archivoCodigoBarras.files?.[0];

        if (!archivo) {
            return;
        }

        const lector = obtenerEscanerCodigoBarras();

        if (!lector) {
            mostrarEstadoEscaner(
                'error',
                'No se pudo cargar el lector de códigos para analizar la foto.'
            );
            return;
        }

        await detenerEscanerCamara();
        mostrarEstadoEscaner('cargando', 'Analizando la foto…');

        try {
            if (typeof lector.scanFileV2 === 'function') {
                const resultado = await lector.scanFileV2(archivo, true);
                await procesarCodigoEscaneado(
                    resultado?.decodedText,
                    obtenerNombreFormato(resultado)
                );
            } else {
                // Compatibilidad por si alguna vez se carga una versión antigua
                // de la librería que todavía no exponga scanFileV2().
                const codigo = await lector.scanFile(archivo, true);
                await procesarCodigoEscaneado(codigo);
            }
        } catch (error) {
            mostrarEstadoEscaner(
                'error',
                'No pudimos leer un código de barras en esa foto. Probá acercándote y evitando reflejos.'
            );
            archivoCodigoBarras.value = '';
        }
    }

    function agregarProducto(producto) {
        const idProducto = Number(producto?.idproducto);

        if (!Number.isInteger(idProducto) || idProducto <= 0) {
            return false;
        }

        const stockTotal = Number(producto.stocktotal ?? producto.stockactual ?? 0);
        const stockLocal = Number(producto.stocklocal ?? 0);
        const existente = carrito.get(idProducto);

        if (stockTotal <= 0) {
            mostrarEstadoProducto('error', `${producto.descripcion} ya no posee stock disponible.`);
            return false;
        }

        if (existente) {
            if (existente.cantidad >= stockTotal) {
                mostrarEstadoProducto(
                    'error',
                    `No hay más stock disponible de ${producto.descripcion} entre las sucursales.`
                );
                return false;
            }

            existente.cantidad += 1;
        } else {
            carrito.set(idProducto, {
                ...producto,
                idproducto: idProducto,
                pvp: Number(producto.pvp),
                stocklocal: stockLocal,
                stocktotal: stockTotal,
                cantidad: 1,
            });
        }

        dibujarCarrito();
        return true;
    }

    function cambiarCantidad(idProducto, nuevaCantidad) {
        const producto = carrito.get(idProducto);

        if (!producto) {
            return;
        }

        const cantidad = Math.max(
            1,
            Math.min(Number(nuevaCantidad) || 1, Number(producto.stocktotal))
        );

        producto.cantidad = cantidad;
        dibujarCarrito();
    }

    function quitarProducto(idProducto) {
        carrito.delete(idProducto);
        dibujarCarrito();
    }

    function dibujarCarrito() {
        carritoLista.replaceChildren();

        let cantidadTotal = 0;
        let importeTotal = 0;
        let importeSubtotal = 0;
        let importeDescuento = 0;
        let usaStockRemoto = false;

        carrito.forEach((producto) => {
            const precioAplicado = precioUnitarioAplicado(producto);
            const importesLinea = calcularImportesProducto(
                producto,
                producto.cantidad
            );
            const subtotalOriginal = importesLinea.subtotal;
            const descuentoLinea = importesLinea.descuento;
            const totalLinea = importesLinea.total;

            cantidadTotal += producto.cantidad;
            importeSubtotal = redondearMoneda(importeSubtotal + subtotalOriginal);
            importeDescuento = redondearMoneda(importeDescuento + descuentoLinea);
            importeTotal = redondearMoneda(importeTotal + totalLinea);

            const fila = document.createElement('tr');
            fila.className = 'fila-producto-venta';

            const celdaCodigo = document.createElement('td');
            const codigo = document.createElement('span');
            codigo.className = 'tabla-carrito__codigo';
            codigo.textContent = producto.codigointerno;
            celdaCodigo.append(codigo);

            const celdaDescripcion = document.createElement('td');
            const descripcion = document.createElement('strong');
            descripcion.className = 'tabla-carrito__descripcion';
            descripcion.textContent = producto.descripcion;

            celdaDescripcion.append(descripcion);

            const stockLocal = Number(producto.stocklocal ?? 0);
            const stockTotal = Number(producto.stocktotal ?? 0);
            const faltanteLocal = Math.max(0, producto.cantidad - stockLocal);

            const celdaStock = document.createElement('td');
            const resumenStock = document.createElement('span');
            resumenStock.className = 'tabla-carrito__stock-resumen';
            resumenStock.textContent = `${stockLocal} / ${stockTotal}`;

            const origenStock = document.createElement('small');
            origenStock.className = 'tabla-carrito__stock-origen';

            if (faltanteLocal > 0) {
                usaStockRemoto = true;
                origenStock.classList.add('tabla-carrito__stock-origen--remoto');
                origenStock.textContent = `+${faltanteLocal} de otra sucursal`;
            } else {
                origenStock.textContent = 'local / total';
            }

            celdaStock.append(resumenStock, origenStock);

            const celdaPrecio = document.createElement('td');
            celdaPrecio.className = 'tabla-carrito__precio';

            const precioMostrado = document.createElement('strong');
            precioMostrado.className = 'tabla-carrito__precio-final';
            precioMostrado.textContent = formatoMoneda.format(precioAplicado);
            celdaPrecio.append(precioMostrado);

            if (descuentoClienteLocal > 0 || porcentajeDescuentoAdicionalActual() > 0) {
                const precioBase = document.createElement('small');
                precioBase.className = 'tabla-carrito__precio-base';
                precioBase.textContent = `PVP ${formatoMoneda.format(producto.pvp)}`;
                celdaPrecio.append(precioBase);

                if (descuentoClienteLocal > 0) {
                    const descuentoCliente = document.createElement('small');
                    descuentoCliente.className = 'tabla-carrito__descuento tabla-carrito__descuento--cliente';
                    descuentoCliente.textContent = `Cliente -${formatearPorcentaje(descuentoClienteLocal)}%`;
                    celdaPrecio.append(descuentoCliente);
                }

                const porcentajeAdicional = porcentajeDescuentoAdicionalActual();

                if (porcentajeAdicional > 0) {
                    const descuentoExtra = document.createElement('small');
                    descuentoExtra.className = 'tabla-carrito__descuento tabla-carrito__descuento--adicional';
                    descuentoExtra.textContent = `Adicional -${formatearPorcentaje(porcentajeAdicional)}%`;
                    celdaPrecio.append(descuentoExtra);
                }
            }

            const celdaCantidad = document.createElement('td');
            const controles = document.createElement('div');
            controles.className = 'tabla-carrito__cantidad';

            const restar = crearBotonCantidad('−', 'restar', producto.idproducto);
            restar.disabled = producto.cantidad <= 1;

            const cantidad = document.createElement('input');
            cantidad.type = 'number';
            cantidad.className = 'cantidad-producto';
            cantidad.min = '1';
            cantidad.max = String(stockTotal);
            cantidad.step = '1';
            cantidad.value = String(producto.cantidad);
            cantidad.dataset.idproducto = producto.idproducto;
            cantidad.setAttribute('aria-label', `Cantidad de ${producto.descripcion}`);

            const sumar = crearBotonCantidad('+', 'sumar', producto.idproducto);
            sumar.disabled = producto.cantidad >= stockTotal;
            controles.append(restar, cantidad, sumar);
            celdaCantidad.append(controles);

            const celdaSubtotal = document.createElement('td');
            celdaSubtotal.className = 'tabla-carrito__subtotal';
            const subtotal = document.createElement('strong');
            subtotal.textContent = formatoMoneda.format(totalLinea);

            celdaSubtotal.append(subtotal);

            if (descuentoLinea > 0) {
                const ahorro = document.createElement('small');
                ahorro.className = 'tabla-carrito__descuento';
                ahorro.textContent = `Ahorro total ${formatoMoneda.format(descuentoLinea)}`;
                celdaSubtotal.append(ahorro);
            }

            const celdaAccion = document.createElement('td');
            celdaAccion.className = 'tabla-carrito__accion';
            const quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'tabla-carrito__quitar';
            quitar.dataset.accion = 'quitar';
            quitar.dataset.idproducto = producto.idproducto;
            quitar.textContent = 'Quitar';
            quitar.setAttribute('aria-label', `Quitar ${producto.descripcion}`);
            celdaAccion.append(quitar);

            fila.append(
                celdaCodigo,
                celdaDescripcion,
                celdaStock,
                celdaPrecio,
                celdaCantidad,
                celdaSubtotal,
                celdaAccion
            );
            carritoLista.append(fila);
        });

        const cantidadProductosDistintos = carrito.size;
        const palabraProducto = cantidadProductosDistintos === 1 ? 'producto' : 'productos';
        const palabraUnidad = cantidadTotal === 1 ? 'unidad' : 'unidades';

        const baseCobro = importeTotal;
        const porcentajeRecargo = porcentajeRecargoActual();
        const recargoCobro = redondearMoneda(baseCobro * (porcentajeRecargo / 100));
        const totalCobro = redondearMoneda(baseCobro + recargoCobro);

        cantidadCarrito.textContent = `${cantidadProductosDistintos} ${palabraProducto} · ${cantidadTotal} ${palabraUnidad}`;
        basePago.textContent = formatoMoneda.format(baseCobro);
        etiquetaRecargo.textContent = `Recargo ${formatearPorcentaje(porcentajeRecargo)}%`;
        importeRecargo.textContent = formatoMoneda.format(recargoCobro);
        totalVenta.textContent = formatoMoneda.format(totalCobro);
        totalVenta.title = [
            `Subtotal ${formatoMoneda.format(importeSubtotal)}`,
            `Descuento total ${formatoMoneda.format(importeDescuento)}`,
            `Recargo ${formatoMoneda.format(recargoCobro)}`,
        ].join(' · ');
        actualizarEstadoConfirmacion();

        if (carrito.size > 0 && usaStockRemoto && !confirmandoVenta) {
            estadoConfirmacion.className = 'estado-confirmacion--correcto';
            estadoConfirmacion.textContent = 'La venta usará stock de otra sucursal. Se descontará automáticamente y quedará el aviso para esa sucursal.';
        }
    }

    function actualizarEstadoConfirmacion() {
        const clienteValido = ['consumidor_final', 'registrado']
            .includes(tipoCliente.value);
        const hayProductos = carrito.size > 0;
        const adicionalValido = descuentoAdicionalValido();
        const pagoValido = metodoPagoValido();

        descuentoAdicional.toggleAttribute('aria-invalid', !adicionalValido);
        metodoPago.toggleAttribute('aria-invalid', !pagoValido);

        botonConfirmarVenta.disabled = confirmandoVenta
            || !clienteValido
            || !hayProductos
            || !adicionalValido
            || !pagoValido;

        if (confirmandoVenta) {
            return;
        }

        estadoConfirmacion.className = '';

        if (!clienteValido) {
            estadoConfirmacion.classList.add('estado-confirmacion--error');
            estadoConfirmacion.textContent = 'Seleccioná y validá un cliente.';
        } else if (!adicionalValido) {
            estadoConfirmacion.classList.add('estado-confirmacion--error');
            estadoConfirmacion.textContent = 'El descuento adicional debe estar entre 0% y 100%.';
        } else if (!pagoValido) {
            estadoConfirmacion.classList.add('estado-confirmacion--error');
            estadoConfirmacion.textContent = 'Seleccioná un medio de pago activo.';
        } else if (!hayProductos) {
            estadoConfirmacion.textContent = 'Agregá al menos un producto para confirmar la venta.';
        } else {
            estadoConfirmacion.classList.add('estado-confirmacion--correcto');
            estadoConfirmacion.textContent = 'La venta está lista para confirmar.';
        }
    }

    function primerMensajeDeError(datos) {
        const errores = datos?.errors;

        if (errores && typeof errores === 'object') {
            for (const mensajes of Object.values(errores)) {
                if (Array.isArray(mensajes) && mensajes.length > 0) {
                    return mensajes[0];
                }
            }
        }

        return datos?.message || 'No se pudo registrar la venta.';
    }

    async function confirmarVenta() {
        actualizarEstadoConfirmacion();

        if (botonConfirmarVenta.disabled || confirmandoVenta) {
            return;
        }

        if (!csrfToken || !urlGuardar) {
            estadoConfirmacion.className = 'estado-confirmacion--error';
            estadoConfirmacion.textContent = 'No se pudo preparar el envío de la venta.';
            return;
        }

        confirmandoVenta = true;
        botonConfirmarVenta.disabled = true;
        botonConfirmarVenta.textContent = 'Guardando venta…';
        estadoConfirmacion.className = 'estado-confirmacion--procesando';
        estadoConfirmacion.textContent = 'Validando precios y stock…';

        const productos = Array.from(carrito.values()).map((producto) => ({
            idproducto: Number(producto.idproducto),
            cantidad: Number(producto.cantidad),
        }));

        try {
            const respuesta = await fetch(urlGuardar, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    idsucursal: Number(sucursal.value),
                    tipo_cliente: tipoCliente.value,
                    idcliente: idCliente.value === ''
                        ? null
                        : Number(idCliente.value),
                    descuento_adicional: porcentajeDescuentoAdicionalActual(),
                    idmetodopago: Number(metodoPago.value),
                    productos,
                }),
            });

            const datos = await respuesta.json().catch(() => ({}));

            if (!respuesta.ok) {
                throw new Error(primerMensajeDeError(datos));
            }

            estadoConfirmacion.className = 'estado-confirmacion--correcto';
            estadoConfirmacion.textContent = datos.mensaje
                || 'Venta registrada correctamente.';
            permitirSalida = true;

            window.setTimeout(() => {
                window.location.assign(datos.redirect || '/ventas');
            }, 450);
        } catch (error) {
            confirmandoVenta = false;
            botonConfirmarVenta.textContent = 'Confirmar venta';
            estadoConfirmacion.className = 'estado-confirmacion--error';
            estadoConfirmacion.textContent = error.message
                || 'No se pudo registrar la venta.';
            actualizarEstadoConfirmacion();
            estadoConfirmacion.className = 'estado-confirmacion--error';
            estadoConfirmacion.textContent = error.message
                || 'No se pudo registrar la venta.';
        }
    }

    function crearBotonCantidad(texto, accion, idProducto) {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'boton-cantidad';
        boton.dataset.accion = accion;
        boton.dataset.idproducto = idProducto;
        boton.textContent = texto;
        return boton;
    }

    codigoCliente.addEventListener('input', () => {
        codigoCliente.value = normalizarCodigoCliente(codigoCliente.value);

        if (
            codigoCliente.value === codigoClienteConfirmado
            && tipoCliente.value !== ''
        ) {
            return;
        }

        idCliente.value = '';
        tipoCliente.value = '';
        tipoClienteSeleccionadoNombre = '';
        descuentoClienteLocal = 0;
        nombreCliente.value = 'Pendiente de validar';
        dibujarCarrito();
        codigoCliente.removeAttribute('aria-invalid');
        estadoCliente.className = 'estado-cliente estado-cliente--pendiente';
        estadoCliente.textContent = 'Presioná Enter para comprobar el código.';
        actualizarEstadoConfirmacion();
    });

    codigoCliente.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter') {
            return;
        }

        evento.preventDefault();
        resolverCodigoCliente();
    });

    codigoCliente.addEventListener('blur', (evento) => {
        if (evento.relatedTarget === abrirBuscadorClientes) {
            return;
        }

        resolverCodigoCliente();
    });

    abrirBuscadorClientes.addEventListener('click', abrirModalClientes);

    buscarClienteModal.addEventListener('input', () => {
        window.clearTimeout(temporizadorClientes);
        temporizadorClientes = window.setTimeout(buscarClientesEnModal, 300);
    });

    buscarClienteModal.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            window.clearTimeout(temporizadorClientes);
            buscarClientesEnModal();
        }
    });

    clientesResultados.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.cliente-resultado');

        if (!boton) {
            return;
        }

        try {
            seleccionarCliente(JSON.parse(boton.dataset.cliente));
            cerrarModalClientes();
        } catch (error) {
            mostrarMensajeClientes('!', 'No se pudo seleccionar el cliente.');
        }
    });

    modalClientes.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-cerrar-modal-clientes]')) {
            cerrarModalClientes();
        }
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'F2') {
            evento.preventDefault();

            if (!modalEscanerProductos.hidden) {
                return;
            }

            if (modalClientes.hidden) {
                abrirModalClientes();
            }
            return;
        }

        if (evento.key === 'F3') {
            evento.preventDefault();

            if (!modalEscanerProductos.hidden) {
                return;
            }

            if (modalProductos.hidden) {
                abrirModalProductos();
            }
            return;
        }

        if (evento.key === 'Escape') {
            if (!modalEscanerProductos.hidden) {
                evento.preventDefault();
                cerrarModalEscaner();
                return;
            }

            if (!modalProductos.hidden) {
                evento.preventDefault();
                cerrarModalProductos();
                return;
            }

            if (!modalClientes.hidden) {
                evento.preventDefault();
                cerrarModalClientes();
            }
        }
    });

    abrirBuscadorProductos.addEventListener('click', () => abrirModalProductos());
    abrirEscanerProductos.addEventListener('click', abrirModalEscaner);

    modalEscanerProductos.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-cerrar-modal-escaner]')) {
            cerrarModalEscaner();
        }
    });

    usarFotoCodigoBarras.addEventListener('click', () => {
        archivoCodigoBarras.click();
    });

    archivoCodigoBarras.addEventListener('change', escanearFotoCodigoBarras);

    codigoBarrasProducto.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter') {
            return;
        }

        evento.preventDefault();
        resolverCodigoBarras();
    });

    buscarProductoModal.addEventListener('input', () => {
        window.clearTimeout(temporizadorProductos);
        temporizadorProductos = window.setTimeout(buscarProductosEnModal, 300);
    });

    buscarProductoModal.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            window.clearTimeout(temporizadorProductos);
            buscarProductosEnModal();
        }
    });

    productosResultados.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.producto-modal-resultado');

        if (!boton || boton.disabled) {
            return;
        }

        const idProducto = Number(boton.dataset.idproducto);
        const producto = productosModalActuales.find(
            (item) => Number(item.idproducto) === idProducto
        );

        if (!producto) {
            return;
        }

        if (agregarProducto(producto)) {
            mostrarEstadoProducto('correcto', `${producto.descripcion} agregado a la venta.`);
            cerrarModalProductos();
        }
    });

    modalProductos.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-cerrar-modal-productos]')) {
            cerrarModalProductos();
        }
    });

    sucursal.addEventListener('change', () => {
        const nuevaSucursal = sucursal.value;

        if (hayVentaEnCurso()) {
            const cambiar = window.confirm(
                'Cambiar de sucursal vaciará los productos de la venta. ¿Querés continuar?'
            );

            if (!cambiar) {
                sucursal.value = sucursalAnterior;
                return;
            }
        }

        sucursalAnterior = nuevaSucursal;
        carrito.clear();
        dibujarCarrito();
        codigoBarrasProducto.value = '';
        productosModalActuales = [];
        mostrarEstadoProducto('listo', 'Listo para ingresar o escanear un código de barras.');
        codigoBarrasProducto.focus();
    });

    carritoLista.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-accion]');

        if (!boton) {
            return;
        }

        const idProducto = Number(boton.dataset.idproducto);
        const producto = carrito.get(idProducto);

        if (!producto) {
            return;
        }

        if (boton.dataset.accion === 'quitar') {
            quitarProducto(idProducto);
        } else if (boton.dataset.accion === 'sumar') {
            cambiarCantidad(idProducto, producto.cantidad + 1);
        } else if (boton.dataset.accion === 'restar') {
            cambiarCantidad(idProducto, producto.cantidad - 1);
        }
    });

    carritoLista.addEventListener('change', (evento) => {
        if (!evento.target.matches('.cantidad-producto')) {
            return;
        }

        cambiarCantidad(
            Number(evento.target.dataset.idproducto),
            Number(evento.target.value)
        );
    });

    metodoPago.addEventListener('change', () => {
        dibujarCarrito();
        actualizarEstadoConfirmacion();
    });

    descuentoAdicional.addEventListener('keydown', (evento) => {
        if (['e', 'E', '+', '-'].includes(evento.key)) {
            evento.preventDefault();
        }
    });

    descuentoAdicional.addEventListener('input', () => {
        if (descuentoAdicional.value !== '') {
            const numero = Number(descuentoAdicional.value);

            if (Number.isFinite(numero)) {
                if (numero > 100) {
                    descuentoAdicional.value = '100';
                } else if (numero < 0) {
                    descuentoAdicional.value = '0';
                }
            }
        }

        dibujarCarrito();
        actualizarEstadoConfirmacion();
    });

    descuentoAdicional.addEventListener('blur', () => {
        if (descuentoAdicional.value === '') {
            descuentoAdicional.value = '0';
        }

        let numero = Number(descuentoAdicional.value);

        if (!Number.isFinite(numero)) {
            numero = 0;
        }

        descuentoAdicional.value = String(
            redondearMoneda(Math.max(0, Math.min(100, numero)))
        );

        dibujarCarrito();
        actualizarEstadoConfirmacion();
    });

    volverVentas.addEventListener('click', (evento) => {
        if (confirmandoVenta) {
            evento.preventDefault();
            estadoConfirmacion.className = 'estado-confirmacion--procesando';
            estadoConfirmacion.textContent = 'La venta se está guardando. Esperá a que termine la operación.';
            return;
        }

        if (!hayVentaEnCurso() || permitirSalida) {
            permitirSalida = true;
            return;
        }

        const salir = window.confirm(
            'Hay productos cargados en esta venta. Si salís, se perderán. ¿Querés volver a Ventas?'
        );

        if (!salir) {
            evento.preventDefault();
            return;
        }

        permitirSalida = true;
    });

    window.addEventListener('beforeunload', (evento) => {
        if (!hayVentaEnCurso() || permitirSalida) {
            return;
        }

        evento.preventDefault();
        evento.returnValue = '';
    });

    botonConfirmarVenta.addEventListener('click', confirmarVenta);

    dibujarCarrito();
    codigoBarrasProducto.focus();
})();
