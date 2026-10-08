/** Devuelve un mensaje cuando el dato no es válido. */
export function validarValor(valor, tipo, obligatorio = false, longitudMaxima = 0) {
    const texto = String(valor ?? '').trim();
    if (!texto) return obligatorio ? 'Completá este campo.' : '';
    if (longitudMaxima > 0 && texto.length > longitudMaxima) {
        return `Este campo admite hasta ${longitudMaxima} caracteres.`;
    }
    if (tipo === 'nombre') {
        return /[\p{L}\p{N}]/u.test(texto) ? '' : 'Escribí al menos una letra o un número.';
    }
    if (tipo === 'barras') {
        return /^[0-9]+$/.test(texto) ? '' : 'Usá solo números, sin espacios ni signos.';
    }
    if (tipo === 'abreviatura') {
        return /^[\p{L}\p{N}.\/²³]+(?: [\p{L}\p{N}.\/²³]+)*$/u.test(texto)
            ? '' : 'Usá letras, números, puntos, barras o espacios simples.';
    }
    if (tipo === 'contenido') {
        const fraccion = /^([0-9]+)[ \t]*\/[ \t]*([0-9]+)$/.exec(texto);
        let cantidad;
        if (fraccion) {
            if (Number(fraccion[2]) === 0) return 'La fracción no puede dividirse por cero.';
            cantidad = Math.round((Number(fraccion[1]) / Number(fraccion[2])) * 1000) / 1000;
        } else {
            if (!/^[0-9]+(?:[.,][0-9]{1,3})?$/.test(texto)) {
                return 'Usá una cantidad como 4, 0,25 o 1/4, sin escribir la unidad.';
            }
            cantidad = Number(texto.replace(',', '.'));
        }
        return Number.isFinite(cantidad) && cantidad >= 0.001 && cantidad <= 9999999.999
            ? '' : 'La cantidad debe estar entre 0,001 y 9.999.999,999.';
    }
    if (tipo === 'precio' || tipo === 'porcentaje') {
        if (!/^[0-9]+(?:[.,][0-9]{1,2})?$/.test(texto)) {
            return 'Usá números positivos o cero y hasta 2 decimales, sin separador de miles.';
        }
        const cantidad = Number(texto.replace(',', '.'));
        const maximo = tipo === 'porcentaje' ? 100 : 9999999999.99;
        return Number.isFinite(cantidad) && cantidad <= maximo ? '' : (
            tipo === 'porcentaje' ? 'La rentabilidad debe estar entre 0 y 100 %.'
                : 'El precio no puede superar 9.999.999.999,99.'
        );
    }
    return '';
}

export function opcionDisponible(valorOpcion, padreOpcion, valorPadre) {
    if (valorOpcion === '') return true;
    if (!valorPadre) return false;
    if (valorOpcion === 'nuevo') return true;
    return valorPadre !== 'nuevo' && String(padreOpcion) === String(valorPadre);
}

