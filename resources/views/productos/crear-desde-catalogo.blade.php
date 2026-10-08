<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Revisar artículo | Messirve</title>

    <link
        rel="stylesheet"
        href="{{ asset('css/productos/catalogo.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/productos/crear-desde-catalogo.css') }}"
    >
</head>

<body>

    @php
        $catalogo = $detalle->catalogoProveedor;
        $proveedor = $catalogo?->proveedor;

        $accionSeleccionada = old('accion', 'nuevo');

        $rentabilidadPredeterminada = old(
            'rentabilidadobjetivo',
            $catalogo?->rentabilidad_predeterminada ?? 0
        );

        $clasificacionProveedor = collect([
            $detalle->linea_proveedor,
            $detalle->tipo_producto_proveedor,
            $detalle->grupo_proveedor,
            $detalle->subgrupo_proveedor,
        ])->filter()->implode(' · ');

        $marcaPredeterminada = $sugerencias['idmarca']
            ?? (!empty($sugerencias['marca_nombre']) ? 'nuevo' : '');

        $lineaPredeterminada = $sugerencias['idlinea']
            ?? (!empty($sugerencias['linea_nombre']) ? 'nuevo' : '');

        $grupoPredeterminado = $sugerencias['idgrupo']
            ?? (!empty($sugerencias['grupo_nombre']) ? 'nuevo' : '');

        $subgrupoPredeterminado = $sugerencias['idsubgrupo']
            ?? (!empty($sugerencias['subgrupo_nombre']) ? 'nuevo' : '');

        $marcaSeleccionada = (string) old(
            'idmarca',
            $marcaPredeterminada
        );

        $lineaSeleccionada = (string) old(
            'idlinea',
            $lineaPredeterminada
        );

        $grupoSeleccionado = (string) old(
            'idgrupo',
            $grupoPredeterminado
        );

        $subgrupoSeleccionado = (string) old(
            'idsubgrupo',
            $subgrupoPredeterminado
        );
    @endphp

    <main class="pagina-productos pagina-revision">

        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">
                    Administración de productos
                </p>

                <h1>Revisar artículo importado</h1>

                <p class="encabezado-principal__descripcion">
                    Confirmá los datos internos de Messirve o vinculá esta
                    oferta con un producto que ya existe.
                </p>
            </div>

            <div class="encabezado-principal__acciones">
                <a
                    href="{{ route('productos.catalogo') }}"
                    class="boton boton--secundario"
                >
                    ← Volver a pendientes
                </a>
            </div>
        </header>

        @if ($errors->any())
            <section class="alerta alerta--error alerta--lista" role="alert">
                <div>
                    <strong>No se pudo guardar el producto.</strong>

                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        <section class="panel datos-importados">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>

                    <div>
                        <h2>Información recibida del proveedor</h2>
                        <p>
                            Estos datos provienen del Excel y se conservarán
                            en detalle_catalogo.
                        </p>
                    </div>
                </div>
            </div>

            <div class="datos-importados__grid">
                <article class="dato-importado dato-importado--descripcion">
                    <span>Descripción recibida</span>
                    <strong>{{ $detalle->descripcionproveedor }}</strong>
                </article>

                <article class="dato-importado">
                    <span>Proveedor</span>
                    <strong>
                        {{ $proveedor?->razonsocial ?? 'Sin proveedor' }}
                    </strong>
                </article>

                <article class="dato-importado">
                    <span>Lista</span>
                    <strong>
                        {{ $catalogo?->nombre_lista ?? 'Sin nombre' }}
                    </strong>
                </article>

                <article class="dato-importado">
                    <span>Código del proveedor</span>
                    <strong>{{ $detalle->codigoproveedor ?: '—' }}</strong>
                </article>

                <article class="dato-importado">
                    <span>Código del fabricante</span>
                    <strong>{{ $detalle->codigofabricante ?: '—' }}</strong>
                </article>

                <article class="dato-importado">
                    <span>Código de barras</span>
                    <strong>{{ $detalle->codigobarra ?: '—' }}</strong>
                </article>

                <article class="dato-importado">
                    <span>Precio de lista</span>
                    <strong>
                        $ {{ number_format(
                            (float) $detalle->preciolista,
                            2,
                            ',',
                            '.'
                        ) }}
                    </strong>
                </article>

                <article class="dato-importado">
                    <span>Descuento del producto</span>
                    <strong>
                        {{ number_format(
                            (float) $detalle->descuentoproducto,
                            2,
                            ',',
                            '.'
                        ) }}%
                    </strong>
                </article>

                <article class="dato-importado dato-importado--clasificacion">
                    <span>Clasificación del proveedor</span>
                    <strong>
                        {{ $clasificacionProveedor ?: 'Sin clasificación' }}
                    </strong>
                </article>
            </div>
        </section>

        <form
            action="{{ route(
                'productos.catalogo.guardar',
                $detalle->iddetallecatalogo
            ) }}"
            method="POST"
            enctype="multipart/form-data"
            id="formulario-producto"
            class="formulario-producto"
        >
            @csrf

            <section class="panel">
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">02</span>

                        <div>
                            <h2>¿Qué querés hacer con este artículo?</h2>
                            <p>
                                Evitá duplicados vinculándolo con un producto
                                existente cuando corresponda.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="selector-accion">
                    <label
                        class="opcion-accion"
                        data-opcion-accion="nuevo"
                    >
                        <input
                            type="radio"
                            name="accion"
                            value="nuevo"
                            @checked($accionSeleccionada === 'nuevo')
                        >

                        <span class="opcion-accion__marca">+</span>

                        <span>
                            <strong>Crear un producto nuevo</strong>
                            <small>
                                Generará un código MES y stock inicial en cero.
                            </small>
                        </span>
                    </label>

                    <label
                        class="opcion-accion"
                        data-opcion-accion="existente"
                    >
                        <input
                            type="radio"
                            name="accion"
                            value="existente"
                            @checked($accionSeleccionada === 'existente')
                        >

                        <span class="opcion-accion__marca">↗</span>

                        <span>
                            <strong>Vincular con uno existente</strong>
                            <small>
                                La oferta quedará relacionada sin duplicar el producto.
                            </small>
                        </span>
                    </label>
                </div>
            </section>

            <div
                id="panel-producto-nuevo"
                data-panel-accion="nuevo"
            >
                <section class="panel">
                    <div class="panel__cabecera">
                        <div>
                            <span class="panel__numero">03</span>

                            <div>
                                <h2>Clasificación interna de Messirve</h2>
                                <p>
                                    Los desplegables muestran nombres, pero el sistema
                                    guardará los identificadores correspondientes.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="aviso-sugerencias">
                        <span class="aviso-sugerencias__icono">✓</span>

                        <div>
                            <strong>Sugerencias preparadas desde el Excel</strong>

                            <p>
                                El sistema seleccionó las coincidencias existentes.
                                Cuando un nombre todavía no existe, aparece la opción
                                de crearlo al guardar el producto.
                            </p>
                        </div>
                    </div>

                    <div class="campos-producto">
                        <div class="campo-formulario campo-formulario--completo">
                            <label for="descripcion">
                                Descripción interna
                                <span class="obligatorio">*</span>
                            </label>

                            <input
                                type="text"
                                id="descripcion"
                                name="descripcion"
                                value="{{ old(
                                    'descripcion',
                                    $detalle->descripcionproveedor
                                ) }}"
                                maxlength="255"
                                data-required="true"
                                required
                            >

                            <small>
                                Puede corregirse para que sea clara en el sistema y la web.
                            </small>
                        </div>

                        <div class="campo-formulario">
                            <label for="idmarca">
                                Marca
                                <span class="obligatorio">*</span>
                            </label>

                            <select
                                id="idmarca"
                                name="idmarca"
                                data-required="true"
                                required
                            >
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
                                    </option>
                                @endforeach

                                <option
                                    value="nuevo"
                                    @selected($marcaSeleccionada === 'nuevo')
                                >
                                    + Crear una marca nueva
                                </option>
                            </select>

                            <small class="campo-formulario__sugerencia">
                                Sugerencia por proveedor:
                                <strong>
                                    {{ $sugerencias['marca_nombre'] ?: 'Sin sugerencia' }}
                                </strong>
                            </small>

                            <div
                                id="contenedor-nueva-marca"
                                class="campo-nuevo"
                                @hidden($marcaSeleccionada !== 'nuevo')
                            >
                                <label for="nueva_marca">
                                    Nombre de la nueva marca
                                </label>

                                <input
                                    type="text"
                                    id="nueva_marca"
                                    name="nueva_marca"
                                    value="{{ old(
                                        'nueva_marca',
                                        $sugerencias['marca_nombre']
                                    ) }}"
                                    maxlength="80"
                                    placeholder="Revisá el nombre antes de guardar"
                                >
                            </div>
                        </div>

                        <div class="campo-formulario">
                            <label for="idlinea">
                                Línea
                                <span class="obligatorio">*</span>
                            </label>

                            <select
                                id="idlinea"
                                name="idlinea"
                                data-required="true"
                                required
                            >
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
                                    </option>
                                @endforeach

                                <option
                                    value="nuevo"
                                    @selected($lineaSeleccionada === 'nuevo')
                                >
                                    + Crear una línea nueva
                                </option>
                            </select>

                            <small class="campo-formulario__sugerencia">
                                Recibido del Excel:
                                <strong>
                                    {{ $sugerencias['linea_nombre'] ?: 'Sin dato' }}
                                </strong>
                            </small>

                            <div
                                id="contenedor-nueva-linea"
                                class="campo-nuevo"
                                @hidden($lineaSeleccionada !== 'nuevo')
                            >
                                <label for="nueva_linea">
                                    Nombre de la nueva línea
                                </label>

                                <input
                                    type="text"
                                    id="nueva_linea"
                                    name="nueva_linea"
                                    value="{{ old(
                                        'nueva_linea',
                                        $sugerencias['linea_nombre']
                                    ) }}"
                                    maxlength="80"
                                >
                            </div>
                        </div>

                        <div class="campo-formulario">
                            <label for="idgrupo">
                                Grupo
                                <span class="obligatorio">*</span>
                            </label>

                            <select
                                id="idgrupo"
                                name="idgrupo"
                                data-required="true"
                                required
                            >
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
                                    </option>
                                @endforeach

                                <option
                                    value="nuevo"
                                    @selected($grupoSeleccionado === 'nuevo')
                                >
                                    + Crear un grupo nuevo
                                </option>
                            </select>

                            <small class="campo-formulario__sugerencia">
                                Recibido del Excel:
                                <strong>
                                    {{ $sugerencias['grupo_nombre'] ?: 'Sin dato' }}
                                </strong>
                            </small>

                            <div
                                id="contenedor-nuevo-grupo"
                                class="campo-nuevo"
                                @hidden($grupoSeleccionado !== 'nuevo')
                            >
                                <label for="nuevo_grupo">
                                    Nombre del nuevo grupo
                                </label>

                                <input
                                    type="text"
                                    id="nuevo_grupo"
                                    name="nuevo_grupo"
                                    value="{{ old(
                                        'nuevo_grupo',
                                        $sugerencias['grupo_nombre']
                                    ) }}"
                                    maxlength="80"
                                >
                            </div>
                        </div>

                        <div class="campo-formulario">
                            <label for="idsubgrupo">
                                Subgrupo
                                <span class="obligatorio">*</span>
                            </label>

                            <select
                                id="idsubgrupo"
                                name="idsubgrupo"
                                data-required="true"
                                required
                            >
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
                                    </option>
                                @endforeach

                                <option
                                    value="nuevo"
                                    @selected($subgrupoSeleccionado === 'nuevo')
                                >
                                    + Crear un subgrupo nuevo
                                </option>
                            </select>

                            <small class="campo-formulario__sugerencia">
                                Recibido del Excel:
                                <strong>
                                    {{ $sugerencias['subgrupo_nombre'] ?: 'Sin dato' }}
                                </strong>
                            </small>

                            <div
                                id="contenedor-nuevo-subgrupo"
                                class="campo-nuevo"
                                @hidden($subgrupoSeleccionado !== 'nuevo')
                            >
                                <label for="nuevo_subgrupo">
                                    Nombre del nuevo subgrupo
                                </label>

                                <input
                                    type="text"
                                    id="nuevo_subgrupo"
                                    name="nuevo_subgrupo"
                                    value="{{ old(
                                        'nuevo_subgrupo',
                                        $sugerencias['subgrupo_nombre']
                                    ) }}"
                                    maxlength="100"
                                >
                            </div>
                        </div>

                        <div class="campo-formulario">
                            <label for="idiva">
                                Alícuota de IVA
                                <span class="obligatorio">*</span>
                            </label>

                            <select
                                id="idiva"
                                name="idiva"
                                data-required="true"
                                required
                            >
                                <option value="">Seleccionar IVA</option>

                                @foreach ($alicuotasIva as $iva)
                                    <option
                                        value="{{ $iva->idiva }}"
                                        data-porcentaje="{{ (float) $iva->porcentaje }}"
                                        @selected(
                                            (string) old('idiva')
                                            === (string) $iva->idiva
                                        )
                                    >
                                        {{ $iva->nombre }}
                                        —
                                        {{ number_format(
                                            (float) $iva->porcentaje,
                                            2,
                                            ',',
                                            '.'
                                        ) }}%
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="campo-formulario">
                            <label for="idunidad">
                                Unidad de medida
                                <span class="obligatorio">*</span>
                            </label>

                            <select
                                id="idunidad"
                                name="idunidad"
                                data-required="true"
                                required
                            >
                                <option value="">Seleccionar unidad</option>

                                @foreach ($unidades as $unidad)
                                    <option
                                        value="{{ $unidad->idunidad }}"
                                        @selected(
                                            (string) old('idunidad')
                                            === (string) $unidad->idunidad
                                        )
                                    >
                                        {{ $unidad->nombre }}
                                        ({{ $unidad->abreviatura }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="campo-formulario">
                        <label for="contenido_neto">Contenido neto</label>

                        <input
                            type="text"
                            id="contenido_neto"
                            name="contenido_neto"
                            value="{{ old('contenido_neto') }}"
                            maxlength="20"
                            inputmode="text"
                            autocomplete="off"
                            placeholder="Ejemplo: 1/4, 1/2, 1 o 4"
                        >

                        <small>
                            Podés ingresar una fracción o un número decimal.
                            El sistema lo convertirá antes de guardarlo.
                        </small>
                    </div>

                        <div class="campo-formulario">
                            <label for="color">Color</label>

                            <input
                                type="text"
                                id="color"
                                name="color"
                                value="{{ old('color') }}"
                                maxlength="80"
                                placeholder="Ejemplo: Blanco"
                            >
                        </div>

                        <div class="campo-formulario">
                            <label for="codigofabricante">
                                Código del fabricante
                            </label>

                            <input
                                type="text"
                                id="codigofabricante"
                                name="codigofabricante"
                                value="{{ old(
                                    'codigofabricante',
                                    $detalle->codigofabricante
                                ) }}"
                                maxlength="50"
                            >
                        </div>

                        <div class="campo-formulario">
                            <label for="codigobarra">Código de barras</label>

                            <input
                                type="text"
                                id="codigobarra"
                                name="codigobarra"
                                value="{{ old(
                                    'codigobarra',
                                    $detalle->codigobarra
                                ) }}"
                                maxlength="50"
                                inputmode="numeric"
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
                                <p>
                                    El cálculo respeta los descuentos acumulados
                                    del catálogo y del producto.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="calculadora-precio"
                        id="calculadora-precio"
                        data-precio-lista="{{ (float) $detalle->preciolista }}"
                        data-descuento-producto="{{ (float) $detalle->descuentoproducto }}"
                        data-descuento-general="{{ (float) ($catalogo?->descuento_total ?? 0) }}"
                        data-incluye-iva="{{ $catalogo?->precios_incluyen_iva ? 1 : 0 }}"
                    >
                        <div class="calculadora-precio__campos">
                            <div class="campo-formulario">
                                <label for="rentabilidadobjetivo">
                                    Rentabilidad objetivo
                                    <span class="obligatorio">*</span>
                                </label>

                                <div class="campo-porcentaje">
                                    <input
                                        type="number"
                                        id="rentabilidadobjetivo"
                                        name="rentabilidadobjetivo"
                                        value="{{ $rentabilidadPredeterminada }}"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        inputmode="decimal"
                                        data-required="true"
                                        required
                                    >

                                    <span>%</span>
                                </div>
                            </div>

                            <div class="campo-formulario">
                                <label for="pvp">
                                    Precio final de venta
                                    <span class="obligatorio">*</span>
                                </label>

                                <div class="campo-moneda">
                                    <span>$</span>

                                    <input
                                        type="number"
                                        id="pvp"
                                        name="pvp"
                                        value="{{ old('pvp') }}"
                                        min="0"
                                        max="9999999999.99"
                                        step="0.01"
                                        inputmode="decimal"
                                        data-required="true"
                                        required
                                    >
                                </div>

                                <small>Podés corregir manualmente el resultado sugerido.</small>
                            </div>

                            <button
                                type="button"
                                id="boton-calcular"
                                class="boton boton--calcular"
                            >
                                Recalcular PVP
                            </button>
                        </div>

                        <div class="desglose-precio">
                            <article>
                                <span>Precio de lista</span>
                                <strong id="resumen-lista">$ 0,00</strong>
                            </article>

                            <article>
                                <span>Luego de descuentos</span>
                                <strong id="resumen-descuentos">$ 0,00</strong>
                            </article>

                            <article>
                                <span>Costo con IVA</span>
                                <strong id="resumen-iva">$ 0,00</strong>
                            </article>

                            <article class="desglose-precio__final">
                                <span>PVP sugerido</span>
                                <strong id="resumen-pvp">$ 0,00</strong>
                            </article>
                        </div>

                        <p class="formula-ayuda">
                            Descuento general:
                            <strong>
                                {{ number_format(
                                    (float) ($catalogo?->descuento_total ?? 0),
                                    4,
                                    ',',
                                    '.'
                                ) }}%
                            </strong>

                            · Descuento del producto:
                            <strong>
                                {{ number_format(
                                    (float) $detalle->descuentoproducto,
                                    2,
                                    ',',
                                    '.'
                                ) }}%
                            </strong>

                            · El precio de lista
                            <strong>
                                {{ $catalogo?->precios_incluyen_iva
                                    ? 'ya incluye IVA'
                                    : 'no incluye IVA' }}
                            </strong>.
                        </p>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel__cabecera">
                        <div>
                            <span class="panel__numero">05</span>

                            <div>
                                <h2>Imagen del producto</h2>
                                <p>
                                    Podés agregarla ahora o hacerlo después
                                    desde Editar producto.
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
                                Seleccionar imagen
                            </label>

                            <strong id="nombre-imagen">
                                Ninguna imagen seleccionada
                            </strong>

                            <small>
                                Formatos JPG, PNG o WEBP. Tamaño máximo: 2 MB.
                            </small>

                            @error('imagen')
                                <span class="mensaje-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div
                            class="vista-previa-imagen vista-previa-imagen--vacia"
                            id="vista-previa-imagen"
                        >
                            <img
                                id="imagen-previsualizada"
                                src=""
                                alt="Vista previa del producto"
                                hidden
                            >

                            <div id="imagen-sin-vista-previa">
                                <span aria-hidden="true">IMG</span>
                                <strong>Sin imagen seleccionada</strong>
                                <small>La vista previa aparecerá aquí.</small>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel__cabecera">
                        <div>
                            <span class="panel__numero">06</span>

                            <div>
                                <h2>Publicación del producto</h2>
                                <p>
                                    Estas opciones pueden modificarse posteriormente.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="opciones-publicacion">
                        <label class="opcion-checkbox">
                            <input type="hidden" name="visibleweb" value="0">

                            <input
                                type="checkbox"
                                name="visibleweb"
                                value="1"
                                @checked((bool) old('visibleweb', true))
                            >

                            <span>
                                <strong>Visible en la tienda web</strong>
                                <small>
                                    Podrá mostrarse al público cuando tenga imagen y stock.
                                </small>
                            </span>
                        </label>

                        <label class="opcion-checkbox">
                            <input type="hidden" name="destacado" value="0">

                            <input
                                type="checkbox"
                                name="destacado"
                                value="1"
                                @checked((bool) old('destacado', false))
                            >

                            <span>
                                <strong>Producto destacado</strong>
                                <small>
                                    Messirve podrá resaltarlo en la página principal.
                                </small>
                            </span>
                        </label>
                    </div>
                </section>
            </div>

            <section
                id="panel-producto-existente"
                class="panel"
                data-panel-accion="existente"
                hidden
            >
                <div class="panel__cabecera">
                    <div>
                        <span class="panel__numero">03</span>

                        <div>
                            <h2>Seleccionar producto existente</h2>
                            <p>
                                Esta acción conserva el producto y agrega la nueva
                                oferta del proveedor.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="vinculacion-existente">
                    <div class="campo-formulario">
                        <label for="idproducto_existente">
                            Producto de Messirve
                            <span class="obligatorio">*</span>
                        </label>

                        <select
                            id="idproducto_existente"
                            name="idproducto_existente"
                            data-required="true"
                            required
                        >
                            <option value="">Seleccionar producto</option>

                            @foreach ($productosExistentes as $producto)
                                <option
                                    value="{{ $producto->idproducto }}"
                                    @selected(
                                        (string) old('idproducto_existente')
                                        === (string) $producto->idproducto
                                    )
                                >
                                    {{ $producto->codigointerno }}
                                    — {{ $producto->descripcion }}

                                    @if ($producto->codigobarra)
                                        — {{ $producto->codigobarra }}
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        <small>
                            Buscá por código interno, descripción o código de barras.
                        </small>
                    </div>

                    @if ($productosExistentes->isEmpty())
                        <div class="aviso-sin-productos">
                            Todavía no existen productos activos para vincular.
                            Seleccioná “Crear un producto nuevo”.
                        </div>
                    @endif
                </div>
            </section>

            <div class="acciones-finales">
                <a
                    href="{{ route('productos.catalogo') }}"
                    class="boton boton--secundario"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="boton boton--primario boton--guardar"
                    id="boton-guardar"
                >
                    Crear producto y vincular
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </form>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radiosAccion = document.querySelectorAll(
                'input[name="accion"]'
            );

            const panelNuevo = document.getElementById(
                'panel-producto-nuevo'
            );

            const panelExistente = document.getElementById(
                'panel-producto-existente'
            );

            const botonGuardar = document.getElementById('boton-guardar');

            const inputImagen = document.getElementById('imagen');
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
            const tiposImagenPermitidos = [
                'image/jpeg',
                'image/png',
                'image/webp',
            ];
            const pesoMaximoImagen = 2 * 1024 * 1024;
            let urlTemporalImagen = null;

            function limpiarVistaPreviaImagen() {
                if (urlTemporalImagen !== null) {
                    URL.revokeObjectURL(urlTemporalImagen);
                    urlTemporalImagen = null;
                }

                imagenPrevisualizada.src = '';
                imagenPrevisualizada.hidden = true;
                imagenSinVistaPrevia.hidden = false;
                nombreImagen.textContent = 'Ninguna imagen seleccionada';
                contenedorVistaPrevia.classList.add(
                    'vista-previa-imagen--vacia'
                );
            }

            inputImagen.addEventListener('change', function () {
                const archivo = inputImagen.files[0];

                inputImagen.setCustomValidity('');

                if (!archivo) {
                    limpiarVistaPreviaImagen();
                    return;
                }

                if (!tiposImagenPermitidos.includes(archivo.type)) {
                    inputImagen.setCustomValidity(
                        'Seleccioná una imagen JPG, PNG o WEBP.'
                    );
                    inputImagen.reportValidity();
                    inputImagen.value = '';
                    inputImagen.setCustomValidity('');
                    limpiarVistaPreviaImagen();
                    return;
                }

                if (archivo.size > pesoMaximoImagen) {
                    inputImagen.setCustomValidity(
                        'La imagen no puede superar los 2 MB.'
                    );
                    inputImagen.reportValidity();
                    inputImagen.value = '';
                    inputImagen.setCustomValidity('');
                    limpiarVistaPreviaImagen();
                    return;
                }

                if (urlTemporalImagen !== null) {
                    URL.revokeObjectURL(urlTemporalImagen);
                }

                urlTemporalImagen = URL.createObjectURL(archivo);
                imagenPrevisualizada.src = urlTemporalImagen;
                imagenPrevisualizada.hidden = false;
                imagenSinVistaPrevia.hidden = true;
                nombreImagen.textContent = archivo.name;
                contenedorVistaPrevia.classList.remove(
                    'vista-previa-imagen--vacia'
                );
            });

            const camposNuevos = [
                {
                    select: document.getElementById('idmarca'),
                    contenedor: document.getElementById(
                        'contenedor-nueva-marca'
                    ),
                    input: document.getElementById('nueva_marca'),
                },
                {
                    select: document.getElementById('idlinea'),
                    contenedor: document.getElementById(
                        'contenedor-nueva-linea'
                    ),
                    input: document.getElementById('nueva_linea'),
                },
                {
                    select: document.getElementById('idgrupo'),
                    contenedor: document.getElementById(
                        'contenedor-nuevo-grupo'
                    ),
                    input: document.getElementById('nuevo_grupo'),
                },
                {
                    select: document.getElementById('idsubgrupo'),
                    contenedor: document.getElementById(
                        'contenedor-nuevo-subgrupo'
                    ),
                    input: document.getElementById('nuevo_subgrupo'),
                },
            ];

            function actualizarCamposNuevos(esProductoNuevo) {
                camposNuevos.forEach(function (campo) {
                    const crearNuevo = campo.select.value === 'nuevo';

                    campo.contenedor.hidden = !crearNuevo;
                    campo.input.required = esProductoNuevo && crearNuevo;
                });
            }

            function cambiarPanelAccion() {
                const radioSeleccionado = document.querySelector(
                    'input[name="accion"]:checked'
                );

                const accion = radioSeleccionado
                    ? radioSeleccionado.value
                    : 'nuevo';

                const esNuevo = accion === 'nuevo';

                panelNuevo.hidden = !esNuevo;
                panelExistente.hidden = esNuevo;

                panelNuevo
                    .querySelectorAll('input, select, button, textarea')
                    .forEach(function (control) {
                        control.disabled = !esNuevo;

                        if (control.dataset.required === 'true') {
                            control.required = esNuevo;
                        }
                    });

                panelExistente
                    .querySelectorAll('input, select, button, textarea')
                    .forEach(function (control) {
                        control.disabled = esNuevo;

                        if (control.dataset.required === 'true') {
                            control.required = !esNuevo;
                        }
                    });

                document
                    .querySelectorAll('[data-opcion-accion]')
                    .forEach(function (opcion) {
                        opcion.classList.toggle(
                            'opcion-accion--seleccionada',
                            opcion.dataset.opcionAccion === accion
                        );
                    });

                botonGuardar.innerHTML = esNuevo
                    ? 'Crear producto y vincular <span aria-hidden="true">→</span>'
                    : 'Vincular producto existente <span aria-hidden="true">→</span>';

                actualizarCamposNuevos(esNuevo);
            }

            radiosAccion.forEach(function (radio) {
                radio.addEventListener('change', cambiarPanelAccion);
            });

            cambiarPanelAccion();

            camposNuevos.forEach(function (campo) {
                campo.select.addEventListener('change', function () {
                    actualizarCamposNuevos(!panelNuevo.hidden);
                });
            });

            /*
             * Respeta la jerarquía línea → grupo → subgrupo y evita
             * mostrar combinaciones que no pertenecen entre sí.
             */
            const selectLinea = document.getElementById('idlinea');
            const selectGrupo = document.getElementById('idgrupo');
            const selectSubgrupo = document.getElementById('idsubgrupo');

            function filtrarGrupos() {
                const idLinea = selectLinea.value;

                Array.from(selectGrupo.options).forEach(function (opcion) {
                    if (opcion.value === '' || opcion.value === 'nuevo') {
                        opcion.hidden = false;
                        return;
                    }

                    opcion.hidden =
                        idLinea === 'nuevo'
                        || (
                            idLinea !== ''
                            && opcion.dataset.idlinea !== idLinea
                        );
                });

                const opcionSeleccionada =
                    selectGrupo.options[selectGrupo.selectedIndex];

                if (opcionSeleccionada && opcionSeleccionada.hidden) {
                    selectGrupo.value = '';
                }
            }

            function filtrarSubgrupos() {
                const idGrupo = selectGrupo.value;

                Array.from(selectSubgrupo.options).forEach(function (opcion) {
                    if (opcion.value === '' || opcion.value === 'nuevo') {
                        opcion.hidden = false;
                        return;
                    }

                    opcion.hidden =
                        idGrupo === 'nuevo'
                        || (
                            idGrupo !== ''
                            && opcion.dataset.idgrupo !== idGrupo
                        );
                });

                const opcionSeleccionada =
                    selectSubgrupo.options[selectSubgrupo.selectedIndex];

                if (opcionSeleccionada && opcionSeleccionada.hidden) {
                    selectSubgrupo.value = '';
                }
            }

            const subgrupoInicial =
                selectSubgrupo.options[selectSubgrupo.selectedIndex];

            if (
                selectGrupo.value === ''
                && subgrupoInicial
                && subgrupoInicial.dataset.idgrupo
            ) {
                selectGrupo.value = subgrupoInicial.dataset.idgrupo;
            }

            const grupoInicial =
                selectGrupo.options[selectGrupo.selectedIndex];

            if (
                selectLinea.value === ''
                && grupoInicial
                && grupoInicial.dataset.idlinea
            ) {
                selectLinea.value = grupoInicial.dataset.idlinea;
            }

            selectLinea.addEventListener('change', function () {
                filtrarGrupos();
                filtrarSubgrupos();
                actualizarCamposNuevos(!panelNuevo.hidden);
            });

            selectGrupo.addEventListener('change', function () {
                filtrarSubgrupos();
                actualizarCamposNuevos(!panelNuevo.hidden);
            });

            filtrarGrupos();
            filtrarSubgrupos();
            actualizarCamposNuevos(!panelNuevo.hidden);

            /* Calcula el precio respetando descuentos, IVA y rentabilidad. */
            const calculadora = document.getElementById('calculadora-precio');
            const selectIva = document.getElementById('idiva');
            const rentabilidad = document.getElementById(
                'rentabilidadobjetivo'
            );
            const campoPvp = document.getElementById('pvp');
            const botonCalcular = document.getElementById('boton-calcular');

            const moneda = new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: 'ARS',
                minimumFractionDigits: 2,
            });

            function numero(valor) {
                const resultado = Number.parseFloat(valor);
                return Number.isFinite(resultado) ? resultado : 0;
            }

            function calcularPrecio(actualizarCampoPvp) {
                const precioLista = numero(
                    calculadora.dataset.precioLista
                );

                const descuentoProducto = numero(
                    calculadora.dataset.descuentoProducto
                );

                const descuentoGeneral = numero(
                    calculadora.dataset.descuentoGeneral
                );

                const incluyeIva =
                    calculadora.dataset.incluyeIva === '1';

                const opcionIva =
                    selectIva.options[selectIva.selectedIndex];

                const porcentajeIva = opcionIva
                    ? numero(opcionIva.dataset.porcentaje)
                    : 0;

                const porcentajeRentabilidad = numero(
                    rentabilidad.value
                );

                const costoConDescuentos =
                    precioLista
                    * (1 - descuentoGeneral / 100)
                    * (1 - descuentoProducto / 100);

                const costoConIva = incluyeIva
                    ? costoConDescuentos
                    : costoConDescuentos * (1 + porcentajeIva / 100);

                const pvpSugerido =
                    costoConIva * (1 + porcentajeRentabilidad / 100);

                document.getElementById('resumen-lista').textContent =
                    moneda.format(precioLista);

                document.getElementById('resumen-descuentos').textContent =
                    moneda.format(costoConDescuentos);

                document.getElementById('resumen-iva').textContent =
                    moneda.format(costoConIva);

                document.getElementById('resumen-pvp').textContent =
                    moneda.format(pvpSugerido);

                if (actualizarCampoPvp) {
                    campoPvp.value = pvpSugerido.toFixed(2);
                }
            }

            selectIva.addEventListener('change', function () {
                calcularPrecio(true);
            });

            rentabilidad.addEventListener('input', function () {
                calcularPrecio(true);
            });

            botonCalcular.addEventListener('click', function () {
                calcularPrecio(true);
            });

            calcularPrecio(campoPvp.value === '');
        });
    </script>

</body>
</html>
