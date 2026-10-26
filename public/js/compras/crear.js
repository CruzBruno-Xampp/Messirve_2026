(() => {
    'use strict';

    const aplicacion = document.getElementById('aplicacion-nueva-compra');
    if (!aplicacion) return;

    const formulario = document.getElementById('formulario-busqueda-compra');
    const inputBuscar = document.getElementById('buscar-articulo');
    const botonBuscar = document.getElementById('boton-buscar-articulo');
    const resultados = document.getElementById('resultados-compra');
    const sucursal = document.getElementById('sucursal-destino');

    const pedidoVacio = document.getElementById('pedido-vacio');
    const contenedorTabla = document.getElementById('contenedor-tabla-pedido');
    const listaPedido = document.getElementById('lista-pedido');
    const contadorPedido = document.getElementById('contador-pedido');
    const cantidadProveedores = document.getElementById('cantidad-proveedores');
    const totalPedido = document.getElementById('total-pedido');
    const botonConfirmarPedido = document.getElementById('boton-confirmar-pedido');
    const notaProximoPaso = document.getElementById('nota-proximo-paso');
    const panelConfirmacion = document.getElementById('confirmacion-compra');
    const tituloConfirmacion = document.getElementById('confirmacion-compra-titulo');
    const listaConfirmacion = document.getElementById('confirmacion-compra-lista');
    const bloqueProductosMes = document.getElementById('confirmacion-productos-mes');
    const textoProductosMes = document.getElementById('confirmacion-productos-mes-texto');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const modalMes = document.getElementById('modal-producto-mes');
    const formularioMes = document.getElementById('formulario-producto-mes');
    const errorModalMes = document.getElementById('error-modal-mes');

    const mesOrigenDescripcion = document.getElementById('mes-origen-descripcion');
    const mesOrigenProveedor = document.getElementById('mes-origen-proveedor');
    const mesOrigenBarra = document.getElementById('mes-origen-barra');
    const mesOrigenClasificacion = document.getElementById('mes-origen-clasificacion');

    const mesDescripcion = document.getElementById('mes-descripcion');
    const mesCodigoFabricante = document.getElementById('mes-codigo-fabricante');
    const mesCodigoBarra = document.getElementById('mes-codigo-barra');
    const mesMarca = document.getElementById('mes-marca');
    const mesNuevaMarca = document.getElementById('mes-nueva-marca');
    const mesLinea = document.getElementById('mes-linea');
    const mesNuevaLinea = document.getElementById('mes-nueva-linea');
    const mesGrupo = document.getElementById('mes-grupo');
    const mesNuevoGrupo = document.getElementById('mes-nuevo-grupo');
    const mesSubgrupo = document.getElementById('mes-subgrupo');
    const mesNuevoSubgrupo = document.getElementById('mes-nuevo-subgrupo');
    const mesUnidad = document.getElementById('mes-unidad');
    const mesNuevaUnidad = document.getElementById('mes-nueva-unidad');
    const mesNuevaUnidadAbreviatura = document.getElementById('mes-nueva-unidad-abreviatura');
    const mesIva = document.getElementById('mes-iva');
    const mesContenido = document.getElementById('mes-contenido');
    const mesColor = document.getElementById('mes-color');
    const mesPvp = document.getElementById('mes-pvp');
    const mesRentabilidad = document.getElementById('mes-rentabilidad');
    const mesRecalcularPvp = document.getElementById('mes-recalcular-pvp');
    const mesResumenLista = document.getElementById('mes-resumen-lista');
    const mesResumenCosto = document.getElementById('mes-resumen-costo');
    const mesResumenCostoEtiqueta = document.getElementById('mes-resumen-costo-etiqueta');
    const mesResumenIva = document.getElementById('mes-resumen-iva');
    const mesResumenPvp = document.getElementById('mes-resumen-pvp');
    const mesFormulaPrecio = document.getElementById('mes-formula-precio');

    const pedido = new Map();
    let resultadosActuales = [];
    let controladorBusqueda = null;
    let temporizadorBusqueda = null;
    let preparacionMesActual = null;
    let datosPreparacionActual = null;
    let pvpEditadoManualmente = false;
    let confirmandoPedido = false;
    let pedidoConfirmado = false;
    let errorConfirmacion = '';

    const moneda = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function escaparHtml(valor) {
        return String(valor ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function textoOrigen(origen) {
        if (origen === 'informado') return 'Precio c/desc';
        if (origen === 'calculado') return 'Calculado';
        return 'Precio lista';
    }

    function normalizarDecimal(valor) {
        const texto = String(valor ?? '').trim().replace(',', '.');
        if (texto === '') return null;
        const numero = Number(texto);
        return Number.isFinite(numero) ? numero : null;
    }

    function construirUrlPreparar(idDetalleCatalogo) {
        return String(aplicacion.dataset.urlPreparar || '')
            .replace('__DETALLE__', encodeURIComponent(String(idDetalleCatalogo)));
    }

    function opcion(valor, texto, seleccionado = false) {
        return `<option value="${escaparHtml(valor)}" ${seleccionado ? 'selected' : ''}>${escaparHtml(texto)}</option>`;
    }

    function llenarSelectSimple(select, registros, idCampo, textoCampo, seleccionado, textoInicial, permitirNuevo = true) {
        let html = opcion('', textoInicial, !seleccionado);

        for (const registro of registros ?? []) {
            html += opcion(
                registro[idCampo],
                typeof textoCampo === 'function' ? textoCampo(registro) : registro[textoCampo],
                String(registro[idCampo]) === String(seleccionado ?? '')
            );
        }

        if (permitirNuevo) {
            html += opcion('nuevo', '+ Crear nuevo', seleccionado === 'nuevo');
        }

        select.innerHTML = html;
    }

    function valorSugerido(id, nombre) {
        if (id !== null && id !== undefined && id !== '') return String(id);
        if (String(nombre ?? '').trim() !== '') return 'nuevo';
        return '';
    }

    function actualizarBloquesNuevos() {
        document.getElementById('bloque-nueva-marca').hidden = mesMarca.value !== 'nuevo';
        document.getElementById('bloque-nueva-linea').hidden = mesLinea.value !== 'nuevo';
        document.getElementById('bloque-nuevo-grupo').hidden = mesGrupo.value !== 'nuevo';
        document.getElementById('bloque-nuevo-subgrupo').hidden = mesSubgrupo.value !== 'nuevo';
        document.getElementById('bloque-nueva-unidad').hidden = mesUnidad.value !== 'nuevo';
    }

    function reconstruirGrupos(valorPreferido = '') {
        const datos = datosPreparacionActual;
        if (!datos) return;

        const linea = mesLinea.value;
        const grupos = (datos.opciones?.grupos ?? []).filter((registro) => {
            if (linea === 'nuevo' || linea === '') return false;
            return String(registro.idlinea) === String(linea);
        });

        const sugerido = valorPreferido || (
            String(datos.sugerencias?.idlinea ?? '') === String(linea)
                ? valorSugerido(datos.sugerencias?.idgrupo, datos.sugerencias?.grupo_nombre)
                : ''
        );

        llenarSelectSimple(mesGrupo, grupos, 'idgrupo', 'nombre', sugerido, 'Seleccionar grupo', true);
        if (linea === 'nuevo') mesGrupo.value = 'nuevo';
        reconstruirSubgrupos();
        actualizarBloquesNuevos();
    }

    function reconstruirSubgrupos(valorPreferido = '') {
        const datos = datosPreparacionActual;
        if (!datos) return;

        const grupo = mesGrupo.value;
        const subgrupos = (datos.opciones?.subgrupos ?? []).filter((registro) => {
            if (grupo === 'nuevo' || grupo === '') return false;
            return String(registro.idgrupo) === String(grupo);
        });

        const sugerido = valorPreferido || (
            String(datos.sugerencias?.idgrupo ?? '') === String(grupo)
                ? valorSugerido(datos.sugerencias?.idsubgrupo, datos.sugerencias?.subgrupo_nombre)
                : ''
        );

        llenarSelectSimple(mesSubgrupo, subgrupos, 'idsubgrupo', 'nombre', sugerido, 'Seleccionar subgrupo', true);
        if (grupo === 'nuevo') mesSubgrupo.value = 'nuevo';
        actualizarBloquesNuevos();
    }

    function llenarSelectIva(registros, seleccionado) {
        let html = opcion('', 'Seleccionar IVA', !seleccionado);

        for (const registro of registros ?? []) {
            const porcentaje = Number(registro.porcentaje ?? 0);
            const texto = `${registro.nombre} — ${porcentaje.toLocaleString('es-AR')} %`;
            const selected =
                String(registro.idiva) === String(seleccionado ?? '');

            html += `
                <option
                    value="${escaparHtml(registro.idiva)}"
                    data-porcentaje="${escaparHtml(porcentaje)}"
                    ${selected ? 'selected' : ''}
                >${escaparHtml(texto)}</option>
            `;
        }

        mesIva.innerHTML = html;
    }

    function porcentajeIvaSeleccionado() {
        const opcionActual = mesIva?.options?.[mesIva.selectedIndex];

        if (!opcionActual || mesIva.value === '') {
            return null;
        }

        const porcentaje = Number(opcionActual.dataset.porcentaje);

        return Number.isFinite(porcentaje)
            ? porcentaje
            : null;
    }

    function formatoPorcentaje(valor, decimales = 2) {
        return Number(valor ?? 0).toLocaleString('es-AR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: decimales,
        });
    }

    function recalcularPrecioMes(actualizarCampoPvp = false) {
        const precio = datosPreparacionActual?.precio;

        if (!precio) {
            return;
        }

        const precioLista = Number(precio.precio_lista ?? 0);
        const costoProveedor = Number(precio.costo_proveedor ?? 0);
        const descuentoProducto = Number(precio.descuento_producto ?? 0);
        const descuentoGeneral = Number(precio.descuento_general ?? 0);
        const incluyeIva = Boolean(precio.precios_incluyen_iva);
        const porcentajeIva = porcentajeIvaSeleccionado();
        const rentabilidad =
            normalizarDecimal(mesRentabilidad.value) ?? 0;

        /*
         * costoProveedor ya sale de la misma regla que el comparador:
         * Precio c/desc si existe; si no, precio lista con descuentos.
         */
        let costoConIva = costoProveedor;

        if (!incluyeIva && porcentajeIva !== null) {
            costoConIva =
                costoProveedor * (1 + porcentajeIva / 100);
        }

        const pvpSugerido =
            costoConIva * (1 + rentabilidad / 100);

        mesResumenLista.textContent = moneda.format(precioLista);
        mesResumenCosto.textContent = moneda.format(costoProveedor);
        mesResumenIva.textContent = moneda.format(costoConIva);
        mesResumenPvp.textContent = moneda.format(pvpSugerido);

        mesResumenCostoEtiqueta.textContent =
            precio.origen_costo === 'informado'
                ? 'Precio c/desc'
                : 'Luego de descuentos';

        const origen =
            precio.origen_costo === 'informado'
                ? 'El proveedor informó “Precio c/desc”, por eso se usa directamente.'
                : (
                    `Desc. producto ${formatoPorcentaje(descuentoProducto)}%`
                    + ` · Desc. general ${formatoPorcentaje(descuentoGeneral, 4)}%.`
                );

        let textoIva;

        if (incluyeIva) {
            textoIva =
                'La lista ya incluye IVA: no se vuelve a sumar.';
        } else if (porcentajeIva !== null) {
            textoIva =
                `La lista no incluye IVA: se suma ${formatoPorcentaje(porcentajeIva)}%.`;
        } else {
            textoIva =
                'La lista no incluye IVA: seleccioná una alícuota para calcularlo.';
        }

        mesFormulaPrecio.textContent = `${origen} ${textoIva}`;

        if (actualizarCampoPvp) {
            mesPvp.value =
                pvpSugerido.toFixed(2).replace('.', ',');
        }
    }

    function cerrarModalMes() {
        modalMes.hidden = true;
        document.body.classList.remove('modal-producto-mes-abierto');
        errorModalMes.hidden = true;
        errorModalMes.textContent = '';
        preparacionMesActual = null;
        datosPreparacionActual = null;
        pvpEditadoManualmente = false;
    }

    function cargarPreparacionEnFormulario(datos, preparacionGuardada = null) {
        datosPreparacionActual = datos;
        const prep = preparacionGuardada ?? {};

        mesOrigenDescripcion.textContent = datos.detalle?.descripcion || '—';
        mesOrigenProveedor.textContent = `${datos.detalle?.proveedor || '—'} · ${datos.detalle?.lista || 'Lista vigente'}`;
        mesOrigenBarra.textContent = datos.detalle?.codigo_barra || '—';
        mesOrigenClasificacion.textContent = datos.detalle?.clasificacion || 'Sin clasificación';

        mesDescripcion.value = prep.descripcion ?? datos.detalle?.descripcion ?? '';
        mesCodigoFabricante.value = prep.codigofabricante ?? datos.detalle?.codigo_fabricante ?? '';
        mesCodigoBarra.value = prep.codigobarra ?? datos.detalle?.codigo_barra ?? '';

        const marcaSugerida = prep.idmarca ?? valorSugerido(datos.sugerencias?.idmarca, datos.sugerencias?.marca_nombre);
        llenarSelectSimple(mesMarca, datos.opciones?.marcas, 'idmarca', 'nombre', marcaSugerida, 'Seleccionar marca', true);
        mesNuevaMarca.value = prep.nueva_marca ?? (marcaSugerida === 'nuevo' ? datos.sugerencias?.marca_nombre ?? '' : '');

        const lineaSugerida = prep.idlinea ?? valorSugerido(datos.sugerencias?.idlinea, datos.sugerencias?.linea_nombre);
        llenarSelectSimple(mesLinea, datos.opciones?.lineas, 'idlinea', 'nombre', lineaSugerida, 'Seleccionar línea', true);
        mesNuevaLinea.value = prep.nueva_linea ?? (lineaSugerida === 'nuevo' ? datos.sugerencias?.linea_nombre ?? '' : '');

        reconstruirGrupos(prep.idgrupo ?? '');
        if (mesLinea.value === 'nuevo') mesGrupo.value = 'nuevo';
        mesNuevoGrupo.value = prep.nuevo_grupo ?? (mesGrupo.value === 'nuevo' ? datos.sugerencias?.grupo_nombre ?? '' : '');

        reconstruirSubgrupos(prep.idsubgrupo ?? '');
        if (mesGrupo.value === 'nuevo') mesSubgrupo.value = 'nuevo';
        mesNuevoSubgrupo.value = prep.nuevo_subgrupo ?? (mesSubgrupo.value === 'nuevo' ? datos.sugerencias?.subgrupo_nombre ?? '' : '');

        llenarSelectSimple(
            mesUnidad,
            datos.opciones?.unidades,
            'idunidad',
            (registro) => `${registro.nombre} (${registro.abreviatura})`,
            prep.idunidad ?? '',
            'Seleccionar unidad',
            true
        );
        mesNuevaUnidad.value = prep.nueva_unidad ?? '';
        mesNuevaUnidadAbreviatura.value = prep.nueva_unidad_abreviatura ?? '';

        llenarSelectIva(
            datos.opciones?.alicuotas,
            prep.idiva ?? datos.sugerencias?.idiva ?? ''
        );

        mesContenido.value = prep.contenido_neto ?? '';
        mesColor.value = prep.color ?? '';

        mesRentabilidad.value =
            prep.rentabilidadobjetivo
            ?? String(datos.sugerencias?.rentabilidad ?? 0).replace('.', ',');

        pvpEditadoManualmente = prep.pvp_manual === true;

        if (
            prep.pvp !== undefined
            && prep.pvp !== null
            && prep.pvp !== ''
        ) {
            mesPvp.value = String(prep.pvp).replace('.', ',');
        } else {
            mesPvp.value = '';
            pvpEditadoManualmente = false;
        }

        actualizarBloquesNuevos();
        recalcularPrecioMes(!pvpEditadoManualmente);
    }

    async function abrirModalMes(grupo, oferta, preparacionGuardada = null) {
        preparacionMesActual = { grupo, oferta };
        modalMes.hidden = false;
        document.body.classList.add('modal-producto-mes-abierto');
        errorModalMes.hidden = true;
        errorModalMes.textContent = '';

        mesOrigenDescripcion.textContent = grupo.descripcion || 'Consultando...';
        mesOrigenProveedor.textContent = oferta.proveedor?.nombre || 'Consultando...';
        mesOrigenBarra.textContent = grupo.codigo_barra || '—';
        mesOrigenClasificacion.textContent = 'Consultando...';

        try {
            const respuesta = await fetch(construirUrlPreparar(oferta.iddetallecatalogo), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const datos = await respuesta.json().catch(() => null);
            if (!respuesta.ok) throw new Error(datos?.message || 'No se pudieron preparar los datos del producto.');
            cargarPreparacionEnFormulario(datos, preparacionGuardada);
            window.setTimeout(() => mesDescripcion.focus(), 0);
        } catch (error) {
            errorModalMes.textContent = error.message || 'No se pudieron preparar los datos.';
            errorModalMes.hidden = false;
        }
    }

    function validarPreparacionMes() {
        const requeridos = [
            [mesDescripcion, 'Completá la descripción interna.'],
            [mesMarca, 'Seleccioná una marca.'],
            [mesLinea, 'Seleccioná una línea.'],
            [mesGrupo, 'Seleccioná un grupo.'],
            [mesSubgrupo, 'Seleccioná un subgrupo.'],
            [mesUnidad, 'Seleccioná una unidad de medida.'],
            [mesIva, 'Seleccioná una alícuota de IVA.'],
            [mesPvp, 'Completá el precio final de venta.'],
            [mesRentabilidad, 'Completá la rentabilidad objetivo.'],
        ];

        for (const [campo, mensaje] of requeridos) {
            if (String(campo.value ?? '').trim() === '') { campo.focus(); return mensaje; }
        }

        if (mesMarca.value === 'nuevo' && mesNuevaMarca.value.trim() === '') return 'Escribí el nombre de la nueva marca.';
        if (mesLinea.value === 'nuevo' && mesNuevaLinea.value.trim() === '') return 'Escribí el nombre de la nueva línea.';
        if (mesGrupo.value === 'nuevo' && mesNuevoGrupo.value.trim() === '') return 'Escribí el nombre del nuevo grupo.';
        if (mesSubgrupo.value === 'nuevo' && mesNuevoSubgrupo.value.trim() === '') return 'Escribí el nombre del nuevo subgrupo.';
        if (mesUnidad.value === 'nuevo' && (mesNuevaUnidad.value.trim() === '' || mesNuevaUnidadAbreviatura.value.trim() === '')) return 'Completá el nombre y la abreviatura de la nueva unidad.';
        if (mesCodigoBarra.value.trim() !== '' && !/^\d+$/.test(mesCodigoBarra.value.trim())) return 'El código de barras debe contener solamente números.';

        const pvp = normalizarDecimal(mesPvp.value);
        if (pvp === null || pvp < 0) return 'Ingresá un precio de venta válido.';
        const rentabilidad = normalizarDecimal(mesRentabilidad.value);
        if (rentabilidad === null || rentabilidad < 0 || rentabilidad > 100) return 'La rentabilidad debe estar entre 0% y 100%.';
        return '';
    }

    function leerPreparacionMes() {
        return {
            descripcion: mesDescripcion.value.trim(),
            codigofabricante: mesCodigoFabricante.value.trim() || null,
            codigobarra: mesCodigoBarra.value.trim() || null,
            idmarca: mesMarca.value,
            nueva_marca: mesMarca.value === 'nuevo' ? mesNuevaMarca.value.trim() : null,
            idlinea: mesLinea.value,
            nueva_linea: mesLinea.value === 'nuevo' ? mesNuevaLinea.value.trim() : null,
            idgrupo: mesGrupo.value,
            nuevo_grupo: mesGrupo.value === 'nuevo' ? mesNuevoGrupo.value.trim() : null,
            idsubgrupo: mesSubgrupo.value,
            nuevo_subgrupo: mesSubgrupo.value === 'nuevo' ? mesNuevoSubgrupo.value.trim() : null,
            idunidad: mesUnidad.value,
            nueva_unidad: mesUnidad.value === 'nuevo' ? mesNuevaUnidad.value.trim() : null,
            nueva_unidad_abreviatura: mesUnidad.value === 'nuevo' ? mesNuevaUnidadAbreviatura.value.trim() : null,
            idiva: mesIva.value,
            contenido_neto: mesContenido.value.trim() || null,
            color: mesColor.value.trim() || null,
            pvp: normalizarDecimal(mesPvp.value),
            pvp_manual: pvpEditadoManualmente,
            rentabilidadobjetivo: normalizarDecimal(mesRentabilidad.value),
            visibleweb: 0,
            destacado: 0,
        };
    }

    function ocultarConfirmacion() {
        if (!panelConfirmacion) return;
        panelConfirmacion.hidden = true;
    }

    function pedidoListoParaConfirmar() {
        if (pedido.size === 0) {
            return {
                listo: false,
                mensaje: 'Agregá al menos un producto para confirmar el pedido.',
            };
        }

        if (!sucursal?.value) {
            return {
                listo: false,
                mensaje: 'Seleccioná la sucursal o depósito de destino.',
            };
        }

        for (const item of pedido.values()) {
            if (!Number.isInteger(Number(item.cantidad)) || Number(item.cantidad) < 1) {
                return {
                    listo: false,
                    mensaje: 'Todas las cantidades deben ser mayores a cero.',
                };
            }

            if (item.requiereProductoMes && !item.productoPreparado) {
                return {
                    listo: false,
                    mensaje: `Completá los datos MES de ${item.descripcion}.`,
                };
            }
        }

        return {
            listo: true,
            mensaje: 'Todo listo. Al confirmar se registrará una compra pendiente por cada proveedor.',
        };
    }

    function actualizarEstadoConfirmacion() {
        if (!botonConfirmarPedido || !notaProximoPaso) return;

        const estado = pedidoListoParaConfirmar();
        botonConfirmarPedido.disabled = !estado.listo || confirmandoPedido;
        botonConfirmarPedido.title = estado.mensaje;

        if (confirmandoPedido) {
            notaProximoPaso.textContent =
                'Estamos registrando los pedidos. No cierres esta pantalla.';
            notaProximoPaso.classList.remove(
                'nota-proximo-paso--lista',
                'nota-proximo-paso--error'
            );
            return;
        }

        if (errorConfirmacion !== '') {
            notaProximoPaso.textContent = errorConfirmacion;
            notaProximoPaso.classList.add('nota-proximo-paso--error');
            notaProximoPaso.classList.remove('nota-proximo-paso--lista');
            return;
        }

        notaProximoPaso.textContent = estado.mensaje;
        notaProximoPaso.classList.remove('nota-proximo-paso--error');
        notaProximoPaso.classList.toggle(
            'nota-proximo-paso--lista',
            estado.listo
        );
    }

    function payloadPedido() {
        return {
            idsucursal: Number(sucursal.value),
            productos: Array.from(pedido.values()).map((item) => ({
                iddetallecatalogo: Number(item.idDetalleCatalogo),
                cantidad: Number(item.cantidad),
                preparacion: item.requiereProductoMes
                    ? item.productoPreparado
                    : null,
            })),
        };
    }

    function primerErrorRespuesta(datos) {
        const errores = datos?.errors ?? {};
        const mensajes = Object.values(errores).flat();

        return mensajes[0]
            || datos?.message
            || 'No se pudo registrar el pedido. Revisá los datos e intentá nuevamente.';
    }

    function mostrarConfirmacionExitosa(datos) {
        const compras = Array.isArray(datos?.compras) ? datos.compras : [];
        const productosCreados = Array.isArray(datos?.productos_creados)
            ? datos.productos_creados
            : [];

        tituloConfirmacion.textContent = compras.length === 1
            ? 'La compra fue creada correctamente'
            : `Se generaron ${compras.length} compras correctamente`;

        listaConfirmacion.innerHTML = compras.map((compra) => `
            <article class="confirmacion-compra__item">
                <div>
                    <span>${escaparHtml(compra.codigo || `Compra ${compra.idcompra}`)}</span>
                    <strong>${escaparHtml(compra.proveedor)}</strong>
                </div>
                <div>
                    <span>Productos</span>
                    <strong>${escaparHtml(compra.cantidad_productos)}</strong>
                </div>
                <div>
                    <span>Total con IVA</span>
                    <strong>${escaparHtml(moneda.format(Number(compra.total || 0)))}</strong>
                </div>
                <span class="confirmacion-compra__estado">PENDIENTE</span>
            </article>
        `).join('');

        if (productosCreados.length > 0) {
            bloqueProductosMes.hidden = false;
            textoProductosMes.textContent = productosCreados
                .map((producto) => `${producto.codigo} — ${producto.descripcion}`)
                .join(' · ');
        } else {
            bloqueProductosMes.hidden = true;
            textoProductosMes.textContent = '';
        }

        panelConfirmacion.hidden = false;
        panelConfirmacion.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function confirmarPedido() {
        const estado = pedidoListoParaConfirmar();

        if (!estado.listo || confirmandoPedido) {
            actualizarEstadoConfirmacion();
            return;
        }

        confirmandoPedido = true;
        pedidoConfirmado = false;
        errorConfirmacion = '';
        actualizarEstadoConfirmacion();
        botonConfirmarPedido.textContent = 'Confirmando...';

        try {
            const respuesta = await fetch(aplicacion.dataset.urlConfirmar, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payloadPedido()),
            });

            const datos = await respuesta.json().catch(() => null);

            if (!respuesta.ok) {
                throw new Error(primerErrorRespuesta(datos));
            }

            pedidoConfirmado = true;
            pedido.clear();
            resultadosActuales = [];
            inputBuscar.value = '';
            dibujarPedido();
            mostrarEstado(
                'Pedido confirmado',
                'Podés revisar las compras generadas en el resumen de abajo.'
            );
            mostrarConfirmacionExitosa(datos);
        } catch (error) {
            errorConfirmacion = error.message
                || 'No se pudo registrar el pedido.';
        } finally {
            confirmandoPedido = false;
            botonConfirmarPedido.textContent = 'Confirmar pedidos';
            actualizarEstadoConfirmacion();
        }
    }

    function mostrarEstado(titulo, texto, tipo = '') {
        resultados.innerHTML = `
            <div class="estado-busqueda ${tipo ? `estado-busqueda--${tipo}` : ''}">
                <span class="estado-busqueda__icono">${tipo === 'error' ? '!' : '⌕'}</span>
                <h3>${escaparHtml(titulo)}</h3>
                <p>${escaparHtml(texto)}</p>
            </div>
        `;
    }

    async function buscarArticulos() {
        const termino = inputBuscar.value.trim();

        if (termino.length < 2) {
            controladorBusqueda?.abort();
            resultadosActuales = [];

            if (termino.length === 0) {
                mostrarEstado(
                    'Buscá el primer artículo',
                    'Messirve mostrará las ofertas vigentes y marcará el costo más bajo disponible.'
                );
            } else {
                mostrarEstado(
                    'Seguí escribiendo',
                    'Ingresá al menos 2 caracteres para comenzar la búsqueda.'
                );
            }

            return;
        }

        controladorBusqueda?.abort();
        controladorBusqueda = new AbortController();

        botonBuscar.disabled = true;
        botonBuscar.textContent = 'Buscando...';
        resultados.classList.add('resultados-compra--cargando');

        try {
            const url = new URL(aplicacion.dataset.urlBuscar, window.location.origin);
            url.searchParams.set('buscar', termino);

            const respuesta = await fetch(url, {
                signal: controladorBusqueda.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const datos = await respuesta.json().catch(() => null);

            if (!respuesta.ok) {
                const mensaje =
                    datos?.message
                    || Object.values(datos?.errors ?? {})?.flat()?.[0]
                    || 'No se pudo realizar la búsqueda.';
                throw new Error(mensaje);
            }

            resultadosActuales = Array.isArray(datos?.resultados)
                ? datos.resultados
                : [];

            dibujarResultados();
        } catch (error) {
            if (error.name !== 'AbortError') {
                mostrarEstado(
                    'No pudimos buscar los artículos',
                    error.message || 'Intentá nuevamente.',
                    'error'
                );
            }
        } finally {
            botonBuscar.disabled = false;
            botonBuscar.textContent = 'Buscar';
            resultados.classList.remove('resultados-compra--cargando');
        }
    }

    function dibujarResultados() {
        if (resultadosActuales.length === 0) {
            mostrarEstado(
                'No encontramos artículos vigentes',
                'Probá con otra descripción, código o producto MES.'
            );
            return;
        }

        resultados.innerHTML = resultadosActuales.map((grupo, indiceGrupo) => {
            const ofertas = Array.isArray(grupo.ofertas) ? grupo.ofertas : [];

            const meta = [
                grupo.codigo_mes ? `MES: ${escaparHtml(grupo.codigo_mes)}` : 'Sin producto MES todavía',
                grupo.codigo_barra ? `Código de barras: ${escaparHtml(grupo.codigo_barra)}` : 'Sin código de barras',
            ].join(' · ');

            const filas = ofertas.map((oferta, indiceOferta) => {
                const comparable = Boolean(oferta.es_comparable);
                const costo = comparable && oferta.costo_comparable !== null
                    ? moneda.format(Number(oferta.costo_comparable))
                    : 'Revisar IVA';

                const origen = textoOrigen(oferta.origen_costo);
                const claseFila = oferta.es_mejor
                    ? 'oferta-compra oferta-compra--mejor'
                    : comparable
                        ? 'oferta-compra'
                        : 'oferta-compra oferta-compra--no-comparable';

                const resultado = oferta.es_mejor
                    ? '<span class="insignia-oferta insignia-oferta--mejor">Mejor precio</span>'
                    : comparable
                        ? '<span class="insignia-oferta">Alternativa</span>'
                        : '<span class="insignia-oferta insignia-oferta--error">No comparable</span>';

                const seleccionado = pedido.get(grupo.grupo);
                const esOfertaSeleccionada = Boolean(
                    seleccionado
                    && Number(seleccionado.idDetalleCatalogo)
                        === Number(oferta.iddetallecatalogo)
                );

                let textoBoton = 'Agregar al pedido';
                if (esOfertaSeleccionada) {
                    textoBoton = 'Seleccionado';
                } else if (seleccionado) {
                    textoBoton = 'Elegir este proveedor';
                }

                const claseBoton = esOfertaSeleccionada
                    ? 'boton-agregar-oferta boton-agregar-oferta--seleccionado'
                    : 'boton-agregar-oferta';

                return `
                    <tr class="${claseFila}">
                        <td>
                            <strong>${escaparHtml(oferta.proveedor?.nombre)}</strong>
                            <small>${escaparHtml(oferta.catalogo?.nombre || 'Lista vigente')}</small>
                        </td>
                        <td>
                            <strong class="costo-oferta">${escaparHtml(costo)}</strong>
                            <small>${escaparHtml(origen)}</small>
                        </td>
                        <td>${resultado}</td>
                        <td>
                            <button
                                type="button"
                                class="${claseBoton}"
                                data-grupo="${indiceGrupo}"
                                data-oferta="${indiceOferta}"
                                ${(!comparable || esOfertaSeleccionada) ? 'disabled' : ''}
                            >
                                ${textoBoton}
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            const avisoMes = grupo.requiere_producto_mes
                ? `
                    <div class="aviso-producto-mes">
                        <strong>Este artículo todavía no tiene código MES.</strong>
                        <span>Si confirmamos el pedido en el próximo paso, ahí prepararemos sus datos internos y se creará el producto.</span>
                    </div>
                `
                : '';

            return `
                <article class="resultado-articulo">
                    <header class="resultado-articulo__cabecera">
                        <div>
                            <span class="resultado-articulo__etiqueta">Artículo encontrado</span>
                            <h3>${escaparHtml(grupo.descripcion)}</h3>
                            <p>${meta}</p>
                        </div>
                        <span class="cantidad-ofertas">${ofertas.length} ${ofertas.length === 1 ? 'oferta' : 'ofertas'}</span>
                    </header>

                    ${avisoMes}

                    <div class="tabla-ofertas-contenedor">
                        <table class="tabla-ofertas">
                            <thead>
                                <tr>
                                    <th>Proveedor / lista</th>
                                    <th>Costo comparable s/IVA</th>
                                    <th>Resultado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>${filas}</tbody>
                        </table>
                    </div>
                </article>
            `;
        }).join('');
    }

    function guardarOfertaEnPedido(grupo, oferta, productoPreparado = null) {
        pedidoConfirmado = false;
        errorConfirmacion = '';
        ocultarConfirmacion();
        const existente = pedido.get(grupo.grupo);
        const cantidad = existente?.cantidad ?? 1;
        pedido.set(grupo.grupo, {
            grupo: grupo.grupo,
            descripcion: grupo.descripcion,
            codigoMes: grupo.codigo_mes,
            codigoBarra: grupo.codigo_barra,
            requiereProductoMes: Boolean(grupo.requiere_producto_mes),
            productoPreparado: productoPreparado ?? existente?.productoPreparado ?? null,
            idProducto: grupo.idproducto,
            idDetalleCatalogo: Number(oferta.iddetallecatalogo),
            idProveedor: Number(oferta.proveedor?.id),
            proveedor: oferta.proveedor?.nombre || 'Proveedor',
            lista: oferta.catalogo?.nombre || 'Lista vigente',
            costo: Number(oferta.costo_comparable),
            cantidad,
        });
        dibujarResultados();
        dibujarPedido();
    }

    function agregarOferta(indiceGrupo, indiceOferta) {
        const grupo = resultadosActuales[indiceGrupo];
        const oferta = grupo?.ofertas?.[indiceOferta];
        if (!grupo || !oferta || !oferta.es_comparable) return;

        const existente = pedido.get(grupo.grupo);

        if (grupo.requiere_producto_mes) {
            const cambioProveedor = Boolean(
                existente
                && Number(existente.idDetalleCatalogo)
                    !== Number(oferta.iddetallecatalogo)
            );

            if (!existente?.productoPreparado || cambioProveedor) {
                abrirModalMes(
                    grupo,
                    oferta,
                    existente?.productoPreparado ?? null
                );
                return;
            }
        }

        guardarOfertaEnPedido(
            grupo,
            oferta,
            existente?.productoPreparado ?? null
        );
    }

    function dibujarPedido() {
        const items = Array.from(pedido.values());

        pedidoVacio.hidden = items.length > 0;
        contenedorTabla.hidden = items.length === 0;

        contadorPedido.textContent =
            `${items.length} ${items.length === 1 ? 'producto' : 'productos'}`;

        const proveedores = new Set(items.map((item) => item.idProveedor));
        cantidadProveedores.textContent = String(proveedores.size);

        let total = 0;

        listaPedido.innerHTML = items.map((item) => {
            const subtotal = item.costo * item.cantidad;
            total += subtotal;

            const codigo = item.codigoMes
                ? escaparHtml(item.codigoMes)
                : (
                    item.productoPreparado
                        ? '<span class="pedido-preparado-mes">MES al confirmar · Datos preparados</span>'
                        : '<span class="pedido-pendiente-mes">MES al confirmar · Faltan datos</span>'
                );

            const editarMes = item.requiereProductoMes
                ? `<button type="button" class="boton-editar-mes" data-accion="editar-mes" data-grupo="${escaparHtml(item.grupo)}">Editar datos MES</button>`
                : '';

            return `
                <tr>
                    <td>
                        <strong>${escaparHtml(item.descripcion)}</strong>
                        <small>${codigo}</small>
                        ${editarMes}
                    </td>
                    <td>
                        <strong>${escaparHtml(item.proveedor)}</strong>
                        <small>${escaparHtml(item.lista)}</small>
                    </td>
                    <td class="importe-pedido">${escaparHtml(moneda.format(item.costo))}</td>
                    <td>
                        <div class="control-cantidad">
                            <button type="button" data-accion="restar" data-grupo="${escaparHtml(item.grupo)}">−</button>
                            <input
                                type="number"
                                min="1"
                                max="999999"
                                step="1"
                                value="${item.cantidad}"
                                data-accion="cantidad"
                                data-grupo="${escaparHtml(item.grupo)}"
                                aria-label="Cantidad de ${escaparHtml(item.descripcion)}"
                            >
                            <button type="button" data-accion="sumar" data-grupo="${escaparHtml(item.grupo)}">+</button>
                        </div>
                    </td>
                    <td class="importe-pedido importe-pedido--subtotal">${escaparHtml(moneda.format(subtotal))}</td>
                    <td>
                        <button type="button" class="boton-quitar-pedido" data-accion="quitar" data-grupo="${escaparHtml(item.grupo)}">
                            Quitar
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        totalPedido.textContent = moneda.format(total);
        actualizarEstadoConfirmacion();
    }

    function modificarCantidad(grupo, nuevaCantidad) {
        const item = pedido.get(grupo);
        if (!item) return;

        const cantidad = Math.max(
            1,
            Math.min(999999, Number.parseInt(String(nuevaCantidad), 10) || 1)
        );

        pedidoConfirmado = false;
        errorConfirmacion = '';
        ocultarConfirmacion();
        item.cantidad = cantidad;
        pedido.set(grupo, item);
        dibujarPedido();
    }

    inputBuscar?.addEventListener('input', () => {
        window.clearTimeout(temporizadorBusqueda);

        const termino = inputBuscar.value.trim();

        if (termino.length < 2) {
            buscarArticulos();
            return;
        }

        temporizadorBusqueda = window.setTimeout(() => {
            buscarArticulos();
        }, 300);
    });

    formulario?.addEventListener('submit', (evento) => {
        evento.preventDefault();
        window.clearTimeout(temporizadorBusqueda);
        buscarArticulos();
    });

    resultados?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('.boton-agregar-oferta');
        if (!boton) return;

        agregarOferta(
            Number.parseInt(boton.dataset.grupo || '', 10),
            Number.parseInt(boton.dataset.oferta || '', 10)
        );
    });

    listaPedido?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('button[data-accion]');
        if (!boton) return;

        const grupo = boton.dataset.grupo;
        const item = pedido.get(grupo);
        if (!item) return;

        if (boton.dataset.accion === 'restar') {
            modificarCantidad(grupo, item.cantidad - 1);
        } else if (boton.dataset.accion === 'sumar') {
            modificarCantidad(grupo, item.cantidad + 1);
        } else if (boton.dataset.accion === 'quitar') {
            pedidoConfirmado = false;
            errorConfirmacion = '';
            ocultarConfirmacion();
            pedido.delete(grupo);
            dibujarResultados();
            dibujarPedido();
        } else if (boton.dataset.accion === 'editar-mes') {
            const grupoActual = resultadosActuales.find((resultado) => resultado.grupo === grupo);
            if (grupoActual) {
                const ofertaActual = grupoActual.ofertas.find((oferta) => Number(oferta.iddetallecatalogo) === Number(item.idDetalleCatalogo));
                if (ofertaActual) abrirModalMes(grupoActual, ofertaActual, item.productoPreparado);
            }
        }
    });

    listaPedido?.addEventListener('change', (evento) => {
        const input = evento.target.closest('input[data-accion="cantidad"]');
        if (!input) return;

        modificarCantidad(input.dataset.grupo, input.value);
    });

    sucursal?.addEventListener('change', () => {
        pedidoConfirmado = false;
        errorConfirmacion = '';
        ocultarConfirmacion();
        actualizarEstadoConfirmacion();
    });

    mesMarca?.addEventListener('change', actualizarBloquesNuevos);
    mesLinea?.addEventListener('change', () => { reconstruirGrupos(); actualizarBloquesNuevos(); });
    mesGrupo?.addEventListener('change', () => { reconstruirSubgrupos(); actualizarBloquesNuevos(); });
    mesSubgrupo?.addEventListener('change', actualizarBloquesNuevos);
    mesUnidad?.addEventListener('change', actualizarBloquesNuevos);

    mesIva?.addEventListener('change', () => {
        recalcularPrecioMes(!pvpEditadoManualmente);
    });

    mesRentabilidad?.addEventListener('input', () => {
        recalcularPrecioMes(!pvpEditadoManualmente);
    });

    mesPvp?.addEventListener('input', () => {
        pvpEditadoManualmente = true;
    });

    mesRecalcularPvp?.addEventListener('click', () => {
        pvpEditadoManualmente = false;
        recalcularPrecioMes(true);
    });

    document.querySelectorAll('[data-cerrar-modal-mes]').forEach((boton) => boton.addEventListener('click', cerrarModalMes));

    formularioMes?.addEventListener('submit', (evento) => {
        evento.preventDefault();
        const error = validarPreparacionMes();
        if (error !== '') {
            errorModalMes.textContent = error;
            errorModalMes.hidden = false;
            return;
        }
        if (!preparacionMesActual) return;
        guardarOfertaEnPedido(preparacionMesActual.grupo, preparacionMesActual.oferta, leerPreparacionMes());
        cerrarModalMes();
    });

    botonConfirmarPedido?.addEventListener('click', confirmarPedido);

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && modalMes && !modalMes.hidden) cerrarModalMes();
    });

    window.addEventListener('beforeunload', (evento) => {
        if (pedido.size === 0 || pedidoConfirmado) return;

        evento.preventDefault();
        evento.returnValue = '';
    });

    dibujarPedido();
})();
