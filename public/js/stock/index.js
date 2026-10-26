(() => {
    'use strict';

    const aplicacion = document.getElementById('aplicacion-stock');
    const formularioFiltros = document.getElementById('formulario-filtros-stock');
    const buscar = document.getElementById('buscar');
    const sucursalFiltro = document.getElementById('idsucursal');
    const disponibilidad = document.getElementById('disponibilidad');

    let temporizadorBusqueda;
    let solicitudFiltros;
    let solicitudUbicacion;

    async function actualizarResultados() {
        if (!formularioFiltros) {
            return;
        }

        const url = new URL(formularioFiltros.action, window.location.origin);
        const termino = buscar?.value.trim() ?? '';

        if (termino !== '') {
            url.searchParams.set('buscar', termino);
        }

        if (sucursalFiltro?.value) {
            url.searchParams.set('idsucursal', sucursalFiltro.value);
        }

        if (disponibilidad?.value && disponibilidad.value !== 'todos') {
            url.searchParams.set('disponibilidad', disponibilidad.value);
        }

        solicitudFiltros?.abort();
        solicitudFiltros = new AbortController();

        const panelStockActual = document.getElementById('panel-resultados-stock');
        const panelRecepcionActual = document.getElementById('panel-recepcion-compras');
        const pestanaActiva = document
            .querySelector('[data-pestana-recepcion].pestana-recepcion--activa')
            ?.dataset.pestanaRecepcion || 'pendientes';

        [panelStockActual, panelRecepcionActual].forEach((panel) => {
            panel?.classList.add('panel--cargando');
            panel?.setAttribute('aria-busy', 'true');
        });

        try {
            const respuesta = await fetch(url, {
                signal: solicitudFiltros.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!respuesta.ok) {
                throw new Error('No se pudieron actualizar los resultados.');
            }

            const documento = new DOMParser().parseFromString(
                await respuesta.text(),
                'text/html'
            );
            const panelStockNuevo = documento.getElementById('panel-resultados-stock');
            const panelRecepcionNuevo = documento.getElementById('panel-recepcion-compras');

            if (!panelStockNuevo || !panelRecepcionNuevo) {
                throw new Error('La respuesta no contiene todos los paneles de stock.');
            }

            document.getElementById('panel-resultados-stock')?.replaceWith(panelStockNuevo);
            document.getElementById('panel-recepcion-compras')?.replaceWith(panelRecepcionNuevo);

            // El panel se reemplaza por AJAX. Restauramos la pestaña que el usuario
            // estaba consultando para que el filtro no lo devuelva siempre a Pendientes.
            activarPestanaRecepcion(pestanaActiva);

            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') {
                [panelStockActual, panelRecepcionActual].forEach((panel) => {
                    panel?.classList.remove('panel--cargando');
                    panel?.removeAttribute('aria-busy');
                });
            }
        }
    }

    formularioFiltros?.addEventListener('submit', (evento) => {
        evento.preventDefault();
        actualizarResultados();
    });

    buscar?.addEventListener('input', () => {
        window.clearTimeout(temporizadorBusqueda);
        temporizadorBusqueda = window.setTimeout(actualizarResultados, 300);
    });

    sucursalFiltro?.addEventListener('change', actualizarResultados);
    disponibilidad?.addEventListener('change', actualizarResultados);

    const modalIngreso = document.getElementById('modal-ingreso-stock');
    const formularioIngreso = document.getElementById('formulario-ingreso-stock');
    const ingresoProducto = document.getElementById('modal-producto');
    const ingresoCodigo = document.getElementById('modal-ubicacion');
    const ingresoSucursal = document.getElementById('idsucursal_destino_ingreso');
    const ingresoStockActual = document.getElementById('modal-stock-actual');
    const cantidadIngreso = document.getElementById('cantidad_ingreso');
    const stockMinimo = document.getElementById('stockminimo');
    const stockMaximo = document.getElementById('stockmaximo');
    const errorIngreso = document.getElementById('error-limites-stock');

    const modalTransferencia = document.getElementById('modal-transferencia-stock');
    const formularioTransferencia = document.getElementById('formulario-transferencia-stock');
    const transferenciaProducto = document.getElementById('transferencia-producto');
    const transferenciaCodigo = document.getElementById('transferencia-codigo');
    const transferenciaOrigen = document.getElementById('transferencia-origen');
    const transferenciaDisponible = document.getElementById('transferencia-disponible');
    const transferenciaSucursal = document.getElementById('idsucursal_destino_transferencia');
    const transferenciaStockDestino = document.getElementById('transferencia-stock-destino');
    const cantidadTransferencia = document.getElementById('cantidad_transferencia');
    const minimoDestino = document.getElementById('stockminimo_destino');
    const maximoDestino = document.getElementById('stockmaximo_destino');
    const errorTransferencia = document.getElementById('error-transferencia-stock');

    const modalRecepcionCompra = document.getElementById('modal-recepcion-compra');
    const formularioRecepcionCompra = document.getElementById('formulario-recepcion-compra');
    const recepcionProducto = document.getElementById('recepcion-producto');
    const recepcionCodigo = document.getElementById('recepcion-codigo');
    const recepcionCompra = document.getElementById('recepcion-compra');
    const recepcionProveedor = document.getElementById('recepcion-proveedor');
    const recepcionSucursal = document.getElementById('recepcion-sucursal');
    const recepcionStock = document.getElementById('recepcion-stock');
    const recepcionPedido = document.getElementById('recepcion-pedido');
    const recepcionRecibido = document.getElementById('recepcion-recibido');
    const recepcionProblemaPrevio = document.getElementById('recepcion-problema-previo');
    const recepcionPendiente = document.getElementById('recepcion-pendiente');
    const cantidadLlegada = document.getElementById('cantidad_llegada');
    const cantidadBuena = document.getElementById('cantidad_buena');
    const cantidadProblema = document.getElementById('cantidad_problema');
    const bloqueObservacionRecepcion = document.getElementById('bloque-observacion-recepcion');
    const observacionRecepcion = document.getElementById('observacion_recepcion');
    const minimoRecepcion = document.getElementById('stockminimo_recepcion');
    const maximoRecepcion = document.getElementById('stockmaximo_recepcion');
    const errorRecepcionCompra = document.getElementById('error-recepcion-compra');

    const modalReemplazo = document.getElementById('modal-reemplazo-compra');
    const formularioReemplazo = document.getElementById('formulario-reemplazo-compra');
    const reemplazoProducto = document.getElementById('reemplazo-producto');
    const reemplazoCodigo = document.getElementById('reemplazo-codigo');
    const reemplazoCompraProveedor = document.getElementById('reemplazo-compra-proveedor');
    const reemplazoPendiente = document.getElementById('reemplazo-pendiente');
    const reemplazoStock = document.getElementById('reemplazo-stock');
    const cantidadReemplazo = document.getElementById('cantidad_reemplazo');
    const observacionReemplazo = document.getElementById('observacion_reemplazo');
    const errorReemplazo = document.getElementById('error-reemplazo-compra');

    let botonQueAbrioModal = null;
    let idProductoIngreso = null;
    let idProductoTransferencia = null;
    let idSucursalOrigen = null;

    function construirUrlUbicacion(idProducto, idSucursal) {
        const plantilla = aplicacion?.dataset.urlUbicacion ?? '';

        return plantilla
            .replace('__PRODUCTO__', encodeURIComponent(String(idProducto)))
            .replace('__SUCURSAL__', encodeURIComponent(String(idSucursal)));
    }

    async function consultarUbicacion(idProducto, idSucursal) {
        solicitudUbicacion?.abort();
        solicitudUbicacion = new AbortController();

        const respuesta = await fetch(
            construirUrlUbicacion(idProducto, idSucursal),
            {
                signal: solicitudUbicacion.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        if (!respuesta.ok) {
            throw new Error('No se pudo consultar el stock de la sucursal.');
        }

        return respuesta.json();
    }

    function mostrarError(elemento, mensaje) {
        if (!elemento) {
            return;
        }

        elemento.textContent = mensaje;
        elemento.hidden = false;
    }

    function ocultarError(elemento) {
        if (!elemento) {
            return;
        }

        elemento.hidden = true;
        elemento.textContent = '';
    }

    function actualizarBloqueoPagina() {
        const hayModalAbierto = Boolean(
            (modalIngreso && !modalIngreso.hidden)
            || (modalTransferencia && !modalTransferencia.hidden)
            || (modalRecepcionCompra && !modalRecepcionCompra.hidden)
            || (modalReemplazo && !modalReemplazo.hidden)
        );

        document.body.classList.toggle('modal-abierto', hayModalAbierto);
    }

    function cerrarIngreso() {
        if (!modalIngreso) {
            return;
        }

        solicitudUbicacion?.abort();
        modalIngreso.hidden = true;
        actualizarBloqueoPagina();
        botonQueAbrioModal?.focus();
    }

    function cerrarTransferencia() {
        if (!modalTransferencia) {
            return;
        }

        solicitudUbicacion?.abort();
        modalTransferencia.hidden = true;
        actualizarBloqueoPagina();
        botonQueAbrioModal?.focus();
    }

    function abrirIngreso(boton) {
        if (!modalIngreso || !formularioIngreso || !ingresoSucursal) {
            return;
        }

        botonQueAbrioModal = boton;
        idProductoIngreso = Number.parseInt(boton.dataset.idproducto || '', 10);
        formularioIngreso.action = boton.dataset.action ?? '';
        ingresoProducto.textContent = boton.dataset.producto || 'Producto';
        ingresoCodigo.textContent = boton.dataset.codigo || 'Sin código interno';
        ingresoSucursal.value = boton.dataset.idsucursal || '';
        Array.from(ingresoSucursal.options).forEach((opcion) => {
            opcion.disabled = opcion.value !== ingresoSucursal.value;
        });
        ingresoStockActual.textContent = `${Number.parseInt(boton.dataset.actual || '0', 10).toLocaleString('es-AR')} unidades`;
        cantidadIngreso.value = '';
        stockMinimo.value = boton.dataset.minimo || '0';
        stockMaximo.value = boton.dataset.maximo || '';
        ocultarError(errorIngreso);

        modalIngreso.hidden = false;
        actualizarBloqueoPagina();
        window.setTimeout(() => cantidadIngreso?.focus(), 0);
    }

    async function cambiarSucursalIngreso() {
        ocultarError(errorIngreso);

        if (!ingresoSucursal?.value || !idProductoIngreso) {
            ingresoStockActual.textContent = 'Seleccioná una sucursal';
            stockMinimo.value = '0';
            stockMaximo.value = '';
            return;
        }

        ingresoStockActual.textContent = 'Consultando...';

        try {
            const datos = await consultarUbicacion(
                idProductoIngreso,
                ingresoSucursal.value
            );
            ingresoStockActual.textContent = `${Number(datos.stockactual).toLocaleString('es-AR')} unidades`;
            stockMinimo.value = String(datos.stockminimo ?? 0);
            stockMaximo.value = datos.stockmaximo ?? '';
        } catch (error) {
            if (error.name !== 'AbortError') {
                ingresoStockActual.textContent = 'No disponible';
                mostrarError(errorIngreso, error.message);
            }
        }
    }

    function abrirTransferencia(boton) {
        if (!modalTransferencia || !formularioTransferencia || !transferenciaSucursal) {
            return;
        }

        botonQueAbrioModal = boton;
        idProductoTransferencia = Number.parseInt(boton.dataset.idproducto || '', 10);
        idSucursalOrigen = String(boton.dataset.idsucursal || '');
        const disponible = Number.parseInt(boton.dataset.actual || '0', 10);

        formularioTransferencia.action = boton.dataset.action ?? '';
        transferenciaProducto.textContent = boton.dataset.producto || 'Producto';
        transferenciaCodigo.textContent = boton.dataset.codigo || 'Sin código interno';
        transferenciaOrigen.textContent = boton.dataset.sucursal || 'Sucursal de origen';
        transferenciaDisponible.textContent = `${disponible.toLocaleString('es-AR')} disponibles`;

        transferenciaSucursal.value = '';
        Array.from(transferenciaSucursal.options).forEach((opcion) => {
            opcion.disabled = opcion.value !== '' && opcion.value === idSucursalOrigen;
        });

        cantidadTransferencia.value = '';
        cantidadTransferencia.max = String(disponible);
        transferenciaStockDestino.textContent = 'Seleccioná una sucursal';
        minimoDestino.value = '';
        maximoDestino.value = '';
        minimoDestino.disabled = true;
        maximoDestino.disabled = true;
        ocultarError(errorTransferencia);

        modalTransferencia.hidden = false;
        actualizarBloqueoPagina();
        window.setTimeout(() => transferenciaSucursal.focus(), 0);
    }

    async function cambiarSucursalTransferencia() {
        ocultarError(errorTransferencia);
        minimoDestino.disabled = true;
        maximoDestino.disabled = true;

        if (!transferenciaSucursal?.value || !idProductoTransferencia) {
            transferenciaStockDestino.textContent = 'Seleccioná una sucursal';
            minimoDestino.value = '';
            maximoDestino.value = '';
            return;
        }

        if (transferenciaSucursal.value === idSucursalOrigen) {
            transferenciaStockDestino.textContent = 'Destino inválido';
            mostrarError(
                errorTransferencia,
                'La sucursal de destino debe ser diferente a la de origen.'
            );
            return;
        }

        transferenciaStockDestino.textContent = 'Consultando...';

        try {
            const datos = await consultarUbicacion(
                idProductoTransferencia,
                transferenciaSucursal.value
            );
            transferenciaStockDestino.textContent = `${Number(datos.stockactual).toLocaleString('es-AR')} unidades`;
            minimoDestino.value = String(datos.stockminimo ?? 0);
            maximoDestino.value = datos.stockmaximo ?? '';
            minimoDestino.disabled = false;
            maximoDestino.disabled = false;
        } catch (error) {
            if (error.name !== 'AbortError') {
                transferenciaStockDestino.textContent = 'No disponible';
                mostrarError(errorTransferencia, error.message);
            }
        }
    }

    function activarPestanaRecepcion(nombre) {
        document.querySelectorAll('[data-pestana-recepcion]').forEach((boton) => {
            const activa = boton.dataset.pestanaRecepcion === nombre;
            boton.classList.toggle('pestana-recepcion--activa', activa);
            boton.setAttribute('aria-selected', activa ? 'true' : 'false');
        });
        document.querySelectorAll('[data-contenido-recepcion]').forEach((panel) => {
            panel.hidden = panel.dataset.contenidoRecepcion !== nombre;
        });
    }

    function cerrarRecepcionCompra() {
        if (!modalRecepcionCompra) return;
        modalRecepcionCompra.hidden = true;
        actualizarBloqueoPagina();
        botonQueAbrioModal?.focus();
    }

    function abrirRecepcionCompra(boton) {
        if (!modalRecepcionCompra || !formularioRecepcionCompra) return;
        botonQueAbrioModal = boton;
        const pendiente = Number.parseInt(boton.dataset.pendiente || '0', 10);
        formularioRecepcionCompra.action = boton.dataset.action || '';
        recepcionProducto.textContent = boton.dataset.producto || 'Producto';
        recepcionCodigo.textContent = boton.dataset.codigo || 'Sin código MES';
        recepcionCompra.textContent = boton.dataset.compra || '—';
        recepcionProveedor.textContent = boton.dataset.proveedor || '—';
        recepcionSucursal.textContent = boton.dataset.sucursal || '—';
        recepcionStock.textContent = Number(boton.dataset.stock || 0).toLocaleString('es-AR');
        recepcionPedido.textContent = Number(boton.dataset.pedido || 0).toLocaleString('es-AR');
        recepcionRecibido.textContent = Number(boton.dataset.recibido || 0).toLocaleString('es-AR');
        recepcionProblemaPrevio.textContent = Number(boton.dataset.problema || 0).toLocaleString('es-AR');
        recepcionPendiente.textContent = pendiente.toLocaleString('es-AR');
        cantidadLlegada.max = String(pendiente);
        cantidadBuena.max = String(pendiente);
        cantidadProblema.max = String(pendiente);
        cantidadLlegada.value = String(pendiente);
        cantidadBuena.value = String(pendiente);
        cantidadProblema.value = '0';
        minimoRecepcion.value = boton.dataset.minimo || '0';
        maximoRecepcion.value = boton.dataset.maximo || '';
        observacionRecepcion.value = '';
        bloqueObservacionRecepcion.hidden = true;
        ocultarError(errorRecepcionCompra);
        modalRecepcionCompra.hidden = false;
        actualizarBloqueoPagina();
        window.setTimeout(() => cantidadLlegada?.focus(), 0);
    }

    function cerrarReemplazo() {
        if (!modalReemplazo) return;
        modalReemplazo.hidden = true;
        actualizarBloqueoPagina();
        botonQueAbrioModal?.focus();
    }

    function abrirReemplazo(boton) {
        if (!modalReemplazo || !formularioReemplazo) return;
        botonQueAbrioModal = boton;
        const pendiente = Number.parseInt(boton.dataset.problema || '0', 10);
        formularioReemplazo.action = boton.dataset.action || '';
        reemplazoProducto.textContent = boton.dataset.producto || 'Producto';
        reemplazoCodigo.textContent = boton.dataset.codigo || 'Sin código MES';
        reemplazoCompraProveedor.textContent = `${boton.dataset.compra || '—'} · ${boton.dataset.proveedor || 'Proveedor'}`;
        reemplazoPendiente.textContent = pendiente.toLocaleString('es-AR');
        reemplazoStock.textContent = Number(boton.dataset.stock || 0).toLocaleString('es-AR');
        cantidadReemplazo.max = String(pendiente);
        cantidadReemplazo.value = String(pendiente);
        observacionReemplazo.value = '';
        ocultarError(errorReemplazo);
        modalReemplazo.hidden = false;
        actualizarBloqueoPagina();
        window.setTimeout(() => cantidadReemplazo?.focus(), 0);
    }

    function sincronizarCantidadesRecepcion(origen) {
        const llegada = Number.parseInt(cantidadLlegada?.value || '0', 10);
        let buenas = Number.parseInt(cantidadBuena?.value || '0', 10);
        let problemas = Number.parseInt(cantidadProblema?.value || '0', 10);
        if (origen === 'llegada') {
            problemas = Math.min(problemas, Math.max(llegada, 0));
            buenas = Math.max(llegada - problemas, 0);
            cantidadProblema.value = String(problemas);
            cantidadBuena.value = String(buenas);
        } else if (origen === 'problema') {
            problemas = Math.min(Math.max(problemas, 0), Math.max(llegada, 0));
            cantidadProblema.value = String(problemas);
            cantidadBuena.value = String(Math.max(llegada - problemas, 0));
        } else if (origen === 'buena') {
            buenas = Math.min(Math.max(buenas, 0), Math.max(llegada, 0));
            cantidadBuena.value = String(buenas);
            cantidadProblema.value = String(Math.max(llegada - buenas, 0));
        }
        const problemasFinal = Number.parseInt(cantidadProblema?.value || '0', 10);
        bloqueObservacionRecepcion.hidden = problemasFinal <= 0;
        if (problemasFinal <= 0) observacionRecepcion.value = '';
        ocultarError(errorRecepcionCompra);
    }

    function soloDigitos(campo, error) {
        campo?.addEventListener('input', () => {
            campo.value = campo.value.replace(/[^0-9]/g, '');
            ocultarError(error);
        });
    }

    [cantidadIngreso, stockMinimo, stockMaximo].forEach((campo) => {
        soloDigitos(campo, errorIngreso);
    });

    [cantidadTransferencia, minimoDestino, maximoDestino].forEach((campo) => {
        soloDigitos(campo, errorTransferencia);
    });

    [cantidadLlegada, cantidadBuena, cantidadProblema, minimoRecepcion, maximoRecepcion].forEach((campo) => soloDigitos(campo, errorRecepcionCompra));
    [cantidadReemplazo].forEach((campo) => soloDigitos(campo, errorReemplazo));
    cantidadLlegada?.addEventListener('input', () => sincronizarCantidadesRecepcion('llegada'));
    cantidadBuena?.addEventListener('input', () => sincronizarCantidadesRecepcion('buena'));
    cantidadProblema?.addEventListener('input', () => sincronizarCantidadesRecepcion('problema'));

    ingresoSucursal?.addEventListener('change', cambiarSucursalIngreso);
    transferenciaSucursal?.addEventListener('change', cambiarSucursalTransferencia);

    formularioIngreso?.addEventListener('submit', (evento) => {
        ocultarError(errorIngreso);

        const minimo = Number.parseInt(stockMinimo?.value || '0', 10);
        const maximoTexto = stockMaximo?.value ?? '';
        const cantidad = Number.parseInt(cantidadIngreso?.value || '0', 10);

        if (!ingresoSucursal?.value) {
            evento.preventDefault();
            mostrarError(errorIngreso, 'Seleccioná la sucursal donde quedará el stock.');
            ingresoSucursal?.focus();
        } else if (cantidad < 1) {
            evento.preventDefault();
            mostrarError(errorIngreso, 'La cantidad recibida debe ser mayor que cero.');
            cantidadIngreso?.focus();
        } else if (maximoTexto !== '' && Number.parseInt(maximoTexto, 10) < minimo) {
            evento.preventDefault();
            mostrarError(errorIngreso, 'El stock máximo debe ser igual o mayor que el mínimo.');
            stockMaximo?.focus();
        }
    });

    formularioTransferencia?.addEventListener('submit', (evento) => {
        ocultarError(errorTransferencia);

        const cantidad = Number.parseInt(cantidadTransferencia?.value || '0', 10);
        const disponible = Number.parseInt(cantidadTransferencia?.max || '0', 10);
        const minimo = Number.parseInt(minimoDestino?.value || '0', 10);
        const maximoTexto = maximoDestino?.value ?? '';

        if (!transferenciaSucursal?.value) {
            evento.preventDefault();
            mostrarError(errorTransferencia, 'Seleccioná la sucursal de destino.');
            transferenciaSucursal?.focus();
        } else if (transferenciaSucursal.value === idSucursalOrigen) {
            evento.preventDefault();
            mostrarError(errorTransferencia, 'El destino debe ser diferente al origen.');
            transferenciaSucursal.focus();
        } else if (minimoDestino?.disabled) {
            evento.preventDefault();
            mostrarError(errorTransferencia, 'Esperá a que se consulte el stock del destino.');
        } else if (cantidad < 1 || cantidad > disponible) {
            evento.preventDefault();
            mostrarError(
                errorTransferencia,
                `La cantidad debe estar entre 1 y ${disponible.toLocaleString('es-AR')}.`
            );
            cantidadTransferencia?.focus();
        } else if (maximoTexto !== '' && Number.parseInt(maximoTexto, 10) < minimo) {
            evento.preventDefault();
            mostrarError(errorTransferencia, 'El máximo del destino debe ser igual o mayor que el mínimo.');
            maximoDestino?.focus();
        }
    });

    formularioRecepcionCompra?.addEventListener('submit', (evento) => {
        ocultarError(errorRecepcionCompra);
        const llegada = Number.parseInt(cantidadLlegada?.value || '0', 10);
        const buenas = Number.parseInt(cantidadBuena?.value || '0', 10);
        const problemas = Number.parseInt(cantidadProblema?.value || '0', 10);
        const maximo = Number.parseInt(cantidadLlegada?.max || '0', 10);
        const minimoStock = Number.parseInt(minimoRecepcion?.value || '0', 10);
        const maximoTexto = maximoRecepcion?.value ?? '';
        let mensaje = ''; let campo = null;
        if (llegada < 1 || llegada > maximo) { mensaje = `La cantidad que llegó debe estar entre 1 y ${maximo.toLocaleString('es-AR')}.`; campo = cantidadLlegada; }
        else if ((buenas + problemas) !== llegada) { mensaje = 'Las unidades buenas y con problemas deben sumar la cantidad que llegó.'; campo = cantidadBuena; }
        else if (problemas > 0 && observacionRecepcion?.value.trim() === '') { mensaje = 'Escribí una observación explicando el problema.'; campo = observacionRecepcion; }
        else if (maximoTexto !== '' && Number.parseInt(maximoTexto, 10) < minimoStock) { mensaje = 'El stock máximo debe ser igual o mayor que el mínimo.'; campo = maximoRecepcion; }
        if (mensaje) { evento.preventDefault(); mostrarError(errorRecepcionCompra, mensaje); campo?.focus(); }
    });

    formularioReemplazo?.addEventListener('submit', (evento) => {
        ocultarError(errorReemplazo);
        const cantidad = Number.parseInt(cantidadReemplazo?.value || '0', 10);
        const maximo = Number.parseInt(cantidadReemplazo?.max || '0', 10);
        if (cantidad < 1 || cantidad > maximo) {
            evento.preventDefault();
            mostrarError(errorReemplazo, `La cantidad reemplazada debe estar entre 1 y ${maximo.toLocaleString('es-AR')}.`);
            cantidadReemplazo?.focus();
        }
    });

    document.addEventListener('click', (evento) => {
        const pestanaRecepcion = evento.target.closest('[data-pestana-recepcion]');
        const botonIngreso = evento.target.closest('.js-abrir-ingreso');
        const botonTransferencia = evento.target.closest('.js-abrir-transferencia');
        const botonRecepcionCompra = evento.target.closest('.js-abrir-recepcion-compra');
        const botonReemplazo = evento.target.closest('.js-abrir-reemplazo');

        if (pestanaRecepcion) {
            activarPestanaRecepcion(
                pestanaRecepcion.dataset.pestanaRecepcion || 'pendientes'
            );
            return;
        }

        if (botonIngreso) {
            abrirIngreso(botonIngreso);
            return;
        }

        if (botonTransferencia && !botonTransferencia.disabled) { abrirTransferencia(botonTransferencia); return; }
        if (botonRecepcionCompra) { abrirRecepcionCompra(botonRecepcionCompra); return; }
        if (botonReemplazo) { abrirReemplazo(botonReemplazo); return; }
        if (evento.target.closest('.js-cerrar-recepcion-compra')) { cerrarRecepcionCompra(); return; }
        if (evento.target.closest('.js-cerrar-reemplazo')) { cerrarReemplazo(); return; }

        if (evento.target.closest('.js-cerrar-ingreso')) {
            cerrarIngreso();
        }

        if (evento.target.closest('.js-cerrar-transferencia')) {
            cerrarTransferencia();
        }
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Escape') {
            return;
        }

        if (modalIngreso && !modalIngreso.hidden) {
            cerrarIngreso();
        }

        if (modalTransferencia && !modalTransferencia.hidden) cerrarTransferencia();
        if (modalRecepcionCompra && !modalRecepcionCompra.hidden) cerrarRecepcionCompra();
        if (modalReemplazo && !modalReemplazo.hidden) cerrarReemplazo();
    });
})();
