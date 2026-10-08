<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Comparar precios de proveedores | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/productos/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/productos/comparar-proveedores.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>
<body class="con-barra-lateral">

    @include('partials.barra-lateral')

    @php
        $productoReferencia = $detalle->producto;
        $codigoBarraReferencia = trim((string) ($detalle->codigobarra ?? ''));

        $parametrosVolver = [
            'idproveedor' => $catalogoReferencia->idproveedor,
            'idcatalogo' => $catalogoReferencia->idcatalogoproveedor,
            'estado_articulo' => 'todos',
        ];

        $hayIvaSinNormalizar = $ofertas->contains(
            fn (array $oferta): bool =>
                $oferta['precios_incluyen_iva']
                && !$oferta['iva_pudo_normalizarse']
        );
    @endphp

    <main class="pagina-productos pagina-comparador">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">
                    Administración de catálogos
                </p>

                <h1>Comparar precios de proveedores</h1>

                <p class="encabezado-principal__descripcion">
                    Revisá las ofertas vigentes del mismo artículo antes de decidir
                    a qué proveedor realizarle la compra.
                </p>
            </div>

            <div class="encabezado-principal__acciones">
                <a
                    href="{{ route('productos.catalogo', $parametrosVolver) }}"
                    class="boton boton--secundario"
                >
                    ← Volver al catálogo
                </a>
            </div>
        </header>

        <section class="articulo-referencia">
            <div class="articulo-referencia__estado">
                {{ $productoReferencia ? $productoReferencia->codigointerno : 'PENDIENTE' }}
            </div>

            <div class="articulo-referencia__datos">
                <span>Artículo tomado como referencia</span>

                <h2>{{ $detalle->descripcionproveedor }}</h2>

                <div class="articulo-referencia__meta">
                    <span>
                        Proveedor:
                        <strong>{{ $proveedorReferencia?->razonsocial ?? 'Sin proveedor' }}</strong>
                    </span>

                    <span>
                        Lista:
                        <strong>{{ $catalogoReferencia->nombre_lista }}</strong>
                    </span>

                    <span>
                        Código proveedor:
                        <strong>{{ $detalle->codigoproveedor ?: '—' }}</strong>
                    </span>

                    <span>
                        Código de barras:
                        <strong>{{ $codigoBarraReferencia !== '' ? $codigoBarraReferencia : 'Sin código' }}</strong>
                    </span>
                </div>
            </div>
        </section>

        <section class="criterio-comparacion">
            <span class="criterio-comparacion__icono">i</span>

            <div>
                <strong>Cómo encontramos artículos equivalentes</strong>

                <p>
                    @if ($criterioComparacion === 'producto_y_codigo')
                        Se buscan ofertas vinculadas al mismo producto interno Messirve
                        y también coincidencias exactas del código de barras.
                    @elseif ($criterioComparacion === 'producto')
                        Se buscan ofertas vinculadas al mismo producto interno Messirve.
                    @else
                        Como este artículo todavía no tiene producto interno, se buscan
                        coincidencias exactas del código de barras. La descripción no se usa
                        para asumir automáticamente que dos artículos son iguales.
                    @endif
                </p>
            </div>
        </section>

        @if ($ofertas->isEmpty())
            <section class="panel">
                <div class="estado-vacio estado-vacio--comparador">
                    <span class="estado-vacio__icono">i</span>
                    <h3>No encontramos ofertas vigentes</h3>
                    <p>
                        No hay artículos equivalentes en listas vigentes de proveedores activos.
                    </p>
                </div>
            </section>
        @else
            <section class="resumen resumen--comparador">
                <article class="resumen__tarjeta">
                    <span class="resumen__etiqueta">Ofertas vigentes</span>
                    <strong class="resumen__valor">{{ $ofertas->count() }}</strong>
                    <small>Filas de catálogo encontradas</small>
                </article>

                <article class="resumen__tarjeta">
                    <span class="resumen__etiqueta">Proveedores comparables</span>
                    <strong class="resumen__valor">
                        {{ $ofertasComparables->pluck('catalogo.idproveedor')->filter()->unique()->count() }}
                    </strong>
                    <small>Con costo normalizado correctamente</small>
                </article>

                <article class="resumen__tarjeta resumen__tarjeta--mejor">
                    <span class="resumen__etiqueta">
                        {{ $hayComparacionReal ? 'Proveedor sugerido' : 'Resultado actual' }}
                    </span>

                    @if ($mejorOferta)
                        <strong class="mejor-proveedor">
                            {{ $mejorOferta['proveedor']?->razonsocial ?? 'Proveedor sin nombre' }}
                        </strong>

                        <span class="mejor-precio">
                            $ {{ number_format(
                                (float) $mejorOferta['costo_comparable'],
                                2,
                                ',',
                                '.'
                            ) }}
                        </span>

                        <small>Costo comparable sin IVA</small>
                    @else
                        <strong class="mejor-proveedor">Sin recomendación automática</strong>
                        <small>Revisá la información de IVA de las ofertas.</small>
                    @endif
                </article>

                <article class="resumen__tarjeta resumen__tarjeta--informativa">
                    <span class="resumen__icono">i</span>
                    <div>
                        <strong>Cómo se calcula</strong>
                        <p>
                            Si el proveedor informa “Precio c/desc”, se usa directamente.
                            Si no, se aplican el descuento del producto y el descuento general
                            de la lista. Después se normaliza el IVA antes de ordenar.
                        </p>
                    </div>
                </article>
            </section>

            @if (!$hayComparacionReal)
                <section class="alerta alerta--advertencia">
                    <strong>Todavía no hay una comparación entre dos proveedores.</strong>
                    <span>
                        El sistema encontró una sola alternativa comparable. Cuando otro proveedor
                        tenga el mismo artículo en su lista vigente, aparecerá automáticamente acá.
                    </span>
                </section>
            @endif

            @if ($hayIvaSinNormalizar)
                <section class="alerta alerta--error">
                    <strong>Hay ofertas que no pudieron normalizarse.</strong>
                    <span>
                        Alguna lista informa precios con IVA incluido y no encontramos una alícuota
                        válida para retirarlo. Esa oferta se muestra, pero no puede ser elegida como
                        mejor precio automáticamente.
                    </span>
                </section>
            @endif

            <section class="panel panel--tabla">
                <div class="panel__cabecera panel__cabecera--tabla">
                    <div>
                        <span class="panel__numero">01</span>
                        <div>
                            <h2>Ofertas vigentes</h2>
                            <p>
                                Las listas históricas no participan de la recomendación de compra.
                            </p>
                        </div>
                    </div>

                    <span class="resultado-cantidad">
                        {{ $ofertas->count() }} {{ $ofertas->count() === 1 ? 'oferta' : 'ofertas' }}
                    </span>
                </div>

                <div
                    class="tabla-contenedor tabla-contenedor--comparador"
                    tabindex="0"
                    aria-label="Comparación de precios entre proveedores"
                >
                    <table class="tabla-productos tabla-comparador">
                        <thead>
                            <tr>
                                <th>Proveedor</th>
                                <th>Lista vigente</th>
                                <th>Código proveedor</th>
                                <th>Código de barras</th>
                                <th>Relación con Messirve</th>
                                <th>Precio lista</th>
                                <th>Desc. producto</th>
                                <th>Desc. lista</th>
                                <th>Precio neto informado</th>
                                <th>Origen del costo</th>
                                <th>IVA en archivo</th>
                                <th>Costo comparable s/IVA</th>
                                <th>Resultado</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($ofertas as $oferta)
                                @php
                                    $catalogo = $oferta['catalogo'];
                                    $fila = $oferta['detalle'];
                                    $esMejor = $mejorOferta
                                        && $oferta['es_comparable']
                                        && $fila->iddetallecatalogo
                                            === $mejorOferta['detalle']->iddetallecatalogo;
                                @endphp

                                <tr @class([
                                    'fila-mejor-oferta' => $esMejor,
                                    'fila-referencia' => $oferta['es_referencia'],
                                    'fila-no-comparable' => !$oferta['es_comparable'],
                                ])>
                                    <td>
                                        <strong class="dato-principal">
                                            {{ $oferta['proveedor']?->razonsocial ?? 'Sin proveedor' }}
                                        </strong>

                                        @if ($oferta['es_referencia'])
                                            <span class="etiqueta-referencia">Referencia</span>
                                        @endif
                                    </td>

                                    <td>
                                        <strong class="dato-principal">
                                            {{ $catalogo?->nombre_lista ?? 'Sin nombre' }}
                                        </strong>
                                        <span class="dato-secundario">
                                            Vigencia: {{ $catalogo?->fecha_vigencia?->format('d/m/Y') ?? 'sin fecha' }}
                                        </span>
                                    </td>

                                    <td><span class="codigo">{{ $fila->codigoproveedor ?: '—' }}</span></td>
                                    <td><span class="codigo">{{ $fila->codigobarra ?: '—' }}</span></td>

                                    <td>
                                        @if ($fila->producto)
                                            <span class="producto-vinculado">
                                                <strong>{{ $fila->producto->codigointerno }}</strong>
                                                <small>{{ $fila->producto->descripcion }}</small>
                                            </span>
                                        @else
                                            <span class="producto-pendiente">Pendiente de vincular</span>
                                        @endif
                                    </td>

                                    <td class="importe">
                                        $ {{ number_format((float) $oferta['precio_lista'], 2, ',', '.') }}
                                    </td>

                                    <td class="porcentaje">
                                        {{ number_format((float) $oferta['descuento_producto'], 2, ',', '.') }}%
                                    </td>

                                    <td>
                                        <div class="descuentos-lista">
                                            <strong>
                                                {{ number_format((float) $oferta['descuento_general'], 2, ',', '.') }}%
                                            </strong>
                                            @if ($catalogo)
                                                <small>
                                                    Caja: {{ number_format((float) $catalogo->descuento_caja, 2, ',', '.') }}%
                                                    · Vol.: {{ number_format((float) $catalogo->descuento_volumen, 2, ',', '.') }}%
                                                    · Esp.: {{ number_format((float) $catalogo->descuento_especial, 2, ',', '.') }}%
                                                </small>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="importe">
                                        @if ($oferta['precio_neto_informado'] !== null)
                                            $ {{ number_format((float) $oferta['precio_neto_informado'], 2, ',', '.') }}
                                        @else
                                            <span class="dato-vacio">No informado</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($oferta['origen_costo'] === 'informado')
                                            <span class="origen-costo origen-costo--informado">Precio c/desc</span>
                                        @elseif ($oferta['origen_costo'] === 'calculado')
                                            <span class="origen-costo origen-costo--calculado">Calculado</span>
                                        @else
                                            <span class="origen-costo origen-costo--lista">Precio lista</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span @class([
                                            'estado-iva',
                                            'estado-iva--incluido' => $oferta['precios_incluyen_iva'],
                                            'estado-iva--sin-iva' => !$oferta['precios_incluyen_iva'],
                                        ])>
                                            {{ $oferta['precios_incluyen_iva'] ? 'Incluido' : 'No incluido' }}
                                        </span>
                                    </td>

                                    <td class="importe importe--comparable">
                                        @if ($oferta['es_comparable'])
                                            $ {{ number_format((float) $oferta['costo_comparable'], 2, ',', '.') }}

                                            @if (
                                                $oferta['precios_incluyen_iva']
                                                && $oferta['iva_pudo_normalizarse']
                                            )
                                                <span class="dato-secundario">
                                                    IVA {{ number_format((float) $oferta['iva_porcentaje'], 2, ',', '.') }}% retirado
                                                </span>
                                            @endif
                                        @else
                                            <span class="costo-no-comparable">Revisar IVA</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($esMejor)
                                            <span class="mejor-opcion">
                                                {{ $hayComparacionReal ? 'Mejor precio' : 'Única oferta' }}
                                            </span>
                                        @elseif (!$oferta['es_comparable'])
                                            <span class="sin-recomendacion">No comparable</span>
                                        @else
                                            <span class="otra-opcion">Alternativa</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="nota-comparacion">
                <strong>Importante</strong>
                <p>
                    La recomendación considera costo. Antes de comprar también conviene revisar
                    disponibilidad, cantidades mínimas, financiación, plazo de entrega y cualquier
                    bonificación que el proveedor no haya informado en el catálogo.
                </p>
            </section>
        @endif
    </main>
</body>
</html>
