<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar producto | Messirve</title>

    <link
        rel="stylesheet"
        href="{{ asset('css/productos/catalogo.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/productos/editar.css') }}"
    >
</head>

<body>

    @php
        $marcaSeleccionada = (string) old(
            'idmarca',
            $producto->idmarca
        );

        $lineaSeleccionada = (string) old(
            'idlinea',
            $idLineaActual
        );

        $grupoSeleccionado = (string) old(
            'idgrupo',
            $idGrupoActual
        );

        $subgrupoSeleccionado = (string) old(
            'idsubgrupo',
            $producto->idsubgrupo
        );

        $contenidoGuardado = (string) $producto->contenido_neto;

        // Quitamos ceros decimales, sin convertir un entero como 20 en 2.
        if (str_contains($contenidoGuardado, '.')) {
            $contenidoGuardado = rtrim(rtrim($contenidoGuardado, '0'), '.');
        }

        $eliminarImagenSeleccionado =
            (string) old('eliminar_imagen', '0') === '1';
    @endphp

    <main class="pagina-productos pagina-edicion">

        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">
                    Administración de productos
                </p>

                <h1>Editar producto</h1>

                <p class="encabezado-principal__descripcion">
                    Corregí los datos internos sin modificar el código MES,
                    el stock ni los precios originales de los proveedores.
                </p>
            </div>

            <div class="encabezado-principal__acciones">
                <a
                    href="{{ route('productos.index', [
                        'buscar' => $producto->codigointerno,
                        'estado' => 'todos',
                    ]) }}"
                    class="boton boton--secundario"
                >
                    ← Volver al listado
                </a>
            </div>
        </header>

        @if ($errors->any())
            <section class="alerta alerta--error" role="alert">
                <strong>No se pudieron guardar los cambios.</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="resumen-edicion">
            <article>
                <span>Código interno</span>
                <strong>{{ $producto->codigointerno }}</strong>
                <small>Es generado por el sistema y no puede modificarse.</small>
            </article>

            <article>
                <span>Identificador</span>
                <strong>#{{ $producto->idproducto }}</strong>
                <small>Referencia interna de la base de datos.</small>
            </article>

            <article class="resumen-edicion__aviso">
                <span class="resumen-edicion__icono">i</span>

                <div>
                    <strong>El stock se administra por separado</strong>
                    <small>
                        Esta pantalla no cambia cantidades ni sucursales.
                    </small>
                </div>
            </article>
        </section>

        <form
            action="{{ route('productos.actualizar', $producto) }}"
            method="POST"
            enctype="multipart/form-data"
            id="formulario-edicion-producto"
            class="formulario-edicion"
        >
            @csrf
            @method('PUT')

            <section class="panel">
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">01</span>

                        <div>
                            <h2>Información principal</h2>
                            <p>Datos utilizados para identificar el producto.</p>
                        </div>
                    </div>
                </div>

                <div class="campos-edicion">
                    <div class="campo-edicion campo-edicion--completo">
                        <label for="descripcion">
                            Descripción interna
                            <span class="obligatorio">*</span>
                        </label>

                        <input
                            type="text"
                            id="descripcion"
                            name="descripcion"
                            value="{{ old('descripcion', $producto->descripcion) }}"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="campo-edicion">
                        <label for="codigofabricante">
                            Código del fabricante
                        </label>

                        <input
                            type="text"
                            id="codigofabricante"
                            name="codigofabricante"
                            value="{{ old(
                                'codigofabricante',
                                $producto->codigofabricante
                            ) }}"
                            maxlength="50"
                        >
                    </div>

                    <div class="campo-edicion">
                        <label for="codigobarra">Código de barras</label>

                        <input
                            type="text"
                            id="codigobarra"
                            name="codigobarra"
                            value="{{ old('codigobarra', $producto->codigobarra) }}"
                            maxlength="50"
                            inputmode="numeric"
                            pattern="[0-9]+"
                            title="Ingresá solamente números, sin espacios ni símbolos."
                            aria-describedby="ayuda-codigobarra error-codigobarra"
                        >

                        <small id="ayuda-codigobarra">
                            Solo números. Se conservan los ceros iniciales.
                        </small>
                        <small
                            id="error-codigobarra"
                            class="mensaje-error"
                            aria-live="polite"
                            @if (!$errors->has('codigobarra')) hidden @endif
                        >{{ $errors->first('codigobarra') }}</small>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">02</span>

                        <div>
                            <h2>Marca y clasificación</h2>
                            <p>Respetá la jerarquía línea, grupo y subgrupo.</p>
                        </div>
                    </div>
                </div>

                <div class="campos-edicion">
                    <div class="campo-edicion">
                        <label for="idmarca">
                            Marca
                            <span class="obligatorio">*</span>
                        </label>

                        <select id="idmarca" name="idmarca" required>
                            <option value="">Seleccionar marca</option>

                            @foreach ($marcas as $marca)
                                <option
                                    value="{{ $marca->idmarca }}"
                                    @selected(
                                        $marcaSeleccionada
                                        === (string) $marca->idmarca
                                    )
                                >
                                    {{ $marca->nombre }}
                                    @if (!$marca->estado)
                                        — Inactiva
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="campo-edicion">
                        <label for="idlinea">
                            Línea
                            <span class="obligatorio">*</span>
                        </label>

                        <select id="idlinea" name="idlinea" required>
                            <option value="">Seleccionar línea</option>

                            @foreach ($lineas as $linea)
                                <option
                                    value="{{ $linea->idlinea }}"
                                    @selected(
                                        $lineaSeleccionada
                                        === (string) $linea->idlinea
                                    )
                                >
                                    {{ $linea->nombre }}
                                    @if (!$linea->estado)
                                        — Inactiva
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="campo-edicion">
                        <label for="idgrupo">
                            Grupo
                            <span class="obligatorio">*</span>
                        </label>

                        <select id="idgrupo" name="idgrupo" required>
                            <option value="">Seleccionar grupo</option>

                            @foreach ($grupos as $grupo)
                                <option
                                    value="{{ $grupo->idgrupo }}"
                                    data-idlinea="{{ $grupo->idlinea }}"
                                    @selected(
                                        $grupoSeleccionado
                                        === (string) $grupo->idgrupo
                                    )
                                >
                                    {{ $grupo->nombre }}
                                    @if (!$grupo->estado)
                                        — Inactivo
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="campo-edicion">
                        <label for="idsubgrupo">
                            Subgrupo
                            <span class="obligatorio">*</span>
                        </label>

                        <select id="idsubgrupo" name="idsubgrupo" required>
                            <option value="">Seleccionar subgrupo</option>

                            @foreach ($subgrupos as $subgrupo)
                                <option
                                    value="{{ $subgrupo->idsubgrupo }}"
                                    data-idgrupo="{{ $subgrupo->idgrupo }}"
                                    @selected(
                                        $subgrupoSeleccionado
                                        === (string) $subgrupo->idsubgrupo
                                    )
                                >
                                    {{ $subgrupo->nombre }}
                                    @if (!$subgrupo->estado)
                                        — Inactivo
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">03</span>

                        <div>
                            <h2>Presentación e impuestos</h2>
                            <p>Contenido físico y alícuota correspondiente.</p>
                        </div>
                    </div>
                </div>

                <div class="campos-edicion">
                    <div class="campo-edicion">
                        <label for="idiva">
                            Alícuota de IVA
                            <span class="obligatorio">*</span>
                        </label>

                        <select id="idiva" name="idiva" required>
                            <option value="">Seleccionar IVA</option>

                            @foreach ($alicuotasIva as $iva)
                                <option
                                    value="{{ $iva->idiva }}"
                                    @selected(
                                        (string) old('idiva', $producto->idiva)
                                        === (string) $iva->idiva
                                    )
                                >
                                    {{ $iva->nombre }} —
                                    {{ number_format(
                                        (float) $iva->porcentaje,
                                        2,
                                        ',',
                                        '.'
                                    ) }}%
                                    @if (!$iva->estado)
                                        — Inactiva
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="campo-edicion">
                        <label for="idunidad">
                            Unidad de medida
                            <span class="obligatorio">*</span>
                        </label>

                        <select id="idunidad" name="idunidad" required>
                            <option value="">Seleccionar unidad</option>

                            @foreach ($unidades as $unidad)
                                <option
                                    value="{{ $unidad->idunidad }}"
                                    @selected(
                                        (string) old(
                                            'idunidad',
                                            $producto->idunidad
                                        ) === (string) $unidad->idunidad
                                    )
                                >
                                    {{ $unidad->nombre }}
                                    ({{ $unidad->abreviatura }})
                                    @if (!$unidad->estado)
                                        — Inactiva
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="campo-edicion">
                        <label for="contenido_neto">Contenido neto</label>

                        <input
                            type="text"
                            id="contenido_neto"
                            name="contenido_neto"
                            value="{{ old('contenido_neto', $contenidoGuardado) }}"
                            maxlength="20"
                            pattern="(?:[0-9]+(?:[.,][0-9]{1,3})?|[0-9]+[ \t]*/[ \t]*[0-9]+)"
                            title="Usá una cantidad positiva: 4, 0,25, 0.25 o 1/4."
                            aria-describedby="ayuda-contenido-neto error-contenido_neto"
                            placeholder="Ejemplo: 1/4, 1/2, 1 o 4"
                        >

                        <small id="ayuda-contenido-neto">
                            Cantidad mayor que cero. Hasta 3 decimales o una
                            fracción como 1/4; elegí la unidad en el selector.
                        </small>
                        <small
                            id="error-contenido_neto"
                            class="mensaje-error"
                            aria-live="polite"
                            @if (!$errors->has('contenido_neto')) hidden @endif
                        >{{ $errors->first('contenido_neto') }}</small>
                    </div>

                    <div class="campo-edicion">
                        <label for="color">Color</label>

                        <input
                            type="text"
                            id="color"
                            name="color"
                            value="{{ old('color', $producto->color) }}"
                            maxlength="80"
                            placeholder="Ejemplo: Blanco"
                        >
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">04</span>

                        <div>
                            <h2>Precio y rentabilidad</h2>
                            <p>Valores comerciales definidos por Messirve.</p>
                        </div>
                    </div>
                </div>

                <div class="campos-edicion">
                    <div class="campo-edicion">
                        <label for="rentabilidadobjetivo">
                            Rentabilidad objetivo
                            <span class="obligatorio">*</span>
                        </label>

                        <div class="campo-con-sufijo">
                            <input
                                type="number"
                                id="rentabilidadobjetivo"
                                name="rentabilidadobjetivo"
                                value="{{ old(
                                    'rentabilidadobjetivo',
                                    $producto->rentabilidadobjetivo
                                ) }}"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                            >
                            <span>%</span>
                        </div>
                    </div>

                    <div class="campo-edicion">
                        <label for="pvp">
                            Precio final de venta
                            <span class="obligatorio">*</span>
                        </label>

                        <div class="campo-con-sufijo campo-con-sufijo--moneda">
                            <span>$</span>
                            <input
                                type="number"
                                id="pvp"
                                name="pvp"
                                value="{{ old('pvp', $producto->pvp) }}"
                                min="0"
                                max="9999999999.99"
                                step="0.01"
                                required
                            >
                        </div>
                    </div>
                </div>

                <p class="aviso-precio">
                    La edición del PVP no cambia el precio de lista guardado
                    en los catálogos de los proveedores.
                </p>
            </section>

            <section class="panel">
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">05</span>

                        <div>
                            <h2>Imagen del producto</h2>
                            <p>
                                Agregá una imagen, reemplazá la actual o
                                eliminála si ya no corresponde.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="carga-imagen-producto">
                    <div class="selector-imagen-producto">
                        <input
                            type="file"
                            id="imagen"
                            name="imagen"
                            accept="image/jpeg,image/png,image/webp"
                            class="selector-imagen-producto__input"
                        >

                        <label
                            for="imagen"
                            class="selector-imagen-producto__boton"
                        >
                            {{ $producto->rutaimagen
                                ? 'Reemplazar imagen'
                                : 'Seleccionar imagen' }}
                        </label>

                        <strong id="nombre-imagen">
                            {{ $producto->rutaimagen
                                ? 'Imagen actual del producto'
                                : 'Ninguna imagen seleccionada' }}
                        </strong>

                        <small>
                            Formatos JPG, PNG o WEBP. Tamaño máximo: 2 MB.
                            Si no elegís otra imagen, se conservará la actual.
                        </small>

                        @error('imagen')
                            <span class="mensaje-error">
                                {{ $message }}
                            </span>
                        @enderror

                        <input
                            type="hidden"
                            name="eliminar_imagen"
                            value="0"
                        >

                        @if ($producto->rutaimagen)
                            <label class="opcion-eliminar-imagen">
                                <input
                                    type="checkbox"
                                    id="eliminar_imagen"
                                    name="eliminar_imagen"
                                    value="1"
                                    @checked($eliminarImagenSeleccionado)
                                >

                                <span>
                                    <strong>Eliminar imagen actual</strong>
                                    <small>
                                        Se eliminará cuando guardes los cambios.
                                    </small>
                                </span>
                            </label>
                        @endif
                    </div>

                    <div
                        class="vista-previa-imagen {{
                            !$producto->rutaimagen
                            || $eliminarImagenSeleccionado
                                ? 'vista-previa-imagen--vacia'
                                : ''
                        }}"
                        id="vista-previa-imagen"
                        data-imagen-actual="{{ $producto->rutaimagen
                            ? asset('storage/' . $producto->rutaimagen)
                            : '' }}"
                    >
                        <img
                            id="imagen-previsualizada"
                            src="{{ $producto->rutaimagen
                                ? asset('storage/' . $producto->rutaimagen)
                                : '' }}"
                            alt="Imagen de {{ $producto->descripcion }}"
                            @if (
                                !$producto->rutaimagen
                                || $eliminarImagenSeleccionado
                            )
                                hidden
                            @endif
                        >

                        <div
                            id="imagen-sin-vista-previa"
                            @if (
                                $producto->rutaimagen
                                && !$eliminarImagenSeleccionado
                            )
                                hidden
                            @endif
                        >
                            <span aria-hidden="true">IMG</span>
                            <strong id="mensaje-imagen-vacia">
                                {{ $eliminarImagenSeleccionado
                                    ? 'La imagen actual se eliminará'
                                    : 'Este producto no tiene imagen' }}
                            </strong>
                            <small id="ayuda-imagen-vacia">
                                {{ $eliminarImagenSeleccionado
                                    ? 'Guardá los cambios para confirmar.'
                                    : 'Podés seleccionar una desde este formulario.' }}
                            </small>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">06</span>

                        <div>
                            <h2>Publicación y estado</h2>
                            <p>Controlá su presencia en la tienda y su vigencia.</p>
                        </div>
                    </div>
                </div>

                <div class="opciones-edicion">
                    <label class="opcion-edicion">
                        <input type="hidden" name="visibleweb" value="0">
                        <input
                            type="checkbox"
                            name="visibleweb"
                            value="1"
                            @checked(
                                (string) old(
                                    'visibleweb',
                                    $producto->visibleweb ? '1' : '0'
                                ) === '1'
                            )
                        >

                        <span>
                            <strong>Visible en la tienda web</strong>
                            <small>Permite mostrarlo al público.</small>
                        </span>
                    </label>

                    <label class="opcion-edicion">
                        <input type="hidden" name="destacado" value="0">
                        <input
                            type="checkbox"
                            name="destacado"
                            value="1"
                            @checked(
                                (string) old(
                                    'destacado',
                                    $producto->destacado ? '1' : '0'
                                ) === '1'
                            )
                        >

                        <span>
                            <strong>Producto destacado</strong>
                            <small>Permite resaltarlo en la página principal.</small>
                        </span>
                    </label>

                    <div class="campo-edicion opcion-edicion--estado">
                        <label for="estado">
                            Estado del producto
                            <span class="obligatorio">*</span>
                        </label>

                        <select id="estado" name="estado" required>
                            <option
                                value="1"
                                @selected(
                                    (string) old(
                                        'estado',
                                        $producto->estado ? '1' : '0'
                                    ) === '1'
                                )
                            >
                                Activo
                            </option>

                            <option
                                value="0"
                                @selected(
                                    (string) old(
                                        'estado',
                                        $producto->estado ? '1' : '0'
                                    ) === '0'
                                )
                            >
                                Inactivo
                            </option>
                        </select>

                        <small>
                            Inactivar conserva el historial y no elimina registros.
                        </small>
                    </div>
                </div>
            </section>

            <div class="acciones-edicion">
                <a
                    href="{{ route('productos.index', [
                        'buscar' => $producto->codigointerno,
                        'estado' => 'todos',
                    ]) }}"
                    class="boton boton--secundario"
                >
                    Cancelar
                </a>

                <button type="submit" class="boton boton--primario">
                    Guardar cambios
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </form>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formularioProducto = document.getElementById(
                'formulario-edicion-producto'
            );

            // Bloquea caracteres ajenos al campo y valida también pegado/autocompletado.
            function configurarValidacion(campo, caracteresPermitidos, comprobar) {
                const mensaje = document.getElementById('error-' + campo.id);

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

            const validarCodigoBarra = configurarValidacion(
                document.getElementById('codigobarra'),
                /^[0-9]*$/,
                function (valor) {
                    if (valor === '') return '';
                    if (!/^[0-9]+$/.test(valor)) {
                        return 'Ingresá solamente números, sin espacios ni símbolos.';
                    }
                    return valor.length > 50
                        ? 'El código de barras no puede superar los 50 dígitos.'
                        : '';
                }
            );

            const validarContenidoNeto = configurarValidacion(
                document.getElementById('contenido_neto'),
                /^[0-9.,/ \t]*$/,
                function (valor) {
                    const texto = valor.trim();
                    if (texto === '') return '';
                    if (texto.length > 20) {
                        return 'El contenido neto no puede superar los 20 caracteres.';
                    }
                    if (!/^(?:[0-9]+(?:[.,][0-9]{1,3})?|[0-9]+[ \t]*\/[ \t]*[0-9]+)$/.test(texto)) {
                        return 'Usá hasta 3 decimales o una fracción como 1/4, sin letras ni separadores de miles.';
                    }

                    let cantidad;
                    if (texto.includes('/')) {
                        const [numerador, denominador] = texto.split('/').map(Number);
                        if (denominador === 0) {
                            return 'El denominador de la fracción no puede ser cero.';
                        }
                        cantidad = Math.round((numerador / denominador) * 1000) / 1000;
                    } else {
                        cantidad = Number(texto.replace(',', '.'));
                    }

                    if (!Number.isFinite(cantidad) || cantidad > 9999999.999) {
                        return 'El contenido neto no puede superar 9999999,999.';
                    }
                    if (cantidad < 0.001) {
                        return 'Ingresá una cantidad mayor que cero, de al menos 0,001 de la unidad seleccionada.';
                    }
                    return '';
                }
            );

            formularioProducto.addEventListener('submit', function (evento) {
                const resultados = [validarCodigoBarra(), validarContenidoNeto()];
                if (resultados.includes(false)) {
                    evento.preventDefault();
                    formularioProducto.reportValidity();
                }
            });

            const selectLinea = document.getElementById('idlinea');
            const selectGrupo = document.getElementById('idgrupo');
            const selectSubgrupo = document.getElementById('idsubgrupo');

            const inputImagen = document.getElementById('imagen');
            const checkboxEliminarImagen = document.getElementById(
                'eliminar_imagen'
            );
            const nombreImagen = document.getElementById('nombre-imagen');
            const contenedorVistaPrevia = document.getElementById(
                'vista-previa-imagen'
            );
            const imagenPrevisualizada = document.getElementById(
                'imagen-previsualizada'
            );
            const imagenSinVistaPrevia = document.getElementById(
                'imagen-sin-vista-previa'
            );
            const mensajeImagenVacia = document.getElementById(
                'mensaje-imagen-vacia'
            );
            const ayudaImagenVacia = document.getElementById(
                'ayuda-imagen-vacia'
            );
            const urlImagenActual =
                contenedorVistaPrevia.dataset.imagenActual;
            const tiposImagenPermitidos = [
                'image/jpeg',
                'image/png',
                'image/webp',
            ];
            const pesoMaximoImagen = 2 * 1024 * 1024;
            let urlTemporalImagen = null;

            function liberarUrlTemporal() {
                if (urlTemporalImagen !== null) {
                    URL.revokeObjectURL(urlTemporalImagen);
                    urlTemporalImagen = null;
                }
            }

            function mostrarImagen(url, nombre) {
                imagenPrevisualizada.src = url;
                imagenPrevisualizada.hidden = false;
                imagenSinVistaPrevia.hidden = true;
                nombreImagen.textContent = nombre;
                contenedorVistaPrevia.classList.remove(
                    'vista-previa-imagen--vacia'
                );
            }

            function mostrarSinImagen(titulo, ayuda) {
                imagenPrevisualizada.removeAttribute('src');
                imagenPrevisualizada.hidden = true;
                imagenSinVistaPrevia.hidden = false;
                mensajeImagenVacia.textContent = titulo;
                ayudaImagenVacia.textContent = ayuda;
                contenedorVistaPrevia.classList.add(
                    'vista-previa-imagen--vacia'
                );
            }

            function restaurarEstadoImagen() {
                liberarUrlTemporal();

                if (
                    checkboxEliminarImagen
                    && checkboxEliminarImagen.checked
                ) {
                    nombreImagen.textContent =
                        'La imagen actual se eliminará';
                    mostrarSinImagen(
                        'La imagen actual se eliminará',
                        'Guardá los cambios para confirmar.'
                    );
                    return;
                }

                if (urlImagenActual !== '') {
                    mostrarImagen(
                        urlImagenActual,
                        'Imagen actual del producto'
                    );
                    return;
                }

                nombreImagen.textContent = 'Ninguna imagen seleccionada';
                mostrarSinImagen(
                    'Este producto no tiene imagen',
                    'Podés seleccionar una desde este formulario.'
                );
            }

            inputImagen.addEventListener('change', function () {
                const archivo = inputImagen.files[0];

                inputImagen.setCustomValidity('');

                if (!archivo) {
                    restaurarEstadoImagen();
                    return;
                }

                if (!tiposImagenPermitidos.includes(archivo.type)) {
                    inputImagen.setCustomValidity(
                        'Seleccioná una imagen JPG, PNG o WEBP.'
                    );
                    inputImagen.reportValidity();
                    inputImagen.value = '';
                    inputImagen.setCustomValidity('');
                    restaurarEstadoImagen();
                    return;
                }

                if (archivo.size > pesoMaximoImagen) {
                    inputImagen.setCustomValidity(
                        'La imagen no puede superar los 2 MB.'
                    );
                    inputImagen.reportValidity();
                    inputImagen.value = '';
                    inputImagen.setCustomValidity('');
                    restaurarEstadoImagen();
                    return;
                }

                if (checkboxEliminarImagen) {
                    checkboxEliminarImagen.checked = false;
                }

                liberarUrlTemporal();
                urlTemporalImagen = URL.createObjectURL(archivo);
                mostrarImagen(urlTemporalImagen, archivo.name);
            });

            if (checkboxEliminarImagen) {
                checkboxEliminarImagen.addEventListener(
                    'change',
                    function () {
                        if (checkboxEliminarImagen.checked) {
                            inputImagen.value = '';
                        }

                        restaurarEstadoImagen();
                    }
                );
            }

            restaurarEstadoImagen();

            function filtrarGrupos() {
                const idLinea = selectLinea.value;

                Array.from(selectGrupo.options).forEach(function (opcion) {
                    if (opcion.value === '') {
                        opcion.hidden = false;
                        return;
                    }

                    opcion.hidden =
                        idLinea !== ''
                        && opcion.dataset.idlinea !== idLinea;
                });

                const seleccionada =
                    selectGrupo.options[selectGrupo.selectedIndex];

                if (seleccionada && seleccionada.hidden) {
                    selectGrupo.value = '';
                }
            }

            function filtrarSubgrupos() {
                const idGrupo = selectGrupo.value;

                Array.from(selectSubgrupo.options).forEach(function (opcion) {
                    if (opcion.value === '') {
                        opcion.hidden = false;
                        return;
                    }

                    opcion.hidden =
                        idGrupo !== ''
                        && opcion.dataset.idgrupo !== idGrupo;
                });

                const seleccionada =
                    selectSubgrupo.options[selectSubgrupo.selectedIndex];

                if (seleccionada && seleccionada.hidden) {
                    selectSubgrupo.value = '';
                }
            }

            selectLinea.addEventListener('change', function () {
                selectGrupo.value = '';
                selectSubgrupo.value = '';
                filtrarGrupos();
                filtrarSubgrupos();
            });

            selectGrupo.addEventListener('change', function () {
                selectSubgrupo.value = '';
                filtrarSubgrupos();
            });

            filtrarGrupos();
            filtrarSubgrupos();

            document
                .querySelectorAll('input[type="number"]')
                .forEach(function (campo) {
                    campo.addEventListener('keydown', function (evento) {
                        if (['e', 'E', '+', '-'].includes(evento.key)) {
                            evento.preventDefault();
                        }
                    });
                });
        });
    </script>

</body>
</html>
