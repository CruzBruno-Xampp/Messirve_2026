(() => {
    'use strict';

    const formulario = document.getElementById('filtros-reportes');
    const estadoFiltros = document.getElementById('estado-filtros');
    const grafico = document.getElementById('grafico-ventas-linea');

    const formatoMoneda = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 0,
    });

    function aplicarFiltrosAutomaticos() {
        if (!formulario) {
            return;
        }

        const desde = formulario.querySelector('#desde');
        const hasta = formulario.querySelector('#hasta');
        const buscar = formulario.querySelector('#buscar');
        const selects = formulario.querySelectorAll('select');
        let temporizadorBusqueda = null;
        let enviando = false;

        const fechasValidas = () => {
            if (!desde || !hasta || !desde.value || !hasta.value) {
                return false;
            }

            return desde.value <= hasta.value;
        };

        const enviar = (mensaje = 'Aplicando filtros…') => {
            if (enviando || !formulario.checkValidity() || !fechasValidas()) {
                return;
            }

            enviando = true;
            formulario.classList.add('filtros--cargando');

            if (estadoFiltros) {
                estadoFiltros.textContent = mensaje;
            }

            formulario.requestSubmit();
        };

        selects.forEach((select) => {
            select.addEventListener('change', () => enviar());
        });

        [desde, hasta].forEach((campoFecha) => {
            campoFecha?.addEventListener('change', () => {
                if (!fechasValidas()) {
                    if (estadoFiltros) {
                        estadoFiltros.textContent = 'La fecha desde no puede ser posterior a la fecha hasta.';
                    }
                    return;
                }

                enviar('Actualizando período…');
            });
        });

        if (buscar) {
            buscar.addEventListener('input', () => {
                window.clearTimeout(temporizadorBusqueda);
                temporizadorBusqueda = window.setTimeout(() => {
                    enviar('Buscando operaciones…');
                }, 650);
            });

            buscar.addEventListener('keydown', (evento) => {
                if (evento.key !== 'Enter') {
                    return;
                }

                evento.preventDefault();
                window.clearTimeout(temporizadorBusqueda);
                enviar('Buscando operaciones…');
            });
        }
    }

    function crearElementoSvg(nombre, atributos = {}) {
        const elemento = document.createElementNS('http://www.w3.org/2000/svg', nombre);

        Object.entries(atributos).forEach(([clave, valor]) => {
            elemento.setAttribute(clave, String(valor));
        });

        return elemento;
    }

    function abreviarDinero(valor) {
        const numero = Number(valor) || 0;

        if (numero >= 1_000_000) {
            return `$ ${(numero / 1_000_000).toLocaleString('es-AR', { maximumFractionDigits: 1 })} M`;
        }

        if (numero >= 1_000) {
            return `$ ${Math.round(numero / 1_000).toLocaleString('es-AR')} mil`;
        }

        return formatoMoneda.format(numero);
    }

    function dibujarGraficoLinea() {
        if (!grafico) {
            return;
        }

        const datos = Array.from(grafico.querySelectorAll('.grafico-linea__dato'))
            .map((elemento) => ({
                etiqueta: elemento.dataset.etiqueta || '',
                total: Number(elemento.dataset.total || 0),
                cantidad: Number(elemento.dataset.cantidad || 0),
            }))
            .filter((punto) => Number.isFinite(punto.total));

        if (datos.length === 0) {
            return;
        }

        grafico.querySelectorAll('svg').forEach((svgExistente) => svgExistente.remove());

        const ancho = 1000;
        const alto = 300;
        const margen = { superior: 44, derecho: 34, inferior: 68, izquierdo: 86 };
        const anchoUtil = ancho - margen.izquierdo - margen.derecho;
        const altoUtil = alto - margen.superior - margen.inferior;
        const maximoReal = Math.max(...datos.map((punto) => punto.total), 1);
        const maximoEje = maximoReal * 1.18;
        const pasos = 4;

        const svg = crearElementoSvg('svg', {
            class: 'grafico-linea',
            viewBox: `0 0 ${ancho} ${alto}`,
            preserveAspectRatio: 'xMidYMid meet',
            'aria-hidden': 'true',
        });

        const x = (indice) => {
            if (datos.length === 1) {
                return margen.izquierdo + (anchoUtil / 2);
            }

            return margen.izquierdo + (indice * anchoUtil / (datos.length - 1));
        };

        const y = (valor) => margen.superior + altoUtil - ((valor / maximoEje) * altoUtil);
        const baseY = margen.superior + altoUtil;

        for (let i = 0; i <= pasos; i += 1) {
            const proporcion = i / pasos;
            const valor = maximoEje * (1 - proporcion);
            const yLinea = margen.superior + (altoUtil * proporcion);

            svg.appendChild(crearElementoSvg('line', {
                x1: margen.izquierdo,
                y1: yLinea,
                x2: ancho - margen.derecho,
                y2: yLinea,
                class: 'grafico-linea__cuadricula',
            }));

            const etiquetaY = crearElementoSvg('text', {
                x: margen.izquierdo - 12,
                y: yLinea + 4,
                'text-anchor': 'end',
                class: 'grafico-linea__texto-eje',
            });
            etiquetaY.textContent = abreviarDinero(valor);
            svg.appendChild(etiquetaY);
        }

        svg.appendChild(crearElementoSvg('line', {
            x1: margen.izquierdo,
            y1: baseY,
            x2: ancho - margen.derecho,
            y2: baseY,
            class: 'grafico-linea__eje',
        }));

        const puntos = datos.map((punto, indice) => ({
            ...punto,
            x: x(indice),
            y: y(punto.total),
        }));

        if (puntos.length > 1) {
            const area = crearElementoSvg('path', {
                class: 'grafico-linea__area',
                d: [
                    `M ${puntos[0].x} ${baseY}`,
                    ...puntos.map((punto) => `L ${punto.x} ${punto.y}`),
                    `L ${puntos[puntos.length - 1].x} ${baseY}`,
                    'Z',
                ].join(' '),
            });
            svg.appendChild(area);

            const linea = crearElementoSvg('polyline', {
                class: 'grafico-linea__trazo',
                points: puntos.map((punto) => `${punto.x},${punto.y}`).join(' '),
            });
            svg.appendChild(linea);
        }

        puntos.forEach((punto) => {
            const valor = crearElementoSvg('text', {
                x: punto.x,
                y: Math.max(18, punto.y - 13),
                'text-anchor': 'middle',
                class: 'grafico-linea__texto-valor',
            });
            valor.textContent = formatoMoneda.format(punto.total);
            svg.appendChild(valor);

            const circulo = crearElementoSvg('circle', {
                cx: punto.x,
                cy: punto.y,
                r: 5,
                class: 'grafico-linea__punto',
                tabindex: 0,
                role: 'img',
                'aria-label': `${punto.etiqueta}: ${formatoMoneda.format(punto.total)}, ${punto.cantidad} ${punto.cantidad === 1 ? 'venta' : 'ventas'}`,
            });
            const titulo = crearElementoSvg('title');
            titulo.textContent = `${punto.etiqueta} · ${formatoMoneda.format(punto.total)} · ${punto.cantidad} ${punto.cantidad === 1 ? 'venta' : 'ventas'}`;
            circulo.appendChild(titulo);
            svg.appendChild(circulo);

            const fecha = crearElementoSvg('text', {
                x: punto.x,
                y: baseY + 25,
                'text-anchor': 'middle',
                class: 'grafico-linea__texto-fecha',
            });
            fecha.textContent = punto.etiqueta;
            svg.appendChild(fecha);

            const cantidad = crearElementoSvg('text', {
                x: punto.x,
                y: baseY + 42,
                'text-anchor': 'middle',
                class: 'grafico-linea__texto-ventas',
            });
            cantidad.textContent = `${punto.cantidad} ${punto.cantidad === 1 ? 'venta' : 'ventas'}`;
            svg.appendChild(cantidad);
        });

        grafico.appendChild(svg);
    }

    aplicarFiltrosAutomaticos();
    dibujarGraficoLinea();
})();
