document.addEventListener('DOMContentLoaded', () => {
    const grafico = document.getElementById('grafico-ventas');

    if (!grafico) {
        return;
    }

    const datos = Array.from(grafico.querySelectorAll('.grafico-lineal__dato'));
    const svg = grafico.querySelector('.grafico-lineal__svg');
    const linea = grafico.querySelector('.grafico-lineal__linea');
    const area = grafico.querySelector('.grafico-lineal__area-relleno');
    const grupoPuntos = grafico.querySelector('.grafico-lineal__puntos');
    const grupoGrilla = grafico.querySelector('.grafico-lineal__grilla');

    if (!svg || !linea || !area || !grupoPuntos || !grupoGrilla || datos.length === 0) {
        return;
    }

    const namespaceSvg = 'http://www.w3.org/2000/svg';
    const ancho = 700;
    const alto = 230;
    const margenX = 34;
    const margenSuperior = 30;
    const margenInferior = 24;
    const baseY = alto - margenInferior;
    const altoUtil = baseY - margenSuperior;

    const valores = datos.map((dato) => Math.max(0, Number(dato.dataset.valor) || 0));
    const maximo = Math.max(...valores, 1);
    const pasoX = datos.length > 1
        ? (ancho - (margenX * 2)) / (datos.length - 1)
        : 0;

    for (let indice = 0; indice < 4; indice += 1) {
        const y = margenSuperior + ((altoUtil / 3) * indice);
        const lineaGrilla = document.createElementNS(namespaceSvg, 'line');
        lineaGrilla.setAttribute('x1', margenX);
        lineaGrilla.setAttribute('x2', ancho - margenX);
        lineaGrilla.setAttribute('y1', y);
        lineaGrilla.setAttribute('y2', y);
        grupoGrilla.append(lineaGrilla);
    }

    const puntos = valores.map((valor, indice) => {
        const x = margenX + (pasoX * indice);
        const proporcion = valor / maximo;
        const y = baseY - (proporcion * altoUtil);
        return { x, y, valor };
    });

    const puntosLinea = puntos
        .map((punto) => `${punto.x.toFixed(2)},${punto.y.toFixed(2)}`)
        .join(' ');

    linea.setAttribute('points', puntosLinea);

    const primerPunto = puntos[0];
    const ultimoPunto = puntos[puntos.length - 1];
    const caminoArea = [
        `M ${primerPunto.x} ${baseY}`,
        ...puntos.map((punto) => `L ${punto.x} ${punto.y}`),
        `L ${ultimoPunto.x} ${baseY}`,
        'Z',
    ].join(' ');
    area.setAttribute('d', caminoArea);

    puntos.forEach((punto, indice) => {
        const circulo = document.createElementNS(namespaceSvg, 'circle');
        circulo.setAttribute('cx', punto.x);
        circulo.setAttribute('cy', punto.y);
        circulo.setAttribute('r', '5');

        const titulo = document.createElementNS(namespaceSvg, 'title');
        titulo.textContent = `${datos[indice].dataset.etiqueta} · ${datos[indice].dataset.importe}`;
        circulo.append(titulo);
        grupoPuntos.append(circulo);
    });

    const longitud = linea.getTotalLength();
    linea.style.strokeDasharray = String(longitud);
    linea.style.strokeDashoffset = String(longitud);

    window.requestAnimationFrame(() => {
        linea.style.transition = 'stroke-dashoffset .75s ease';
        linea.style.strokeDashoffset = '0';
        area.style.opacity = '1';
    });
});
