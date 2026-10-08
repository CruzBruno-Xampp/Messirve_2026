'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-anulacion');
    const botonAbrir = document.getElementById('abrir-anulacion');

    if (!modal || !botonAbrir) {
        return;
    }

    const contenido = modal.querySelector('.modal-anulacion__contenido');
    const campoMotivo = document.getElementById('motivo');
    const formulario = document.getElementById('formulario-anulacion');
    let ultimoElementoActivo = null;

    const abrirModal = () => {
        ultimoElementoActivo = document.activeElement;
        modal.classList.add('modal-anulacion--visible');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-abierto');

        window.setTimeout(() => {
            if (campoMotivo) {
                campoMotivo.focus();
            } else {
                contenido?.focus();
            }
        }, 0);
    };

    const cerrarModal = () => {
        modal.classList.remove('modal-anulacion--visible');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-abierto');
        ultimoElementoActivo?.focus();
    };

    botonAbrir.addEventListener('click', abrirModal);

    modal.querySelectorAll('[data-cerrar-modal]').forEach((elemento) => {
        elemento.addEventListener('click', cerrarModal);
    });

    document.addEventListener('keydown', (evento) => {
        if (
            evento.key === 'Escape'
            && modal.classList.contains('modal-anulacion--visible')
        ) {
            cerrarModal();
        }
    });

    formulario?.addEventListener('submit', () => {
        if (!formulario.checkValidity()) {
            return;
        }

        formulario.classList.add('formulario-anulacion--enviando');

        const botonEnviar = formulario.querySelector('[type="submit"]');

        if (botonEnviar) {
            botonEnviar.disabled = true;
            botonEnviar.textContent = 'Anulando venta...';
        }
    });

    if (modal.dataset.openOnError === '1') {
        abrirModal();
    }
});
