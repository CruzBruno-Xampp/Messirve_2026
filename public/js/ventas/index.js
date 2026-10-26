'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('modal-metodos-pago');
    const botonAbrir = document.getElementById('abrir-metodos-pago');
    if (!modal || !botonAbrir) return;
    const contenido = modal.querySelector('.modal-metodos-pago__contenido');
    const formulario = document.getElementById('formulario-metodos-pago');
    let ultimoElementoActivo = null;
    const abrirModal = () => {
        ultimoElementoActivo = document.activeElement;
        modal.classList.add('modal-metodos-pago--visible');
        modal.setAttribute('aria-hidden','false');
        document.body.classList.add('modal-abierto');
        window.setTimeout(() => (modal.querySelector('input[type="number"]') || contenido)?.focus(), 0);
    };
    const cerrarModal = () => {
        modal.classList.remove('modal-metodos-pago--visible');
        modal.setAttribute('aria-hidden','true');
        document.body.classList.remove('modal-abierto');
        ultimoElementoActivo?.focus();
    };
    botonAbrir.addEventListener('click', abrirModal);
    modal.querySelectorAll('[data-cerrar-metodos-pago]').forEach(e => e.addEventListener('click', cerrarModal));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('modal-metodos-pago--visible')) cerrarModal(); });
    formulario?.querySelectorAll('input[type="number"]').forEach(campo => {
        campo.addEventListener('keydown', e => { if (['e','E','+','-'].includes(e.key)) e.preventDefault(); });
        campo.addEventListener('input', () => { const n=Number(campo.value); if(Number.isFinite(n)){ if(n>100)campo.value='100'; else if(n<0)campo.value='0'; } });
    });
    formulario?.addEventListener('submit', () => {
        if (!formulario.checkValidity()) return;
        formulario.classList.add('formulario-metodos-pago--enviando');
        const b=formulario.querySelector('[type="submit"]'); if(b){b.disabled=true;b.textContent='Guardando…';}
    });
    if (modal.dataset.openOnError === '1') abrirModal();
});
