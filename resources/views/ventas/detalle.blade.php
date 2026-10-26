<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @php
        $numeroVenta = 'V-' . str_pad(
            (string) $venta->idventa,
            6,
            '0',
            STR_PAD_LEFT
        );
        $estado = strtoupper((string) $venta->estado);
        $claseEstado = match ($estado) {
            'CONFIRMADA', 'COMPLETADA' => 'insignia--exito',
            'ANULADA', 'CANCELADA' => 'insignia--error',
            default => 'insignia--pendiente',
        };
        $nombreCliente = $venta->cliente
            ? trim($venta->cliente->nombre . ' ' . $venta->cliente->apellido)
            : 'Consumidor final';
        $nombreVendedor = trim(
            ($venta->usuario?->nombre ?? '') . ' '
            . ($venta->usuario?->apellido ?? '')
        );
        $fechaVentaLocal = $venta->fecha
            ? $venta->fecha->copy()->setTimezone($zonaHoraria)
            : null;

        $porcentajeDescuentoCliente = (float) ($venta->porcentajedescuentocliente ?? 0);
        $porcentajeDescuentoAdicional = (float) ($venta->porcentajedescuentoadicional ?? 0);
        $ventaConDescuentoLegacy = $porcentajeDescuentoCliente <= 0
            && $porcentajeDescuentoAdicional <= 0
            && (float) $venta->descuento > 0;

        $importeDescuentoClienteVenta = 0.0;
        $importeDescuentoAdicionalVenta = 0.0;

        foreach ($venta->detalles as $detalleResumen) {
            $subtotalResumen = (float) $detalleResumen->subtotal;

            if ($ventaConDescuentoLegacy) {
                $importeDescuentoClienteVenta += (float) $detalleResumen->descuento;
                continue;
            }

            $descuentoClienteResumen = round(
                $subtotalResumen * ($porcentajeDescuentoCliente / 100),
                2
            );
            $baseAdicionalResumen = round(
                $subtotalResumen - $descuentoClienteResumen,
                2
            );
            $descuentoAdicionalResumen = round(
                $baseAdicionalResumen * ($porcentajeDescuentoAdicional / 100),
                2
            );

            $importeDescuentoClienteVenta += $descuentoClienteResumen;
            $importeDescuentoAdicionalVenta += $descuentoAdicionalResumen;
        }

        $importeDescuentoClienteVenta = round($importeDescuentoClienteVenta, 2);
        $importeDescuentoAdicionalVenta = round($importeDescuentoAdicionalVenta, 2);

        $porcentajeDescuentoClienteMostrar = $ventaConDescuentoLegacy
            && (float) $venta->subtotal > 0
                ? round(((float) $venta->descuento / (float) $venta->subtotal) * 100, 2)
                : $porcentajeDescuentoCliente;
        $porcentajeDescuentoAdicionalMostrar = $ventaConDescuentoLegacy
            ? 0.0
            : $porcentajeDescuentoAdicional;

        $porcentajeRecargo = (float) ($venta->porcentajerecargo ?? 0);
        $importeRecargo = (float) ($venta->importerecargo ?? 0);
        $baseDespuesDescuentos = round((float) $venta->total - $importeRecargo, 2);
        $nombreMetodoPago = $venta->metodoPago?->nombre ?? 'No registrado';
    @endphp

    <title>Detalle {{ $numeroVenta }} | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/ventas/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ventas/detalle.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">

    <script src="{{ asset('js/ventas/detalle.js') }}" defer></script>
</head>

