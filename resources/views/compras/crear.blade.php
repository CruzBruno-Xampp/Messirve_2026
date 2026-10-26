<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Nueva compra | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/productos/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/compras/crear.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>
<body class="con-barra-lateral">
    @include('partials.barra-lateral')

    <main
        class="pagina-productos pagina-nueva-compra"
        id="aplicacion-nueva-compra"
        data-url-buscar="{{ route('compras.articulos.buscar') }}"
        data-url-preparar="{{ route('compras.articulos.preparar-producto', ['detalle' => '__DETALLE__']) }}"
        data-url-confirmar="{{ route('compras.guardar') }}"
    >
        <header class="encabezado-principal encabezado-principal--con-acciones">
            <div>
                <p class="encabezado-principal__seccion">Administración comercial</p>
                <h1>Nueva compra</h1>
                <p class="encabezado-principal__descripcion">
                    Buscá qué necesitás comprar. Messirve compara las listas vigentes,
                    destaca el mejor costo y te permite armar un pedido con distintos proveedores.
                </p>
            </div>

            <div class="encabezado-principal__acciones">
                <a href="{{ route('compras.index') }}" class="boton boton--secundario">
                    ← Volver a compras
                </a>
            </div>
        </header>

        <section class="aviso-flujo-compra">
            <span class="aviso-flujo-compra__icono" aria-hidden="true">i</span>
            <div>
                <strong>En esta etapa estamos armando el pedido</strong>
                <p>
                    Agregar un artículo a la lista todavía no modifica stock. La compra recién se registra cuando presionás “Confirmar pedidos”.
                    Si hay productos nuevos, el código MES y su stock inicial en cero se crean en ese momento.
                </p>
            </div>
        </section>

        <section class="panel">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Elegir destino</h2>
                        <p>Indicá dónde se recibirá la mercadería cuando llegue.</p>
                    </div>
                </div>
            </div>

            <div class="bloque-destino">
                <div class="campo">
                    <label for="sucursal-destino">Sucursal o depósito de destino</label>
                    <select id="sucursal-destino" @disabled($sucursales->count() <= 1)>
                        @forelse ($sucursales as $sucursal)
                            <option value="{{ $sucursal->idsucursal }}">
                                {{ $sucursal->nombre }}
                                @if ($sucursal->tipo)
                                    — {{ $sucursal->tipo }}
                                @endif
                            </option>
                        @empty
                            <option value="">No hay una ubicación disponible</option>
                        @endforelse
                    </select>

                    @if (!$esDueno)
                        <small>Tu usuario sólo puede preparar compras para su sucursal asignada.</small>
                    @else
                        <small>El stock inicial en cero se creará acá cuando confirmemos el pedido en el próximo paso.</small>
                    @endif
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">02</span>
                    <div>
                        <h2>Buscar y comparar artículos</h2>
                        <p>
                            Podés buscar por descripción, código del proveedor, código de barras
                            o código interno MES.
                        </p>
                    </div>
                </div>
            </div>

            <form class="buscador-compra" id="formulario-busqueda-compra">
                <div class="campo">
                    <label for="buscar-articulo">Producto a comprar</label>
                    <div class="buscador-compra__fila">
                        <input
                            type="search"
                            id="buscar-articulo"
                            placeholder="Ejemplo: aguarrás, MES-000006 o 779801..."
                            maxlength="255"
                            autocomplete="off"
                        >
                        <button type="submit" class="boton boton--primario" id="boton-buscar-articulo">
                            Buscar
                        </button>
                    </div>
                    <small>
                        La búsqueda se actualiza mientras escribís. La comparación automática nunca supone que dos productos son iguales sólo por una descripción parecida.
                    </small>
                </div>
            </form>

            <div class="resultados-compra" id="resultados-compra" aria-live="polite">
                <div class="estado-busqueda">
                    <span class="estado-busqueda__icono">⌕</span>
                    <h3>Buscá el primer artículo</h3>
                    <p>Messirve mostrará las ofertas vigentes y marcará el costo más bajo disponible.</p>
                </div>
            </div>
        </section>

        <section class="panel panel--pedido">
            <div class="panel__cabecera panel__cabecera--tabla">
                <div>
                    <span class="panel__numero">03</span>
                    <div>
                        <h2>Lista del pedido</h2>
                        <p>Podés mezclar productos de distintos proveedores. Después se separarán automáticamente.</p>
                    </div>
                </div>

                <span class="resultado-cantidad" id="contador-pedido">0 productos</span>
            </div>

            <div class="pedido-vacio" id="pedido-vacio">
                <span class="pedido-vacio__icono">+</span>
                <strong>Todavía no agregaste productos</strong>
                <p>Elegí una oferta arriba y aparecerá en esta lista.</p>
            </div>

            <div class="tabla-contenedor tabla-contenedor--pedido" id="contenedor-tabla-pedido" hidden>
                <table class="tabla-pedido">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Proveedor</th>
                            <th>Costo comparable</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="lista-pedido"></tbody>
                </table>
            </div>

            <footer class="resumen-pedido">
                <div class="resumen-pedido__proveedores">
                    <span>Proveedores involucrados</span>
                    <strong id="cantidad-proveedores">0</strong>
                </div>

                <div class="resumen-pedido__total">
                    <span>Total estimado sin IVA</span>
                    <strong id="total-pedido">$ 0,00</strong>
                </div>

                <button
                    type="button"
                    class="boton boton--primario boton-confirmar-pedido"
                    id="boton-confirmar-pedido"
                    disabled
                    title="Agregá productos válidos para confirmar el pedido"
                >
                    Confirmar pedidos
                </button>
            </footer>

            <p class="nota-proximo-paso" id="nota-proximo-paso">
                Agregá productos al pedido y completá los datos MES necesarios para habilitar la confirmación.
            </p>
        </section>

        <section class="panel confirmacion-compra" id="confirmacion-compra" hidden>
            <div class="confirmacion-compra__cabecera">
                <span class="confirmacion-compra__icono" aria-hidden="true">✓</span>
                <div>
                    <span class="confirmacion-compra__etiqueta">Pedido registrado</span>
                    <h2 id="confirmacion-compra-titulo">Las compras fueron creadas correctamente</h2>
                    <p>El stock todavía no aumentó. Las unidades ingresarán cuando confirmemos la recepción de la mercadería.</p>
                </div>
            </div>

            <div class="confirmacion-compra__lista" id="confirmacion-compra-lista"></div>

            <div class="confirmacion-compra__productos" id="confirmacion-productos-mes" hidden>
                <strong>Productos MES creados en este pedido</strong>
                <p id="confirmacion-productos-mes-texto"></p>
            </div>

            <div class="confirmacion-compra__acciones">
                <a href="{{ route('compras.index') }}" class="boton boton--primario">Ver compras</a>
                <a href="{{ route('compras.crear') }}" class="boton boton--secundario">Nueva compra</a>
            </div>
        </section>
    </main>


    <div class="modal-producto-mes" id="modal-producto-mes" hidden>
        <div class="modal-producto-mes__fondo" data-cerrar-modal-mes></div>
        <section class="modal-producto-mes__contenido" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-producto-mes">
            <header class="modal-producto-mes__cabecera">
                <div>
                    <span>Producto nuevo de Messirve</span>
                    <h2 id="titulo-modal-producto-mes">Preparar producto MES</h2>
                    <p>Confirmá los datos internos. Todavía no se guardará nada en la base.</p>
                </div>
                <button type="button" class="modal-producto-mes__cerrar" data-cerrar-modal-mes aria-label="Cerrar">×</button>
            </header>

            <form id="formulario-producto-mes" novalidate>
                <div class="producto-mes-origen">
                    <div><span>Artículo recibido</span><strong id="mes-origen-descripcion">—</strong></div>
                    <div><span>Proveedor / lista</span><strong id="mes-origen-proveedor">—</strong></div>
                    <div><span>Código de barras</span><strong id="mes-origen-barra">—</strong></div>
                    <div class="producto-mes-origen__clasificacion"><span>Clasificación recibida</span><strong id="mes-origen-clasificacion">—</strong></div>
                </div>

                <div class="modal-producto-mes__cuerpo">
                    <div class="campo-mes campo-mes--completo">
                        <label for="mes-descripcion">Descripción interna *</label>
                        <input type="text" id="mes-descripcion" maxlength="255" required>
                    </div>
                    <div class="campo-mes">
                        <label for="mes-codigo-fabricante">Código del fabricante</label>
                        <input type="text" id="mes-codigo-fabricante" maxlength="50">
                    </div>
                    <div class="campo-mes">
                        <label for="mes-codigo-barra">Código de barras</label>
                        <input type="text" id="mes-codigo-barra" maxlength="50" inputmode="numeric" pattern="[0-9]*">
                    </div>

                    <div class="campo-mes">
                        <label for="mes-marca">Marca *</label>
                        <select id="mes-marca" required></select>
                        <div class="campo-mes__nuevo" id="bloque-nueva-marca" hidden>
                            <label for="mes-nueva-marca">Nombre de la nueva marca *</label>
                            <input type="text" id="mes-nueva-marca" maxlength="80">
                        </div>
                    </div>
                    <div class="campo-mes">
                        <label for="mes-linea">Línea *</label>
                        <select id="mes-linea" required></select>
                        <div class="campo-mes__nuevo" id="bloque-nueva-linea" hidden>
                            <label for="mes-nueva-linea">Nombre de la nueva línea *</label>
                            <input type="text" id="mes-nueva-linea" maxlength="80">
                        </div>
                    </div>
                    <div class="campo-mes">
                        <label for="mes-grupo">Grupo *</label>
                        <select id="mes-grupo" required></select>
                        <div class="campo-mes__nuevo" id="bloque-nuevo-grupo" hidden>
                            <label for="mes-nuevo-grupo">Nombre del nuevo grupo *</label>
                            <input type="text" id="mes-nuevo-grupo" maxlength="80">
                        </div>
                    </div>
                    <div class="campo-mes">
                        <label for="mes-subgrupo">Subgrupo *</label>
                        <select id="mes-subgrupo" required></select>
                        <div class="campo-mes__nuevo" id="bloque-nuevo-subgrupo" hidden>
                            <label for="mes-nuevo-subgrupo">Nombre del nuevo subgrupo *</label>
                            <input type="text" id="mes-nuevo-subgrupo" maxlength="100">
                        </div>
                    </div>

                    <div class="campo-mes">
                        <label for="mes-unidad">Unidad de medida *</label>
                        <select id="mes-unidad" required></select>
                        <div class="campo-mes__nuevo campo-mes__nuevo--unidad" id="bloque-nueva-unidad" hidden>
                            <div>
                                <label for="mes-nueva-unidad">Nombre *</label>
                                <input type="text" id="mes-nueva-unidad" maxlength="50" placeholder="Ejemplo: Unidad">
                            </div>
                            <div>
                                <label for="mes-nueva-unidad-abreviatura">Abreviatura *</label>
                                <input type="text" id="mes-nueva-unidad-abreviatura" maxlength="10" placeholder="Ejemplo: un">
                            </div>
                        </div>
                    </div>
                    <div class="campo-mes">
                        <label for="mes-iva">IVA *</label>
                        <select id="mes-iva" required></select>
                    </div>
                    <div class="campo-mes">
                        <label for="mes-contenido">Contenido neto</label>
                        <input type="text" id="mes-contenido" maxlength="20" inputmode="decimal" placeholder="Ejemplo: 4, 0,25 o 1/4">
                    </div>
                    <div class="campo-mes">
                        <label for="mes-color">Color</label>
                        <input type="text" id="mes-color" maxlength="80" placeholder="Opcional">
                    </div>
                    <section class="calculadora-mes campo-mes--completo" id="calculadora-mes">
                        <div class="calculadora-mes__titulo">
                            <div>
                                <span>Precio y rentabilidad</span>
                                <strong>Messirve calcula un PVP sugerido con la oferta elegida.</strong>
                            </div>
                            <small>Podés corregir manualmente el PVP final.</small>
                        </div>

                        <div class="calculadora-mes__campos">
                            <div class="campo-mes">
                                <label for="mes-rentabilidad">Rentabilidad objetivo *</label>
                                <div class="campo-mes__sufijo campo-mes__sufijo--derecha">
                                    <input type="text" id="mes-rentabilidad" maxlength="8" inputmode="decimal" required>
                                    <span>%</span>
                                </div>
                            </div>

                            <div class="campo-mes">
                                <label for="mes-pvp">Precio final de venta *</label>
                                <div class="campo-mes__sufijo">
                                    <span>$</span>
                                    <input
                                        type="text"
                                        id="mes-pvp"
                                        maxlength="14"
                                        inputmode="decimal"
                                        placeholder="Se calcula automáticamente"
                                        required
                                    >
                                </div>
                                <small class="calculadora-mes__ayuda">
                                    Si lo editás manualmente, Messirve no lo reemplazará hasta que uses “Recalcular PVP”.
                                </small>
                            </div>

                            <button
                                type="button"
                                class="boton boton--secundario calculadora-mes__recalcular"
                                id="mes-recalcular-pvp"
                            >
                                Recalcular PVP
                            </button>
                        </div>

                        <div class="desglose-precio-mes">
                            <article>
                                <span>Precio de lista</span>
                                <strong id="mes-resumen-lista">$ 0,00</strong>
                            </article>
                            <article>
                                <span id="mes-resumen-costo-etiqueta">Costo proveedor</span>
                                <strong id="mes-resumen-costo">$ 0,00</strong>
                            </article>
                            <article>
                                <span>Costo con IVA</span>
                                <strong id="mes-resumen-iva">$ 0,00</strong>
                            </article>
                            <article class="desglose-precio-mes__final">
                                <span>PVP sugerido</span>
                                <strong id="mes-resumen-pvp">$ 0,00</strong>
                            </article>
                        </div>

                        <p class="formula-precio-mes" id="mes-formula-precio">
                            Seleccioná una alícuota de IVA para completar el cálculo.
                        </p>
                    </section>

                    <p class="error-modal-mes" id="error-modal-mes" hidden></p>
                </div>

                <footer class="modal-producto-mes__acciones">
                    <button type="button" class="boton boton--secundario" data-cerrar-modal-mes>Cancelar</button>
                    <button type="submit" class="boton boton--primario">Guardar datos y agregar</button>
                </footer>
            </form>
        </section>
    </div>

    <script src="{{ asset('js/compras/crear.js') }}" defer></script>
</body>
</html>