function iniciarFormulario() {
    const formulario = document.getElementById('formulario-producto-manual');
    if (!formulario) return;

    const campos = [...formulario.querySelectorAll('[data-validar]')];
    const maestros = [...formulario.querySelectorAll('[data-maestro]')];
    const selectorUnidad = formulario.querySelector('[data-selector-unidad]');
    const altaUnidad = formulario.querySelector('[data-alta-unidad]');
    const nombreUnidad = document.getElementById('nueva_unidad');
    const abreviaturaUnidad = document.getElementById('nueva_unidad_abreviatura');
    const botonGuardar = document.getElementById('guardar-producto-manual');
    const estadoGuardado = document.getElementById('estado-guardado');

    function mostrarError(campo, mensaje, invalido = Boolean(mensaje)) {
        const aviso = document.getElementById(`error-${campo.id}`);
        if (aviso) {
            aviso.textContent = mensaje;
            aviso.hidden = !mensaje;
        }
        campo.setAttribute('aria-invalid', String(invalido));
    }

    function validarCampo(campo) {
        if (campo.disabled) {
            campo.setCustomValidity('');
            return true;
        }
        const mensaje = validarValor(campo.value, campo.dataset.validar, campo.required, campo.maxLength);
        campo.setCustomValidity(mensaje);
        mostrarError(campo, mensaje);
        return !mensaje;
    }

    function actualizarClasificacion() {
        // El orden es marca, línea, grupo y subgrupo: los hijos se actualizan al final.
        for (const selector of maestros) {
            const padre = document.getElementById(selector.dataset.dependeDe);
            if (padre) {
                for (const opcion of selector.options) {
                    const disponible = opcionDisponible(opcion.value, opcion.dataset.padre, padre.value);
                    opcion.disabled = !disponible;
                    opcion.hidden = !disponible;
                }
                if (selector.selectedOptions[0]?.disabled) selector.value = '';
            }

            const contenedor = formulario.querySelector(`[data-alta-nueva="${selector.id}"]`);
            const entrada = document.getElementById(selector.dataset.nuevo);
            const crear = selector.value === 'nuevo';
            contenedor.hidden = !crear;
            entrada.disabled = !crear;
            entrada.required = crear;
            if (!crear) {
                entrada.setCustomValidity('');
                mostrarError(entrada, '');
            }
        }
    }

    for (const selector of maestros) {
        selector.addEventListener('change', () => {
            actualizarClasificacion();
            mostrarError(selector, '');
        });
    }
    actualizarClasificacion();

    function actualizarUnidad() {
        const crear = selectorUnidad?.value === 'nuevo';
        if (altaUnidad) altaUnidad.hidden = !crear;

        for (const entrada of [nombreUnidad, abreviaturaUnidad]) {
            if (!entrada) continue;
            entrada.disabled = !crear;
            entrada.required = crear;
            if (!crear) {
                entrada.setCustomValidity('');
                mostrarError(entrada, '');
            }
        }
    }

    selectorUnidad?.addEventListener('change', () => {
        actualizarUnidad();
        mostrarError(selectorUnidad, '');
    });
    actualizarUnidad();

    for (const campo of campos) {
        const tipo = campo.dataset.validar;
        const caracteres = tipo === 'barras' ? /^[0-9]*$/
            : tipo === 'contenido' ? /^[0-9.,/ \t]*$/
                : tipo === 'abreviatura' ? /^[\p{L}\p{N}.\/²³ ]*$/u
                : (tipo === 'precio' || tipo === 'porcentaje') ? /^[0-9.,]*$/ : null;

        function impedirCaracteres(evento, texto) {
            if (!caracteres || texto == null || caracteres.test(texto)) return;
            evento.preventDefault();
            const mensajeActual = validarValor(campo.value, tipo, campo.required, campo.maxLength);
            campo.setCustomValidity(mensajeActual);
            mostrarError(campo, tipo === 'barras'
                ? 'En el código de barras solo se admiten números.'
                : 'Usá los números y separadores indicados debajo del campo.', Boolean(mensajeActual));
        }

        campo.addEventListener('beforeinput', (evento) => {
            if (!evento.isComposing && evento.inputType?.startsWith('insert')) {
                impedirCaracteres(evento, evento.data);
            }
        });
        campo.addEventListener('paste', (evento) => {
            impedirCaracteres(evento, evento.clipboardData?.getData('text'));
        });
        campo.addEventListener('input', () => validarCampo(campo));
        campo.addEventListener('blur', () => {
            campo.value = campo.value.trim();
            validarCampo(campo);
        });
    }

    // También se limpia el aviso del servidor cuando se corrige un dato común.
    for (const campo of formulario.querySelectorAll('input[type="text"]:not([data-validar]), select:not([data-maestro])')) {
        campo.addEventListener('input', () => mostrarError(campo, ''));
        campo.addEventListener('change', () => mostrarError(campo, ''));
    }

    const archivo = document.getElementById('imagen');
    const vista = document.getElementById('imagen-previsualizada');
    const sinVista = document.getElementById('imagen-sin-vista-previa');
    const quitar = document.getElementById('quitar-imagen');
    let urlImagen = null;

    function soltarImagen() {
        if (urlImagen) URL.revokeObjectURL(urlImagen);
        urlImagen = null;
    }

    function comprobarArchivo() {
        const foto = archivo.files[0];
        let mensaje = '';
        if (foto && !['image/jpeg', 'image/png', 'image/webp'].includes(foto.type)) {
            mensaje = 'Elegí una imagen JPG, PNG o WEBP.';
        } else if (foto && foto.size > 2 * 1024 * 1024) {
            mensaje = 'La imagen no puede superar los 2 MB.';
        }
        archivo.setCustomValidity(mensaje);
        mostrarError(archivo, mensaje);
        return !mensaje;
    }

    function actualizarImagen() {
        soltarImagen();
        vista.hidden = true;
        vista.removeAttribute('src');
        sinVista.hidden = false;
        quitar.hidden = !archivo.files.length;
        if (!comprobarArchivo() || !archivo.files[0]) return;

        urlImagen = URL.createObjectURL(archivo.files[0]);
        const urlActual = urlImagen;
        vista.onload = () => {
            if (urlImagen !== urlActual) return;
            vista.hidden = false;
            sinVista.hidden = true;
        };
        vista.onerror = () => {
            if (urlImagen !== urlActual) return;
            archivo.setCustomValidity('No se pudo abrir esta imagen. Elegí otra foto.');
            mostrarError(archivo, archivo.validationMessage);
        };
        vista.src = urlImagen;
    }

    archivo.addEventListener('change', actualizarImagen);
    quitar.addEventListener('click', () => {
        archivo.value = '';
        actualizarImagen();
    });

    formulario.addEventListener('invalid', (evento) => {
        const campo = evento.target;
        if (campo.matches('[data-validar]')) validarCampo(campo);
        else if (campo.id) mostrarError(campo, campo.validationMessage);
    }, true);

    formulario.addEventListener('submit', (evento) => {
        if (formulario.dataset.enviando === '1') {
            evento.preventDefault();
            return;
        }
        for (const campo of campos) {
            campo.value = campo.value.trim();
            validarCampo(campo);
        }
        // Conserva cualquier error detectado al abrir el archivo en la vista previa.
        if (!formulario.reportValidity()) {
            evento.preventDefault();
            return;
        }
        formulario.dataset.enviando = '1';
        botonGuardar.disabled = true;
        botonGuardar.textContent = 'Guardando…';
        estadoGuardado.textContent = 'Guardando el producto. Esperá un momento.';
    });

    window.addEventListener('pagehide', soltarImagen);
    window.addEventListener('pageshow', () => {
        formulario.dataset.enviando = '0';
        botonGuardar.disabled = false;
        botonGuardar.textContent = 'Guardar producto';
        estadoGuardado.textContent = '';
        if (archivo.files.length) actualizarImagen();
    });
    document.getElementById('errores-del-producto')?.focus();
}

if (typeof document !== 'undefined') iniciarFormulario();