<body class="con-barra-lateral">
    @include('partials.barra-lateral')

    <main class="pagina-ventas pagina-detalle-venta">
        <header class="encabezado-principal encabezado-detalle">
            <div>
                <p class="encabezado-principal__seccion">Detalle de la operación</p>
                <h1>Venta {{ $numeroVenta }}</h1>
                <p class="encabezado-principal__descripcion">
                    Revisá los datos generales y todos los productos incluidos.
                </p>
            </div>

            <a href="{{ route('ventas.index') }}" class="boton boton--secundario">
                ← Volver a ventas
            </a>
        </header>

        @if (session('success'))
            <section class="alerta alerta--exito" role="status">
                <strong>Operación realizada.</strong>
                <span>{{ session('success') }}</span>
            </section>
        @endif

        @if ($errors->any())
            <section class="alerta alerta--error" role="alert">
                <strong>No se pudo anular la venta.</strong>
                <span>{{ $errors->first() }}</span>
            </section>
        @endif

        <section class="datos-venta" aria-label="Datos generales de la venta">
            <article class="dato-venta">
                <span>Número</span>
                <strong>{{ $numeroVenta }}</strong>
            </article>

            <article class="dato-venta">
                <span>Fecha y hora</span>
                <strong>{{ $fechaVentaLocal?->format('d/m/Y H:i') ?? '—' }}</strong>
            </article>

            <article class="dato-venta">
                <span>Cliente</span>
                <strong>{{ $nombreCliente }}</strong>
                @if ($venta->cliente)
                    <small>
                        CLI-{{ str_pad((string) $venta->cliente->idcliente, 6, '0', STR_PAD_LEFT) }}
                    </small>
                @endif
            </article>

            <article class="dato-venta">
                <span>Vendedor</span>
                <strong>{{ $nombreVendedor !== '' ? $nombreVendedor : 'Usuario no disponible' }}</strong>
            </article>

            <article class="dato-venta">
                <span>Sucursal</span>
                <strong>{{ $venta->sucursal?->nombre ?? 'Sin sucursal' }}</strong>
                @if ($venta->sucursal?->tipo)
                    <small>{{ $venta->sucursal->tipo }}</small>
                @endif
            </article>

            <article class="dato-venta">
                <span>Canal</span>
                <strong>{{ $venta->canal ?: '—' }}</strong>
            </article>

            <article class="dato-venta">
                <span>Estado</span>
                <strong>
                    <span class="insignia {{ $claseEstado }}">
                        {{ $estado ?: 'PENDIENTE' }}
                    </span>
                </strong>
            </article>

            <article class="dato-venta">
                <span>Medio de pago</span>
                <strong>{{ $nombreMetodoPago }}</strong>
                @if ($venta->idmetodopago)
                    <small>Recargo aplicado: {{ rtrim(rtrim(number_format($porcentajeRecargo, 2, ',', '.'), '0'), ',') }}%</small>
                @else
                    <small>Venta anterior a la registración de medios de pago.</small>
                @endif
            </article>
        </section>

        @if ($movimientoAnulacion)
            @php
                $nombreUsuarioAnulacion = trim(
                    ($movimientoAnulacion->usuario?->nombre ?? '') . ' '
                    . ($movimientoAnulacion->usuario?->apellido ?? '')
                );
                $fechaAnulacionLocal = $movimientoAnulacion->fecha
                    ? $movimientoAnulacion->fecha
                        ->copy()
                        ->setTimezone($zonaHoraria)
                    : null;
            @endphp

            <section class="aviso-venta-anulada" aria-label="Información de la anulación">
                <span class="aviso-venta-anulada__icono" aria-hidden="true">!</span>
                <div>
                    <strong>Esta venta fue anulada</strong>
                    <p>
                        El stock fue reintegrado el
                        {{ $fechaAnulacionLocal?->format('d/m/Y H:i') ?? '—' }}
                        por
                        {{ $nombreUsuarioAnulacion !== '' ? $nombreUsuarioAnulacion : 'un usuario del sistema' }}.
                    </p>
                    <p>
                        <b>Motivo:</b>
                        {{ $movimientoAnulacion->observacion ?: 'Sin motivo registrado.' }}
                    </p>
                </div>
            </section>
        @elseif ($puedeAnular)
            <section class="panel-accion-anulacion">
                <div>
                    <strong>¿Necesitás corregir esta operación?</strong>
                    <p>
                        Al anularla, todos los productos volverán al stock de
                        {{ $venta->sucursal?->nombre ?? 'la sucursal' }}.
                    </p>
                </div>

                <button
                    type="button"
                    class="boton-anular-venta"
                    id="abrir-anulacion"
                >
                    Anular venta
                </button>
            </section>
        @endif

        <section class="panel panel--tabla panel-detalle-productos">
            <div class="panel__cabecera panel__cabecera--tabla">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Productos de la venta</h2>
                        <p>
                            {{ $venta->detalles->count() }}
                            {{ $venta->detalles->count() === 1 ? 'producto registrado' : 'productos registrados' }}
                        </p>
                    </div>
                </div>
            </div>

            @if ($venta->detalles->isEmpty())
                <div class="detalle-sin-productos" role="status">
                    Esta venta no posee productos registrados.
                </div>
            @else
                <div class="tabla-contenedor" tabindex="0">
                    <table class="tabla-detalle-venta">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>PVP</th>
                                <th>Desc. cliente</th>
                                <th>Desc. adicional</th>
                                <th>Cantidad</th>
                                <th>IVA incluido</th>
                                <th>Total línea</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($venta->detalles as $detalle)
                                <tr>
                                    <td>
                                        <span class="codigo">
                                            {{ $detalle->producto?->codigointerno ?? 'Sin código' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="dato-principal">
                                            {{ $detalle->producto?->descripcion ?? 'Producto no disponible' }}
                                        </strong>
                                    </td>
                                    <td class="importe importe--pvp">
                                        $ {{ number_format((float) $detalle->preciounitario, 2, ',', '.') }}
                                    </td>
                                    @php
                                        $subtotalLineaOriginal = (float) $detalle->subtotal;

                                        if ($ventaConDescuentoLegacy) {
                                            $importeDescuentoClienteLinea = (float) $detalle->descuento;
                                            $porcentajeClienteLinea = $subtotalLineaOriginal > 0
                                                ? ($importeDescuentoClienteLinea / $subtotalLineaOriginal) * 100
                                                : 0;
                                            $importeDescuentoAdicionalLinea = 0.0;
                                            $porcentajeAdicionalLinea = 0.0;
                                        } else {
                                            $porcentajeClienteLinea = $porcentajeDescuentoCliente;
                                            $importeDescuentoClienteLinea = round(
                                                $subtotalLineaOriginal * ($porcentajeClienteLinea / 100),
                                                2
                                            );
                                            $baseAdicionalLinea = round(
                                                $subtotalLineaOriginal - $importeDescuentoClienteLinea,
                                                2
                                            );
                                            $porcentajeAdicionalLinea = $porcentajeDescuentoAdicional;
                                            $importeDescuentoAdicionalLinea = round(
                                                $baseAdicionalLinea * ($porcentajeAdicionalLinea / 100),
                                                2
                                            );
                                        }

                                        $porcentajeClienteTexto = rtrim(
                                            rtrim(number_format($porcentajeClienteLinea, 2, ',', '.'), '0'),
                                            ','
                                        );
                                        $porcentajeAdicionalTexto = rtrim(
                                            rtrim(number_format($porcentajeAdicionalLinea, 2, ',', '.'), '0'),
                                            ','
                                        );
                                    @endphp
                                    <td class="descuento-detalle">
                                        <span class="descuento-detalle__porcentaje">
                                            {{ $porcentajeClienteTexto }}%
                                        </span>
                                        <span class="descuento-detalle__separador">/</span>
                                        <span class="descuento-detalle__importe">
                                            $ {{ number_format($importeDescuentoClienteLinea, 2, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="descuento-detalle descuento-detalle--adicional">
                                        <span class="descuento-detalle__porcentaje">
                                            {{ $porcentajeAdicionalTexto }}%
                                        </span>
                                        <span class="descuento-detalle__separador">/</span>
                                        <span class="descuento-detalle__importe">
                                            $ {{ number_format($importeDescuentoAdicionalLinea, 2, ',', '.') }}
                                        </span>
                                    </td>
                                    <td class="cantidad-detalle">
                                        {{ number_format((int) $detalle->cantidad, 0, ',', '.') }}
                                    </td>
                                    <td class="importe importe--secundario">
                                        $ {{ number_format((float) $detalle->importeiva, 2, ',', '.') }}
                                    </td>
                                    <td class="importe importe--destacado importe--total-linea">
                                        $ {{ number_format((float) $detalle->totallinea, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <footer class="totales-detalle">
                <div>
                    <span>Subtotal</span>
                    <strong>$ {{ number_format((float) $venta->subtotal, 2, ',', '.') }}</strong>
                </div>
                <div>
                    <span>Desc. cliente ({{ rtrim(rtrim(number_format($porcentajeDescuentoClienteMostrar, 2, ',', '.'), '0'), ',') }}%)</span>
                    <strong>$ {{ number_format($importeDescuentoClienteVenta, 2, ',', '.') }}</strong>
                </div>
                <div>
                    <span>Desc. adicional ({{ rtrim(rtrim(number_format($porcentajeDescuentoAdicionalMostrar, 2, ',', '.'), '0'), ',') }}%)</span>
                    <strong>$ {{ number_format($importeDescuentoAdicionalVenta, 2, ',', '.') }}</strong>
                </div>
                <div>
                    <span>Después de descuentos</span>
                    <strong>$ {{ number_format($baseDespuesDescuentos, 2, ',', '.') }}</strong>
                </div>
                <div class="totales-detalle__recargo">
                    <span>Recargo ({{ rtrim(rtrim(number_format($porcentajeRecargo, 2, ',', '.'), '0'), ',') }}%)</span>
                    <strong>$ {{ number_format($importeRecargo, 2, ',', '.') }}</strong>
                </div>
                <div class="totales-detalle__total">
                    <span>Total cobrado</span>
                    <strong>$ {{ number_format((float) $venta->total, 2, ',', '.') }}</strong>
                </div>
            </footer>
        </section>
    </main>

    @if ($puedeAnular)
        <div
            class="modal-anulacion"
            id="modal-anulacion"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-modal-anulacion"
            aria-hidden="true"
            data-open-on-error="{{ $errors->any() ? '1' : '0' }}"
        >
            <div class="modal-anulacion__fondo" data-cerrar-modal></div>

            <section class="modal-anulacion__contenido" tabindex="-1">
                <button
                    type="button"
                    class="modal-anulacion__cerrar"
                    aria-label="Cerrar"
                    data-cerrar-modal
                >
                    ×
                </button>

                <p class="modal-anulacion__seccion">Anulación de venta</p>
                <h2 id="titulo-modal-anulacion">Anular {{ $numeroVenta }}</h2>
                <p class="modal-anulacion__descripcion">
                    Esta acción cambiará el estado de la venta y devolverá las
                    unidades al stock. La operación quedará registrada.
                </p>

                <form
                    action="{{ route('ventas.anular', $venta) }}"
                    method="POST"
                    class="formulario-anulacion"
                    id="formulario-anulacion"
                >
                    @csrf
                    @method('PATCH')

                    <div class="campo-anulacion">
                        <label for="motivo">Motivo de la anulación *</label>
                        <textarea
                            id="motivo"
                            name="motivo"
                            rows="4"
                            minlength="10"
                            maxlength="255"
                            required
                            placeholder="Ejemplo: se cargó una cantidad incorrecta"
                        >{{ old('motivo') }}</textarea>
                        <small>Escribí entre 10 y 255 caracteres.</small>
                    </div>

                    <label class="confirmacion-anulacion">
                        <input
                            type="checkbox"
                            name="confirmar_anulacion"
                            value="1"
                            required
                            @checked(old('confirmar_anulacion'))
                        >
                        <span>
                            Confirmo que quiero anular la venta y reintegrar sus
                            productos al stock.
                        </span>
                    </label>

                    <div class="modal-anulacion__acciones">
                        <button
                            type="button"
                            class="boton boton--secundario"
                            data-cerrar-modal
                        >
                            Cancelar
                        </button>
                        <button type="submit" class="boton-confirmar-anulacion">
                            Confirmar anulación
                        </button>
                    </div>
                </form>
            </section>
        </div>
    @endif
</body>
</html>
