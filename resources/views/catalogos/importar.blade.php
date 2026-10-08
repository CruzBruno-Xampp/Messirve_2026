<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>Importar catálogo | Messirve</title>

    {{-- El archivo CSS lo crearemos en el siguiente paso. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/catalogos/importar.css') }}"
    >

    {{-- Barra de navegación reutilizable del sector administrativo. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/admin/barra-lateral.css') }}"
    >
</head>

<body class="con-barra-lateral">

    @php
        $usuarioAdmin = auth('web')->user();
        $puedeCrearProveedores = $usuarioAdmin?->tienePermiso('proveedores.crear') ?? false;
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-importacion">

        {{-- Encabezado principal de la pantalla. --}}
        <header class="encabezado">
            <div>
                <p class="encabezado__seccion">
                    Administración de catálogos
                </p>

                <h1>Importar catálogo de proveedor</h1>

                <p class="encabezado__descripcion">
                    Subí un archivo Excel y el sistema analizará
                    automáticamente sus hojas y columnas.
                </p>
            </div>

            <div class="encabezado__marca">
                <span>MES.</span>SIRVE
            </div>
        </header>

        {{-- Indicador del flujo completo de importación. --}}
        <section class="pasos" aria-label="Pasos de la importación">

            <article class="paso paso--activo">
                <span class="paso__numero">1</span>

                <div>
                    <strong>Subir archivo</strong>
                    <small>Proveedor y condiciones</small>
                </div>
            </article>

            <article class="paso">
                <span class="paso__numero">2</span>

                <div>
                    <strong>Confirmar columnas</strong>
                    <small>Revisar hojas y encabezados</small>
                </div>
            </article>

            <article class="paso">
                <span class="paso__numero">3</span>

                <div>
                    <strong>Importar productos</strong>
                    <small>Guardar en la base de datos</small>
                </div>
            </article>

        </section>

        {{-- Errores generales devueltos por Laravel. --}}
        @if ($errors->any())
            <section class="alerta alerta--error">
                <strong>No se pudo continuar.</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Mensajes correctos, por ejemplo al cancelar una importación. --}}
        @if (session('success'))
            <section class="alerta alerta--exito">
                {{ session('success') }}
            </section>
        @endif

        <form
            action="{{ route('catalogos.importar.analizar') }}"
            method="POST"
            enctype="multipart/form-data"
            id="formulario-importacion"
            class="formulario"
        >
            @csrf

            <section class="tarjeta">

                <div class="tarjeta__encabezado">
                    <div>
                        <span class="tarjeta__numero">01</span>

                        <div>
                            <h2>Datos del catálogo</h2>

                            <p>
                                Identificá al proveedor y la lista
                                de precios recibida.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="campos-grid">

                    {{-- Proveedor al que pertenece el Excel. --}}
                    <div class="campo campo--completo">
                        <label for="idproveedor">
                            Proveedor
                            <span class="obligatorio">*</span>
                        </label>

                        <div class="selector-proveedor">
                            <select
                                id="idproveedor"
                                name="idproveedor"
                                required
                            >
                                <option value="">
                                    Seleccionar proveedor
                                </option>

                                @foreach ($proveedores as $proveedor)
                                    <option
                                        value="{{ $proveedor->idproveedor }}"
                                        @selected(
                                            (string) old(
                                                'idproveedor',
                                                $idProveedorPreseleccionado ?? ''
                                            )
                                            === (string) $proveedor->idproveedor
                                        )
                                    >
                                        {{ $proveedor->razonsocial }}
                                    </option>
                                @endforeach
                            </select>

                            @if ($puedeCrearProveedores)
                                <button
                                    type="button"
                                    id="abrir-modal-proveedor"
                                    class="boton-nuevo-proveedor"
                                >
                                    <span aria-hidden="true">+</span>
                                    Nuevo proveedor
                                </button>
                            @endif
                        </div>

                        @if ($puedeCrearProveedores)
                            <small
                                id="mensaje-proveedor-agregado"
                                class="mensaje-exito-campo"
                                role="status"
                                hidden
                            ></small>
                        @endif

                        @error('idproveedor')
                            <small class="mensaje-error">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                    {{-- Nombre interno con el que se reconocerá la lista. --}}
                    <div class="campo">
                        <label for="nombre_lista">
                            Nombre de la lista
                            <span class="obligatorio">*</span>
                        </label>

                        <input
                            type="text"
                            id="nombre_lista"
                            name="nombre_lista"
                            value="{{ old('nombre_lista') }}"
                            maxlength="100"
                            pattern="[\p{L}\p{N}\s.,()/_&amp;+\-º°#]+"
                            title="Usá letras, números, espacios y signos habituales."
                            autocomplete="off"
                            placeholder="Ejemplo: Lista Tersuave marzo 2026"
                            required
                        >

                        @error('nombre_lista')
                            <small class="mensaje-error">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                    {{-- Fecha hasta la que son válidos los precios. --}}
                    <div class="campo">
                        <label for="fecha_vigencia">
                            Fecha de vigencia
                        </label>

                        <input
                            type="date"
                            id="fecha_vigencia"
                            name="fecha_vigencia"
                            value="{{ old('fecha_vigencia') }}"
                        >

                        @error('fecha_vigencia')
                            <small class="mensaje-error">
                                {{ $message }}
                            </small>
                        @enderror

                        <small class="campo__ayuda">
                            Es opcional y corresponde a la vigencia general del catálogo.
                        </small>
                    </div>

                </div>
            </section>

            <section class="tarjeta">

                <div class="tarjeta__encabezado">
                    <div>
                        <span class="tarjeta__numero">02</span>

                        <div>
                            <h2>Condiciones comerciales</h2>

                            <p>
                                Configurá los descuentos generales
                                y la rentabilidad deseada.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="campos-grid campos-grid--cuatro">

                    <div class="campo">
                        <label for="descuento_caja">
                            Descuento por caja
                        </label>

                        <div class="campo-porcentaje">
                            <input
                                type="number"
                                id="descuento_caja"
                                name="descuento_caja"
                                value="{{ old('descuento_caja', '0.00') }}"
                                min="0"
                                max="100"
                                step="0.01"
                                inputmode="decimal"
                                autocomplete="off"
                                title="Ingresá un número entre 0 y 100, con hasta dos decimales."
                            >

                            <span>%</span>
                        </div>

                        @error('descuento_caja')
                            <small class="mensaje-error">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                    <div class="campo">
                        <label for="descuento_volumen">
                            Descuento por volumen
                        </label>

                        <div class="campo-porcentaje">
                            <input
                                type="number"
                                id="descuento_volumen"
                                name="descuento_volumen"
                                value="{{ old('descuento_volumen', '0.00') }}"
                                min="0"
                                max="100"
                                step="0.01"
                                inputmode="decimal"
                                autocomplete="off"
                                title="Ingresá un número entre 0 y 100, con hasta dos decimales."
                            >

                            <span>%</span>
                        </div>

                        @error('descuento_volumen')
                            <small class="mensaje-error">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                    <div class="campo">
                        <label for="descuento_especial">
                            Descuento especial
                        </label>

                        <div class="campo-porcentaje">
                            <input
                                type="number"
                                id="descuento_especial"
                                name="descuento_especial"
                                value="{{ old('descuento_especial', '0.00') }}"
                                min="0"
                                max="100"
                                step="0.01"
                                inputmode="decimal"
                                autocomplete="off"
                                title="Ingresá un número entre 0 y 100, con hasta dos decimales."
                            >

                            <span>%</span>
                        </div>

                        @error('descuento_especial')
                            <small class="mensaje-error">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                    <div class="campo">
                        <label for="rentabilidad_predeterminada">
                            Rentabilidad predeterminada
                        </label>

                        <div class="campo-porcentaje">
                            <input
                                type="number"
                                id="rentabilidad_predeterminada"
                                name="rentabilidad_predeterminada"
                                value="{{ old(
                                    'rentabilidad_predeterminada',
                                    '50.00'
                                ) }}"
                                min="0"
                                max="100"
                                step="0.01"
                                inputmode="decimal"
                                autocomplete="off"
                                title="Ingresá un número entre 0 y 100, con hasta dos decimales."
                            >

                            <span>%</span>
                        </div>

                        @error('rentabilidad_predeterminada')
                            <small class="mensaje-error">
                                {{ $message }}
                            </small>
                        @enderror
                    </div>

                </div>

                <div class="opcion-iva">

                    {{-- El campo oculto envía 0 cuando no está marcado. --}}
                    <input
                        type="hidden"
                        name="precios_incluyen_iva"
                        value="0"
                    >

                    <input
                        type="checkbox"
                        id="precios_incluyen_iva"
                        name="precios_incluyen_iva"
                        value="1"
                        @checked(old('precios_incluyen_iva'))
                    >

                    <label for="precios_incluyen_iva">
                        <strong>
                            Los precios del catálogo ya incluyen IVA
                        </strong>

                        <small>
                            Activá esta opción solamente si el proveedor
                            confirmó que los importes ya contienen IVA.
                        </small>
                    </label>

                    @error('precios_incluyen_iva')
                        <small class="mensaje-error">
                            {{ $message }}
                        </small>
                    @enderror

                </div>

                <div class="informacion">
                    <strong>Descuento total</strong>

                    <p>
                        No es necesario ingresarlo manualmente.
                        El sistema lo calculará combinando los descuentos
                        de caja, volumen y especial.
                    </p>
                </div>

            </section>

            <section class="tarjeta">

                <div class="tarjeta__encabezado">
                    <div>
                        <span class="tarjeta__numero">03</span>

                        <div>
                            <h2>Archivo del proveedor</h2>

                            <p>
                                Se aceptan archivos Excel XLS y XLSX
                                de hasta 20 MB.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="selector-archivo">

                    <input
                        type="file"
                        id="archivo"
                        name="archivo"
                        accept=".xls,.xlsx"
                        data-tamano-maximo="20971520"
                        required
                    >

                    <label for="archivo" class="selector-archivo__zona">
                        <span class="selector-archivo__icono">
                            XLS
                        </span>

                        <strong>
                            Seleccionar archivo Excel
                        </strong>

                        <span id="nombre-archivo">
                            Ningún archivo seleccionado
                        </span>

                        <small>
                            El archivo todavía no se importará.
                            Primero se mostrarán sus hojas y columnas.
                        </small>
                    </label>

                    @error('archivo')
                        <small class="mensaje-error">
                            {{ $message }}
                        </small>
                    @enderror

                    <small
                        id="error-archivo-cliente"
                        class="mensaje-error"
                        hidden
                    ></small>

                </div>

            </section>

            <footer class="acciones">
                <a href="{{ url('/') }}" class="boton boton--secundario">
                    Cancelar
                </a>

                <button
                    type="submit"
                    id="boton-analizar"
                    class="boton boton--principal"
                >
                    Analizar archivo
                    <span aria-hidden="true">→</span>
                </button>
            </footer>

        </form>

    </main>

    @if ($puedeCrearProveedores)
    <div
        id="modal-proveedor"
        class="modal-confirmacion modal-proveedor"
        hidden
    >
        <button
            type="button"
            id="cerrar-modal-proveedor-fondo"
            class="modal-confirmacion__fondo"
            aria-label="Cerrar formulario de proveedor"
        ></button>

        <section
            class="modal-confirmacion__contenido modal-proveedor__contenido"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-modal-proveedor"
            aria-describedby="descripcion-modal-proveedor"
        >
            <header class="modal-proveedor__encabezado">
                <div>
                    <span
                        class="modal-confirmacion__icono modal-proveedor__icono"
                        aria-hidden="true"
                    >
                        +
                    </span>

                    <p class="modal-confirmacion__etiqueta">
                        Alta rápida
                    </p>

                    <h2 id="titulo-modal-proveedor">
                        Agregar nuevo proveedor
                    </h2>
                </div>

                <button
                    type="button"
                    id="cerrar-modal-proveedor"
                    class="modal-proveedor__cerrar"
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </header>

            <p
                id="descripcion-modal-proveedor"
                class="modal-confirmacion__mensaje"
            >
                Completá la razón social y, si los tenés disponibles,
                los datos de contacto del proveedor.
            </p>

            <form
                id="formulario-proveedor-rapido"
                action="{{ route('catalogos.importar.proveedores.guardar') }}"
                method="POST"
            >
                @csrf

                <div
                    id="error-general-proveedor"
                    class="alerta alerta--error modal-proveedor__error-general"
                    role="alert"
                    hidden
                ></div>

                <div class="modal-proveedor__campos">
                    <div class="campo campo--completo">
                        <label for="proveedor_razonsocial">
                            Razón social
                            <span class="obligatorio">*</span>
                        </label>

                        <input
                            type="text"
                            id="proveedor_razonsocial"
                            name="razonsocial"
                            maxlength="150"
                            autocomplete="organization"
                            placeholder="Ejemplo: Pinturas del Norte S.A."
                            required
                        >

                        <small
                            class="mensaje-error"
                            data-error-proveedor="razonsocial"
                            hidden
                        ></small>
                    </div>

                    <div class="campo">
                        <label for="proveedor_cuit">CUIT</label>

                        <input
                            type="text"
                            id="proveedor_cuit"
                            name="cuit"
                            maxlength="13"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="30-12345678-9"
                        >

                        <small
                            class="mensaje-error"
                            data-error-proveedor="cuit"
                            hidden
                        ></small>
                    </div>

                    <div class="campo">
                        <label for="proveedor_contacto">
                            Persona de contacto
                        </label>

                        <input
                            type="text"
                            id="proveedor_contacto"
                            name="contacto"
                            maxlength="100"
                            pattern="[\p{L}\p{M} ]+"
                            title="Ingresá solamente letras y espacios, incluyendo tildes y ñ."
                            aria-describedby="error-proveedor-contacto"
                            autocomplete="name"
                            placeholder="Nombre del contacto"
                        >

                        <small
                            id="error-proveedor-contacto"
                            class="mensaje-error"
                            data-error-proveedor="contacto"
                            aria-live="polite"
                            hidden
                        ></small>
                    </div>

                    <div class="campo">
                        <label for="proveedor_telefono">Teléfono</label>

                        <input
                            type="tel"
                            id="proveedor_telefono"
                            name="telefono"
                            maxlength="50"
                            inputmode="numeric"
                            pattern="[0-9]+"
                            title="Ingresá solamente números, sin espacios ni símbolos."
                            aria-describedby="error-proveedor-telefono"
                            autocomplete="tel"
                            placeholder="Ejemplo: 3794123456"
                        >

                        <small
                            id="error-proveedor-telefono"
                            class="mensaje-error"
                            data-error-proveedor="telefono"
                            aria-live="polite"
                            hidden
                        ></small>
                    </div>

                    <div class="campo">
                        <label for="proveedor_email">
                            Correo electrónico
                        </label>

                        <input
                            type="email"
                            id="proveedor_email"
                            name="email"
                            maxlength="150"
                            autocomplete="email"
                            placeholder="ventas@proveedor.com"
                        >

                        <small
                            class="mensaje-error"
                            data-error-proveedor="email"
                            hidden
                        ></small>
                    </div>

                    <div class="campo campo--completo">
                        <label for="proveedor_direccion">Dirección</label>

                        <input
                            type="text"
                            id="proveedor_direccion"
                            name="direccion"
                            maxlength="200"
                            autocomplete="street-address"
                            placeholder="Dirección comercial"
                        >

                        <small
                            class="mensaje-error"
                            data-error-proveedor="direccion"
                            hidden
                        ></small>
                    </div>
                </div>

                <p class="modal-proveedor__ayuda">
                    El proveedor se guardará como activo y quedará
                    seleccionado automáticamente.
                </p>

                <div class="modal-proveedor__acciones">
                    <button
                        type="button"
                        id="cancelar-proveedor"
                        class="boton boton--secundario"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        id="guardar-proveedor"
                        class="boton boton--principal"
                    >
                        Guardar y seleccionar
                    </button>
                </div>
            </form>
        </section>
    </div>
    @endif

    <div
        id="modal-confirmacion-iva"
        class="modal-confirmacion"
        hidden
    >
        <button
            type="button"
            class="modal-confirmacion__fondo"
            id="cerrar-modal-fondo"
            aria-label="Cerrar confirmación"
        ></button>

        <section
            class="modal-confirmacion__contenido"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-confirmacion-iva"
            aria-describedby="mensaje-confirmacion-iva"
        >
            <span class="modal-confirmacion__icono" aria-hidden="true">
                !
            </span>

            <p class="modal-confirmacion__etiqueta">
                Verificación de precios
            </p>

            <h2 id="titulo-confirmacion-iva">
                Confirmar configuración del IVA
            </h2>

            <p
                id="mensaje-confirmacion-iva"
                class="modal-confirmacion__mensaje"
            ></p>

            <div class="modal-confirmacion__seleccion">
                <span>Configuración seleccionada</span>
                <strong id="estado-confirmacion-iva"></strong>
            </div>

            <p class="modal-confirmacion__advertencia">
                Esta decisión se utilizará posteriormente para calcular
                los precios de venta de los productos.
            </p>

            <div class="modal-confirmacion__acciones">
                <button
                    type="button"
                    id="volver-revisar-iva"
                    class="boton boton--secundario"
                >
                    Volver a revisar
                </button>

                <button
                    type="button"
                    id="confirmar-analisis"
                    class="boton boton--principal"
                >
                    Sí, analizar archivo
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </section>
    </div>

    <script>
        @if ($puedeCrearProveedores)
        const selectorProveedor = document.getElementById('idproveedor');
        const modalProveedor = document.getElementById('modal-proveedor');
        const formularioProveedor = document.getElementById(
            'formulario-proveedor-rapido'
        );

        const botonAbrirProveedor = document.getElementById(
            'abrir-modal-proveedor'
        );

        const botonCerrarProveedor = document.getElementById(
            'cerrar-modal-proveedor'
        );

        const botonCerrarProveedorFondo = document.getElementById(
            'cerrar-modal-proveedor-fondo'
        );

        const botonCancelarProveedor = document.getElementById(
            'cancelar-proveedor'
        );

        const botonGuardarProveedor = document.getElementById(
            'guardar-proveedor'
        );

        const campoRazonSocial = document.getElementById(
            'proveedor_razonsocial'
        );

        const campoCuitProveedor = document.getElementById('proveedor_cuit');
        const errorGeneralProveedor = document.getElementById(
            'error-general-proveedor'
        );

        const mensajeProveedorAgregado = document.getElementById(
            'mensaje-proveedor-agregado'
        );

        let guardandoProveedor = false;

        function configurarCampoProveedor(campo, caracteresPermitidos, comprobar) {
            const mensaje = formularioProveedor.querySelector(
                `[data-error-proveedor="${campo.name}"]`
            );

            function actualizar() {
                const error = comprobar(campo.value);
                campo.setCustomValidity(error);
                mensaje.textContent = error;
                mensaje.hidden = error === '';

                if (error) {
                    campo.setAttribute('aria-invalid', 'true');
                } else {
                    campo.removeAttribute('aria-invalid');
                }

                return error === '';
            }

            campo.addEventListener('beforeinput', function (evento) {
                if (
                    !evento.isComposing
                    && evento.data
                    && !caracteresPermitidos.test(evento.data)
                ) {
                    evento.preventDefault();
                }
            });

            campo.addEventListener('paste', function (evento) {
                const texto = evento.clipboardData?.getData('text');
                if (texto && !caracteresPermitidos.test(texto)) {
                    evento.preventDefault();
                }
            });

            campo.addEventListener('input', function (evento) {
                if (!evento.isComposing) actualizar();
            });
            campo.addEventListener('compositionend', actualizar);
            campo.addEventListener('invalid', actualizar);
            campo.addEventListener('blur', function () {
                campo.value = campo.value.trim();
                actualizar();
            });

            return actualizar;
        }

        const validarContactoProveedor = configurarCampoProveedor(
            document.getElementById('proveedor_contacto'),
            /^[\p{L}\p{M} ]*$/u,
            function (valor) {
                const contacto = valor.trim();
                if (contacto === '') return '';
                if (!/^\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)*$/u.test(contacto)) {
                    return 'Ingresá solamente letras y espacios, incluyendo tildes y ñ.';
                }
                return contacto.length > 100
                    ? 'La persona de contacto no puede superar los 100 caracteres.'
                    : '';
            }
        );

        const validarTelefonoProveedor = configurarCampoProveedor(
            document.getElementById('proveedor_telefono'),
            /^[0-9]*$/,
            function (valor) {
                if (valor === '') return '';
                if (!/^[0-9]+$/.test(valor)) {
                    return 'Ingresá solamente números, sin espacios, letras ni símbolos.';
                }
                return valor.length > 50
                    ? 'El teléfono no puede superar los 50 dígitos.'
                    : '';
            }
        );

        function limpiarErroresProveedor() {
            errorGeneralProveedor.textContent = '';
            errorGeneralProveedor.hidden = true;

            formularioProveedor
                .querySelectorAll('[data-error-proveedor]')
                .forEach(function (mensaje) {
                    mensaje.textContent = '';
                    mensaje.hidden = true;
                });

            formularioProveedor
                .querySelectorAll('input')
                .forEach(function (campo) {
                    campo.removeAttribute('aria-invalid');
                    campo.setCustomValidity('');
                });
        }

        function abrirModalProveedor() {
            limpiarErroresProveedor();
            mensajeProveedorAgregado.hidden = true;
            modalProveedor.hidden = false;
            document.body.classList.add('modal-abierto');
            campoRazonSocial.focus();
        }

        function cerrarModalProveedor(forzar = false) {
            if (guardandoProveedor && !forzar) {
                return;
            }

            modalProveedor.hidden = true;
            document.body.classList.remove('modal-abierto');
            formularioProveedor.reset();
            limpiarErroresProveedor();
            botonAbrirProveedor.focus();
        }

        function mostrarErroresProveedor(errores) {
            Object.entries(errores).forEach(function ([campo, mensajes]) {
                const mensaje = formularioProveedor.querySelector(
                    `[data-error-proveedor="${campo}"]`
                );

                const entrada = formularioProveedor.elements.namedItem(campo);

                if (mensaje) {
                    mensaje.textContent = mensajes[0];
                    mensaje.hidden = false;
                }

                if (entrada) {
                    entrada.setAttribute('aria-invalid', 'true');
                }
            });

            const primerCampoInvalido = formularioProveedor.querySelector(
                '[aria-invalid="true"]'
            );

            if (primerCampoInvalido) {
                primerCampoInvalido.focus();
            }
        }

        botonAbrirProveedor.addEventListener('click', abrirModalProveedor);
        botonCerrarProveedor.addEventListener('click', function () {
            cerrarModalProveedor();
        });
        botonCerrarProveedorFondo.addEventListener(
            'click',
            function () {
                cerrarModalProveedor();
            }
        );
        botonCancelarProveedor.addEventListener('click', function () {
            cerrarModalProveedor();
        });

        campoCuitProveedor.addEventListener('input', function () {
            const digitos = this.value.replace(/\D/g, '').slice(0, 11);
            let cuitFormateado = digitos.slice(0, 2);

            if (digitos.length > 2) {
                cuitFormateado += '-' + digitos.slice(2, 10);
            }

            if (digitos.length > 10) {
                cuitFormateado += '-' + digitos.slice(10, 11);
            }

            this.value = cuitFormateado;
        });

        formularioProveedor.addEventListener('submit', async function (evento) {
            evento.preventDefault();

            if (guardandoProveedor) {
                return;
            }

            limpiarErroresProveedor();
            const camposValidos = [
                validarContactoProveedor(),
                validarTelefonoProveedor(),
            ];
            const formularioValido = formularioProveedor.reportValidity();
            if (camposValidos.includes(false) || !formularioValido) {
                return;
            }

            guardandoProveedor = true;
            botonGuardarProveedor.disabled = true;
            botonGuardarProveedor.textContent = 'Guardando...';

            try {
                const respuesta = await fetch(formularioProveedor.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(formularioProveedor),
                });

                const resultado = await respuesta.json().catch(function () {
                    return {};
                });

                if (respuesta.status === 422) {
                    mostrarErroresProveedor(resultado.errors || {});
                    return;
                }

                if (!respuesta.ok || !resultado.proveedor) {
                    throw new Error(
                        resultado.message
                        || 'No se pudo guardar el proveedor.'
                    );
                }

                const proveedor = resultado.proveedor;
                const opcion = new Option(
                    proveedor.razonsocial,
                    proveedor.idproveedor,
                    true,
                    true
                );

                selectorProveedor.add(opcion);
                selectorProveedor.dispatchEvent(
                    new Event('change', { bubbles: true })
                );

                guardandoProveedor = false;
                cerrarModalProveedor(true);

                mensajeProveedorAgregado.textContent =
                    `Proveedor “${proveedor.razonsocial}” agregado y seleccionado.`;
                mensajeProveedorAgregado.hidden = false;
                selectorProveedor.focus();
            } catch (error) {
                errorGeneralProveedor.textContent = error.message;
                errorGeneralProveedor.hidden = false;
            } finally {
                guardandoProveedor = false;
                botonGuardarProveedor.disabled = false;
                botonGuardarProveedor.textContent = 'Guardar y seleccionar';
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !modalProveedor.hidden) {
                cerrarModalProveedor();
            }
        });
        @endif

        const formularioImportacion = document.getElementById(
            'formulario-importacion'
        );

        const opcionPreciosConIva = document.getElementById(
            'precios_incluyen_iva'
        );

        const modalConfirmacionIva = document.getElementById(
            'modal-confirmacion-iva'
        );

        const mensajeConfirmacionIva = document.getElementById(
            'mensaje-confirmacion-iva'
        );

        const estadoConfirmacionIva = document.getElementById(
            'estado-confirmacion-iva'
        );

        const botonAnalizar = document.getElementById('boton-analizar');
        const botonVolverIva = document.getElementById('volver-revisar-iva');
        const botonConfirmarAnalisis = document.getElementById(
            'confirmar-analisis'
        );

        const botonCerrarFondo = document.getElementById(
            'cerrar-modal-fondo'
        );

        let analisisConfirmado = false;

        function abrirConfirmacionIva() {
            const preciosIncluyenIva = opcionPreciosConIva.checked;

            if (preciosIncluyenIva) {
                estadoConfirmacionIva.textContent =
                    'Los precios ya incluyen IVA';

                estadoConfirmacionIva.className =
                    'modal-confirmacion__estado modal-confirmacion__estado--incluido';

                mensajeConfirmacionIva.textContent =
                    'Confirmá que el proveedor informó que los importes del catálogo ya contienen IVA. El sistema no volverá a agregarlo al calcular el precio de venta.';
            } else {
                estadoConfirmacionIva.textContent =
                    'Los precios no incluyen IVA';

                estadoConfirmacionIva.className =
                    'modal-confirmacion__estado modal-confirmacion__estado--no-incluido';

                mensajeConfirmacionIva.textContent =
                    'Confirmá que los importes del catálogo no contienen IVA. El sistema agregará la alícuota seleccionada al calcular el precio de venta.';
            }

            modalConfirmacionIva.hidden = false;
            document.body.classList.add('modal-abierto');
            botonConfirmarAnalisis.focus();
        }

        function cerrarConfirmacionIva() {
            modalConfirmacionIva.hidden = true;
            document.body.classList.remove('modal-abierto');
            botonAnalizar.focus();
        }

        formularioImportacion.addEventListener('submit', function (evento) {
            if (analisisConfirmado) {
                return;
            }

            evento.preventDefault();
            abrirConfirmacionIva();
        });

        botonConfirmarAnalisis.addEventListener('click', function () {
            analisisConfirmado = true;
            modalConfirmacionIva.hidden = true;
            document.body.classList.remove('modal-abierto');
            formularioImportacion.requestSubmit();
        });

        botonVolverIva.addEventListener('click', cerrarConfirmacionIva);
        botonCerrarFondo.addEventListener('click', cerrarConfirmacionIva);

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !modalConfirmacionIva.hidden) {
                cerrarConfirmacionIva();
            }
        });

        const inputArchivo = document.getElementById('archivo');
        const nombreArchivo = document.getElementById('nombre-archivo');
        const errorArchivoCliente = document.getElementById(
            'error-archivo-cliente'
        );

        const extensionesPermitidas = ['xls', 'xlsx'];
        const tamanoMaximoArchivo = Number(
            inputArchivo.dataset.tamanoMaximo
        );

        /*
         * Muestra o limpia el mensaje de validación del archivo.
         */
        function mostrarErrorArchivo(mensaje = '') {
            inputArchivo.setCustomValidity(mensaje);
            errorArchivoCliente.textContent = mensaje;
            errorArchivoCliente.hidden = mensaje === '';
        }

        inputArchivo.addEventListener('change', function () {
            mostrarErrorArchivo();

            if (this.files.length === 0) {
                nombreArchivo.textContent =
                    'Ningún archivo seleccionado';

                return;
            }

            const archivoSeleccionado = this.files[0];
            const partesNombre = archivoSeleccionado.name
                .toLowerCase()
                .split('.');

            const extension = partesNombre.length > 1
                ? partesNombre.pop()
                : '';

            if (!extensionesPermitidas.includes(extension)) {
                mostrarErrorArchivo(
                    'El archivo debe tener formato XLS o XLSX.'
                );

                this.value = '';
                nombreArchivo.textContent =
                    'Ningún archivo seleccionado';

                return;
            }

            if (archivoSeleccionado.size > tamanoMaximoArchivo) {
                mostrarErrorArchivo(
                    'El archivo no puede superar los 20 MB.'
                );

                this.value = '';
                nombreArchivo.textContent =
                    'Ningún archivo seleccionado';

                return;
            }

            nombreArchivo.textContent = archivoSeleccionado.name;
        });

        /*
         * Evita notación científica, signos negativos y valores
         * con más de dos decimales en los campos de porcentaje.
         * Laravel vuelve a validar todo al recibir el formulario.
         */
        const camposPorcentaje = document.querySelectorAll(
            '.campo-porcentaje input[type="number"]'
        );

        camposPorcentaje.forEach(function (campo) {
            campo.addEventListener('keydown', function (evento) {
                const teclasNoPermitidas = ['e', 'E', '+', '-'];

                if (teclasNoPermitidas.includes(evento.key)) {
                    evento.preventDefault();
                }
            });

            campo.addEventListener('input', function () {
                this.setCustomValidity('');

                if (this.value === '') {
                    return;
                }

                const formatoValido = /^\d{1,3}(\.\d{1,2})?$/.test(
                    this.value
                );

                const valorNumerico = Number(this.value);

                if (!formatoValido) {
                    this.setCustomValidity(
                        'Ingresá un número con hasta dos decimales.'
                    );

                    return;
                }

                if (valorNumerico < 0 || valorNumerico > 100) {
                    this.setCustomValidity(
                        'El valor debe estar entre 0 y 100.'
                    );
                }
            });
        });
    </script>

</body>
</html>
