<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo producto manual | Messirve</title>
    <link rel="stylesheet" href="{{ asset('css/productos/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/productos/editar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
    <link rel="stylesheet" href="{{ asset('css/productos/crear-manual.css') }}">
    <script type="module" src="{{ asset('js/productos/crear-manual.js') }}"></script>
</head>
<body class="con-barra-lateral">
    @php
        $puedeVerProductos = auth('web')->user()?->tienePermiso('productos.ver') ?? false;
        $valorAnterior = function (string $campo, $defecto = '') {
            $valor = old($campo, $defecto);
            return is_scalar($valor) ? $valor : $defecto;
        };
        $clasificaciones = [
            ['id' => 'idmarca', 'etiqueta' => 'Marca', 'registros' => $marcas,
                'nuevo' => 'nueva_marca', 'textoNuevo' => 'Nueva marca', 'max' => 80, 'padre' => null],
            ['id' => 'idlinea', 'etiqueta' => 'Línea', 'registros' => $lineas,
                'nuevo' => 'nueva_linea', 'textoNuevo' => 'Nueva línea', 'max' => 80, 'padre' => null],
            ['id' => 'idgrupo', 'etiqueta' => 'Grupo', 'registros' => $grupos,
                'nuevo' => 'nuevo_grupo', 'textoNuevo' => 'Nuevo grupo', 'max' => 80, 'padre' => 'idlinea'],
            ['id' => 'idsubgrupo', 'etiqueta' => 'Subgrupo', 'registros' => $subgrupos,
                'nuevo' => 'nuevo_subgrupo', 'textoNuevo' => 'Nuevo subgrupo', 'max' => 100, 'padre' => 'idgrupo'],
        ];
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos pagina-edicion pagina-producto-manual">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Administración de productos</p>
                <h1>Nuevo producto manual</h1>
                <p class="encabezado-principal__descripcion">
                    Cargá un artículo que no figure en los catálogos de tus proveedores.
                </p>
            </div>
            @if ($puedeVerProductos)
                <a href="{{ route('productos.index') }}" class="boton boton--secundario">Volver a productos</a>
            @endif
        </header>

        @if (session('success'))
            <div class="alerta alerta--exito" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alerta alerta--error" role="alert" tabindex="-1" id="errores-del-producto">
                <strong>Revisá estos datos para poder guardar:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p>Si habías elegido una foto, volvé a seleccionarla.</p>
            </div>
        @endif

        <section class="resumen-edicion resumen-manual" aria-label="Cómo se dará de alta el producto">
            <article>
                <span>Código interno</span>
                <strong>Se genera al guardar</strong>
                <small>El sistema le asignará su propio código MES.</small>
            </article>
            <article class="resumen-edicion__aviso">
                <span class="resumen-edicion__icono" aria-hidden="true">i</span>
                <div>
                    <strong>Primero cargá el producto; después, las existencias</strong>
                    <small>Se dará de alta activo y con stock cero en el depósito central.
                        Las unidades compradas se registran desde Stock.</small>
                </div>
            </article>
        </section>

        <form action="{{ route('productos.manual.guardar') }}" method="POST"
              enctype="multipart/form-data" class="formulario-edicion" id="formulario-producto-manual">
            @csrf
            <p class="nota-obligatorios">Los campos con <span class="obligatorio">*</span> son obligatorios.</p>

            <section class="panel">
                <div class="panel__cabecera"><div>
                    <span class="panel__numero">01</span>
                    <div><h2>Datos del producto</h2><p>Escribí cómo lo vas a identificar en la pinturería.</p></div>
                </div></div>
                <div class="campos-edicion">
                    <div class="campo-edicion campo-edicion--completo">
                        <label for="descripcion">Descripción <span class="obligatorio">*</span></label>
                        <input type="text" id="descripcion" name="descripcion" required maxlength="255"
                               value="{{ $valorAnterior('descripcion') }}" data-validar="nombre"
                               aria-describedby="error-descripcion" placeholder="Ejemplo: Pincel de cerda sintética 2 pulgadas">
                        <small id="error-descripcion" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('descripcion')) hidden @endif>{{ $errors->first('descripcion') }}</small>
                    </div>
                    <div class="campo-edicion">
                        <label for="codigofabricante">Código del fabricante</label>
                        <input type="text" id="codigofabricante" name="codigofabricante" maxlength="50"
                               value="{{ $valorAnterior('codigofabricante') }}" aria-describedby="ayuda-fabricante error-codigofabricante">
                        <small id="ayuda-fabricante">Opcional. Puede incluir letras y números. Debe ser único dentro de su marca.</small>
                        <small id="error-codigofabricante" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('codigofabricante')) hidden @endif>{{ $errors->first('codigofabricante') }}</small>
                    </div>
                    <div class="campo-edicion">
                        <label for="codigobarra">Código de barras</label>
                        <input type="text" id="codigobarra" name="codigobarra" maxlength="50" inputmode="numeric"
                               pattern="[0-9]+" value="{{ $valorAnterior('codigobarra') }}" data-validar="barras"
                               title="Solo números, sin espacios ni signos." aria-describedby="ayuda-barras error-codigobarra">
                        <small id="ayuda-barras">Opcional. Se conservan los ceros del comienzo. No puede repetirse en otro producto.</small>
                        <small id="error-codigobarra" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('codigobarra')) hidden @endif>{{ $errors->first('codigobarra') }}</small>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera"><div>
                    <span class="panel__numero">02</span>
                    <div><h2>Marca y clasificación</h2><p>Elegí las opciones existentes o agregá las que falten.</p></div>
                </div></div>
                <div class="campos-edicion">
                    @foreach ($clasificaciones as $campo)
                        <div class="campo-edicion">
                            <label for="{{ $campo['id'] }}">{{ $campo['etiqueta'] }} <span class="obligatorio">*</span></label>
                            <select id="{{ $campo['id'] }}" name="{{ $campo['id'] }}" required
                                    data-maestro data-nuevo="{{ $campo['nuevo'] }}"
                                    @if ($campo['padre']) data-depende-de="{{ $campo['padre'] }}" @endif
                                    aria-describedby="error-{{ $campo['id'] }}">
                                <option value="">Seleccionar {{ mb_strtolower($campo['etiqueta']) }}</option>
                                @foreach ($campo['registros'] as $registro)
                                    <option value="{{ $registro->{$campo['id']} }}"
                                            @if ($campo['padre']) data-padre="{{ $registro->{$campo['padre']} }}" @endif
                                            @selected((string) $valorAnterior($campo['id']) === (string) $registro->{$campo['id']})>
                                        {{ $registro->nombre }}
                                    </option>
                                @endforeach
                                <option value="nuevo" @selected($valorAnterior($campo['id']) === 'nuevo')>
                                    + {{ $campo['textoNuevo'] }}
                                </option>
                            </select>
                            <small id="error-{{ $campo['id'] }}" class="mensaje-error" aria-live="polite"
                                   @if (!$errors->has($campo['id'])) hidden @endif>{{ $errors->first($campo['id']) }}</small>

                            <div class="alta-clasificacion" data-alta-nueva="{{ $campo['id'] }}">
                                <label for="{{ $campo['nuevo'] }}">{{ $campo['textoNuevo'] }} <span class="obligatorio">*</span></label>
                                <input type="text" id="{{ $campo['nuevo'] }}" name="{{ $campo['nuevo'] }}"
                                       maxlength="{{ $campo['max'] }}" value="{{ $valorAnterior($campo['nuevo']) }}"
                                       data-validar="nombre" aria-describedby="error-{{ $campo['nuevo'] }}">
                                <small>Completá este nombre solamente si elegiste agregar una opción nueva.</small>
                                <small id="error-{{ $campo['nuevo'] }}" class="mensaje-error" aria-live="polite"
                                       @if (!$errors->has($campo['nuevo'])) hidden @endif>{{ $errors->first($campo['nuevo']) }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera"><div>
                    <span class="panel__numero">03</span>
                    <div><h2>Presentación</h2><p>Indicá la unidad de medida y, cuando corresponda, el contenido del envase.</p></div>
                </div></div>
                <div class="campos-edicion">
                    <div class="campo-edicion">
                        <label for="idunidad">Unidad de medida <span class="obligatorio">*</span></label>
                        <select id="idunidad" name="idunidad" required
                                data-selector-unidad
                                aria-describedby="error-idunidad">
                            <option value="">Seleccionar unidad</option>
                            @foreach ($unidadesMedida as $unidad)
                                <option value="{{ $unidad->idunidad }}" @selected((string) $valorAnterior('idunidad') === (string) $unidad->idunidad)>
                                    {{ $unidad->nombre }} ({{ $unidad->abreviatura }})
                                </option>
                            @endforeach
                            <option value="nuevo" @selected($valorAnterior('idunidad') === 'nuevo')>
                                + Nueva unidad
                            </option>
                        </select>
                        <small id="error-idunidad" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('idunidad')) hidden @endif>{{ $errors->first('idunidad') }}</small>

                        <div class="alta-clasificacion alta-unidad" data-alta-unidad>
                            <div class="alta-unidad__campos">
                                <div>
                                    <label for="nueva_unidad">
                                        Nombre de la unidad <span class="obligatorio">*</span>
                                    </label>
                                    <input type="text" id="nueva_unidad" name="nueva_unidad"
                                           maxlength="50" value="{{ $valorAnterior('nueva_unidad') }}"
                                           data-validar="nombre"
                                           placeholder="Ejemplo: Unidad"
                                           aria-describedby="error-nueva_unidad">
                                    <small id="error-nueva_unidad" class="mensaje-error" aria-live="polite"
                                           @if (!$errors->has('nueva_unidad')) hidden @endif>{{ $errors->first('nueva_unidad') }}</small>
                                </div>
                                <div>
                                    <label for="nueva_unidad_abreviatura">
                                        Abreviatura <span class="obligatorio">*</span>
                                    </label>
                                    <input type="text" id="nueva_unidad_abreviatura"
                                           name="nueva_unidad_abreviatura" maxlength="10"
                                           value="{{ $valorAnterior('nueva_unidad_abreviatura') }}"
                                           data-validar="abreviatura"
                                           placeholder="Ejemplo: un"
                                           aria-describedby="ayuda-abreviatura error-nueva_unidad_abreviatura">
                                    <small id="ayuda-abreviatura">Ejemplos: un, L, kg o m².</small>
                                    <small id="error-nueva_unidad_abreviatura" class="mensaje-error" aria-live="polite"
                                           @if (!$errors->has('nueva_unidad_abreviatura')) hidden @endif>{{ $errors->first('nueva_unidad_abreviatura') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="campo-edicion">
                        <label for="contenido_neto">Contenido neto</label>
                        <input type="text" id="contenido_neto" name="contenido_neto" maxlength="20"
                               value="{{ $valorAnterior('contenido_neto') }}" data-validar="contenido"
                               pattern="(?:[0-9]+(?:[.,][0-9]{1,3})?|[0-9]+[ \t]*/[ \t]*[0-9]+)"
                               title="Usá una cantidad positiva como 4, 0,25 o 1/4, sin la unidad."
                               placeholder="Ejemplo: 4, 0,25 o 1/4" aria-describedby="ayuda-contenido error-contenido_neto">
                        <small id="ayuda-contenido">Opcional. Mayor que cero, hasta 3 decimales o una fracción. No escribas «litros» ni «kg» acá.</small>
                        <small id="error-contenido_neto" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('contenido_neto')) hidden @endif>{{ $errors->first('contenido_neto') }}</small>
                    </div>
                    <div class="campo-edicion campo-edicion--completo">
                        <label for="color">Color</label>
                        <input type="text" id="color" name="color" maxlength="80" value="{{ $valorAnterior('color') }}"
                               aria-describedby="error-color" placeholder="Opcional, por ejemplo: Blanco">
                        <small id="error-color" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('color')) hidden @endif>{{ $errors->first('color') }}</small>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera"><div>
                    <span class="panel__numero">04</span>
                    <div><h2>Precio de venta</h2><p>Completá el precio final que va a tener el producto.</p></div>
                </div></div>
                <div class="campos-edicion">
                    <div class="campo-edicion">
                        <label for="idiva">IVA <span class="obligatorio">*</span></label>
                        <select id="idiva" name="idiva" required aria-describedby="error-idiva">
                            <option value="">Seleccionar IVA</option>
                            @foreach ($alicuotasIva as $iva)
                                <option value="{{ $iva->idiva }}" @selected((string) $valorAnterior('idiva') === (string) $iva->idiva)>
                                    {{ $iva->nombre }} — {{ number_format($iva->porcentaje, 2, ',', '.') }} %
                                </option>
                            @endforeach
                        </select>
                        <small id="error-idiva" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('idiva')) hidden @endif>{{ $errors->first('idiva') }}</small>
                    </div>
                    <div class="campo-edicion">
                        <label for="pvp">Precio final de venta <span class="obligatorio">*</span></label>
                        <div class="campo-con-sufijo campo-con-sufijo--moneda">
                            <span>$</span>
                            <input type="text" id="pvp" name="pvp" required maxlength="14" inputmode="decimal"
                                   value="{{ $valorAnterior('pvp') }}" data-validar="precio" pattern="[0-9]+(?:[.,][0-9]{1,2})?"
                                   placeholder="Ejemplo: 4500,00" aria-describedby="ayuda-precio error-pvp">
                        </div>
                        <small id="ayuda-precio">Ingresá el importe final con IVA, sin separador de miles. No se calcula automáticamente.</small>
                        <small id="error-pvp" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('pvp')) hidden @endif>{{ $errors->first('pvp') }}</small>
                    </div>
                    <div class="campo-edicion campo-edicion--completo">
                        <label for="rentabilidadobjetivo">Rentabilidad objetivo <span class="obligatorio">*</span></label>
                        <div class="campo-con-sufijo">
                            <input type="text" id="rentabilidadobjetivo" name="rentabilidadobjetivo" required maxlength="14"
                                   inputmode="decimal" value="{{ $valorAnterior('rentabilidadobjetivo', '50,00') }}"
                                   data-validar="porcentaje" pattern="[0-9]+(?:[.,][0-9]{1,2})?"
                                   aria-describedby="ayuda-rentabilidad error-rentabilidadobjetivo">
                            <span>%</span>
                        </div>
                        <small id="ayuda-rentabilidad">De 0 a 100 %. Se guarda como referencia; no modifica el precio que escribiste.</small>
                        <small id="error-rentabilidadobjetivo" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('rentabilidadobjetivo')) hidden @endif>{{ $errors->first('rentabilidadobjetivo') }}</small>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel__cabecera"><div>
                    <span class="panel__numero">05</span>
                    <div><h2>Foto y publicación</h2><p>La foto es opcional. Podés agregarla también al editar el producto.</p></div>
                </div></div>
                <div class="carga-imagen-producto">
                    <div class="campo-edicion">
                        <label for="imagen">Imagen del producto</label>
                        <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp"
                               aria-describedby="ayuda-imagen error-imagen">
                        <small id="ayuda-imagen">JPG, PNG o WEBP, hasta 2 MB.</small>
                        <small id="error-imagen" class="mensaje-error" aria-live="polite"
                               @if (!$errors->has('imagen')) hidden @endif>{{ $errors->first('imagen') }}</small>
                        <button type="button" id="quitar-imagen" class="boton boton--secundario" hidden>Quitar foto seleccionada</button>
                    </div>
                    <div class="vista-previa-manual" aria-label="Vista previa de la foto">
                        <img id="imagen-previsualizada" alt="Foto seleccionada del producto" hidden>
                        <p id="imagen-sin-vista-previa">Sin foto seleccionada</p>
                    </div>
                </div>
                <div class="opciones-edicion">
                    <input type="hidden" name="visibleweb" value="0">
                    <label class="opcion-edicion" for="visibleweb">
                        <input type="checkbox" id="visibleweb" name="visibleweb" value="1" @checked((string) $valorAnterior('visibleweb', '0') === '1')>
                        <span><strong>Visible en la web</strong><small>Permite mostrar este producto en la tienda.</small></span>
                    </label>
                    <input type="hidden" name="destacado" value="0">
                    <label class="opcion-edicion" for="destacado">
                        <input type="checkbox" id="destacado" name="destacado" value="1" @checked((string) $valorAnterior('destacado', '0') === '1')>
                        <span><strong>Producto destacado</strong><small>Lo marca para la sección de destacados.</small></span>
                    </label>
                </div>
                @error('visibleweb')<p class="mensaje-error error-opcion">{{ $message }}</p>@enderror
                @error('destacado')<p class="mensaje-error error-opcion">{{ $message }}</p>@enderror
            </section>

            <div class="acciones-edicion">
                @if ($puedeVerProductos)
                    <a href="{{ route('productos.index') }}" class="boton boton--secundario">Cancelar</a>
                @endif
                <button type="submit" class="boton boton--primario" id="guardar-producto-manual">Guardar producto</button>
            </div>
            <p class="estado-guardado" id="estado-guardado" role="status" aria-live="polite"></p>
        </form>
    </main>
</body>
</html>
