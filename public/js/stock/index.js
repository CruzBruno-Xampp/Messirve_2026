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

        const panelActual = document.getElementById('panel-resultados-stock');
        panelActual?.classList.add('panel--cargando');
        panelActual?.setAttribute('aria-busy', 'true');

        try {
            const respuesta = await fetch(url, {
                signal: solicitudFiltros.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!respuesta.ok) {
                throw new Error('No se pudo actualizar el listado.');
            }

            const documento = new DOMParser().parseFromString(
                await respuesta.text(),
                'text/html'
            );
            const panelNuevo = documento.getElementById('panel-resultados-stock');

            if (!panelNuevo) {
                throw new Error('La respuesta no contiene el listado de stock.');
            }

            document.getElementById('panel-resultados-stock')?.replaceWith(panelNuevo);
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') {
                panelActual?.classList.remove('panel--cargando');
                panelActual?.removeAttribute('aria-busy');
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

    document.addEventListener('click', (evento) => {
        const botonIngreso = evento.target.closest('.js-abrir-ingreso');
        const botonTransferencia = evento.target.closest('.js-abrir-transferencia');

        if (botonIngreso) {
            abrirIngreso(botonIngreso);
            return;
        }

        if (botonTransferencia && !botonTransferencia.disabled) {
            abrirTransferencia(botonTransferencia);
            return;
        }

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

        if (modalTransferencia && !modalTransferencia.hidden) {
            cerrarTransferencia();
        }
    });
})();
