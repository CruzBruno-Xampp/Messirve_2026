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

    <title>Confirmar columnas | Messirve</title>

    {{-- Estilos generales de la importación. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/catalogos/importar.css') }}"
    >

    {{-- Estilos específicos del mapeo. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/catalogos/mapear.css') }}?v=4"
    >
</head>

<body>

    <main class="pagina-importacion">

        <header class="encabezado">
            <div>
                <p class="encabezado__seccion">
                    Administración de catálogos
                </p>

                <h1>Confirmar hojas y columnas</h1>

                <p class="encabezado__descripcion">
                    Revisá la información detectada automáticamente
                    antes de importar los productos.
                </p>
            </div>

            <div class="encabezado__marca">
                <span>MES.</span>SIRVE
            </div>
        </header>

        {{-- Indicador del progreso de la importación. --}}
        <section class="pasos" aria-label="Pasos de la importación">

            <article class="paso paso--completado">
                <span class="paso__numero">✓</span>

                <div>
                    <strong>Archivo analizado</strong>
                    <small>{{ $importacion['nombre_original'] }}</small>
                </div>
            </article>

            <article class="paso paso--activo">
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

        <section class="resumen-archivo">

            <div>
                <span>Archivo recibido</span>

                <strong>
                    {{ $importacion['nombre_original'] }}
                </strong>
            </div>

            <div>
                <span>Hojas encontradas</span>

                <strong>
                    {{ count($analisis['hojas']) }}
                </strong>
            </div>

            <div>
                <span>Descuento total calculado</span>

                <strong>
                    {{ number_format(
                        $importacion['descuento_total'],
                        4,
                        ',',
                        '.'
                    ) }}%
                </strong>
            </div>

            <div>
                <span>Rentabilidad predeterminada</span>

                <strong>
                    {{ number_format(
                        $importacion['rentabilidad_predeterminada'],
                        2,
                        ',',
                        '.'
                    ) }}%
                </strong>
            </div>

        </section>

        <form
            action="{{ route('catalogos.importar.procesar') }}"
            method="POST"
            id="formulario-mapeo"
            class="formulario-mapeo"
        >
            @csrf

            {{-- Verifica que el formulario pertenezca a esta importación. --}}
            <input
                type="hidden"
                name="token"
                value="{{ $importacion['token'] }}"
            >

            <section class="explicacion-mapeo">
                <div class="explicacion-mapeo__icono">!</div>

                <div>
                    <strong>Revisión obligatoria</strong>

                    <p>
                        El sistema propone las columnas que reconoce,
                        pero el dueño debe confirmar que la descripción
                        y el precio de lista sean correctos.
                    </p>
                </div>
            </section>

            <div class="lista-hojas">

                @foreach ($analisis['hojas'] as $indice => $hoja)

                    @php
                        /*
                         * Si el formulario vuelve por un error,
                         * conservamos la selección realizada.
                         */
                        $valorSeleccionAnterior = old(
                            "hojas.$indice.seleccionada"
                        );

                        $hojaSeleccionada =
                            $valorSeleccionAnterior !== null
                                ? (bool) $valorSeleccionAnterior
                                : (bool) $hoja['es_candidata'];

                        $filaEncabezado = old(
                            "hojas.$indice.fila_encabezado",
                            $hoja['fila_encabezado_sugerida'] ?? 1
                        );

                        /*
                         * Genera las letras de las columnas:
                         * A, B, C ... Z, AA, AB, etc.
                         */
                        $columnasDisponibles = [];

                        $cantidadColumnas = min(
                            max(1, $hoja['total_columnas']),
                            100
                        );

                        for ($numero = 1; $numero <= $cantidadColumnas; $numero++) {
                            $numeroTemporal = $numero;
                            $letra = '';

                            while ($numeroTemporal > 0) {
                                $resto = ($numeroTemporal - 1) % 26;

                                $letra =
                                    chr(65 + $resto)
                                    . $letra;

                                $numeroTemporal = intdiv(
                                    $numeroTemporal - 1,
                                    26
                                );
                            }

                            $columnasDisponibles[] = $letra;
                        }

                    @endphp

                    <article
                        class="hoja-card {{ $hojaSeleccionada ? 'hoja-card--seleccionada' : '' }}"
                        data-hoja="{{ $indice }}"
                    >

                        <div class="hoja-card__cabecera">

                            <div class="seleccion-hoja">

                                {{-- Envía cero cuando no está seleccionada. --}}
                                <input
                                    type="hidden"
                                    name="hojas[{{ $indice }}][seleccionada]"
                                    value="0"
                                >

                                <input
                                    type="checkbox"
                                    id="hoja-{{ $indice }}"
                                    name="hojas[{{ $indice }}][seleccionada]"
                                    value="1"
                                    class="checkbox-hoja"
                                    @checked($hojaSeleccionada)
                                >

                                <label for="hoja-{{ $indice }}">
                                    <strong>{{ $hoja['nombre'] }}</strong>

                                    <span>
                                        {{ number_format(
                                            $hoja['total_filas'],
                                            0,
                                            ',',
                                            '.'
                                        ) }}
                                        filas ·

                                        {{ $hoja['total_columnas'] }}
                                        columnas
                                    </span>
                                </label>

                            </div>

                            @if ($hoja['es_candidata'])
                                <span class="estado-hoja estado-hoja--detectada">
                                    Hoja detectada
                                </span>
                            @else
                                <span class="estado-hoja estado-hoja--revision">
                                    Requiere revisión
                                </span>
                            @endif

                        </div>

                        {{-- Nombre real de la hoja, validado por el controlador. --}}
                        <input
                            type="hidden"
                            name="hojas[{{ $indice }}][nombre]"
                            value="{{ $hoja['nombre'] }}"
                        >

                        <div class="hoja-card__contenido">

                            <section class="configuracion-hoja">

                                <div class="configuracion-hoja__titulo">
                                    <h2>Encabezado de la hoja</h2>

                                    <p>
                                        Confirmá qué fila contiene los nombres
                                        de las columnas del archivo.
                                    </p>
                                </div>

                                <div class="campos-grid">

                                    <div class="campo campo--completo">
                                        <label for="fila-encabezado-{{ $indice }}">
                                            Fila que contiene los encabezados
                                        </label>

                                        <input
                                            type="number"
                                            id="fila-encabezado-{{ $indice }}"
                                            name="hojas[{{ $indice }}][fila_encabezado]"
                                            value="{{ $filaEncabezado }}"
                                            min="1"
                                            max="{{ $hoja['total_filas'] }}"
                                            class="fila-encabezado-input"
                                            data-indice="{{ $indice }}"
                                        >

                                        <small class="campo__ayuda">
                                            El sistema sugirió la fila
                                            {{ $hoja[
                                                'fila_encabezado_sugerida'
                                            ] ?? 'sin determinar' }}.
                                        </small>
                                    </div>

                                </div>

                            </section>

                            <section class="vista-previa">

                                <div class="seccion-titulo">
                                    <div>
                                        <h2>Vista previa del Excel</h2>

                                        <p>
                                            La fila coloreada es el encabezado
                                            seleccionado actualmente.
                                        </p>
                                    </div>

                                    <span>
                                        {{ $hoja['cantidad_coincidencias'] }}
                                        campos reconocidos
                                    </span>
                                </div>

                                <div class="tabla-contenedor">
                                    <table class="tabla-excel">
                                        <thead>
                                            <tr>
                                                <th>Fila</th>

                                                @foreach ($columnasDisponibles as $columna)
                                                    <th>{{ $columna }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach ($hoja['vista_previa'] as $fila)
                                                <tr
                                                    data-numero-fila="{{ $fila['numero_fila'] }}"
                                                    @class([
                                                        'fila-seleccionada' =>
                                                            (int) $fila['numero_fila']
                                                            === (int) $filaEncabezado
                                                    ])
                                                >
                                                    <th>
                                                        {{ $fila['numero_fila'] }}
                                                    </th>

                                                    @foreach ($columnasDisponibles as $columna)
                                                        @php
                                                            $valorCelda =
                                                                $fila['celdas'][$columna]
                                                                ?? '';
                                                        @endphp

                                                        <td
                                                            data-columna="{{ $columna }}"
                                                            data-valor="{{ $valorCelda }}"
                                                            title="{{ $valorCelda }}"
                                                        >
                                                            {{ \Illuminate\Support\Str::limit(
                                                                (string) $valorCelda,
                                                                45
                                                            ) }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </section>

                            <section class="mapeo-columnas">

                                <div class="seccion-titulo">
                                    <div>
                                        <h2>Relación de columnas</h2>

                                        <p>
                                            Seleccioná qué columna del Excel
                                            corresponde a cada campo de Messirve.
                                        </p>
                                    </div>
                                </div>

                                <div class="mapeo-grid">

                                    @foreach (
                                        $camposImportables
                                        as $campo => $etiqueta
                                    )
                                        @php
                                            $columnaSugerida = old(
                                                "hojas.$indice.mapeo.$campo",
                                                $hoja[
                                                    'mapeo_sugerido'
                                                ][$campo] ?? ''
                                            );

                                            $esObligatorio = in_array(
                                            $campo,
                                                [
                                                    'codigo_proveedor',
                                                    'descripcion_proveedor',
                                                    'precio_lista',
                                                ],
                                                true
                                            );
                                        @endphp

                                        <div class="campo campo-mapeo">
                                            <label
                                                for="mapeo-{{ $indice }}-{{ $campo }}"
                                            >
                                                {{ $etiqueta }}

                                                @if ($esObligatorio)
                                                    <span class="obligatorio">
                                                        *
                                                    </span>
                                                @endif
                                            </label>

                                            <select
                                                id="mapeo-{{ $indice }}-{{ $campo }}"
                                                name="hojas[{{ $indice }}][mapeo][{{ $campo }}]"
                                                class="select-mapeo"
                                                data-campo="{{ $campo }}"
                                                data-obligatorio="{{ $esObligatorio ? '1' : '0' }}"
                                                @required(
                                                    $esObligatorio
                                                    && $hojaSeleccionada
                                                )
                                            >
                                                <option value="">
                                                    No importar este campo
                                                </option>

                                                @foreach (
                                                    $columnasDisponibles
                                                    as $columna
                                                )
                                                    @php
                                                        $textoEncabezado =
                                                            $hoja[
                                                                'encabezados_sugeridos'
                                                            ][$columna]
                                                            ?? 'Sin encabezado';
                                                    @endphp

                                                    <option
                                                        value="{{ $columna }}"
                                                        data-columna="{{ $columna }}"
                                                        @selected(
                                                            $columnaSugerida
                                                            === $columna
                                                        )
                                                    >
                                                        {{ $columna }}
                                                        —
                                                        {{ \Illuminate\Support\Str::limit(
                                                            (string) $textoEncabezado,
                                                            55
                                                        ) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                    @endforeach

                                </div>

                            </section>

                        </div>

                    </article>

                @endforeach

            </div>

            <footer class="acciones acciones--mapeo">

                <button
                    type="submit"
                    form="formulario-cancelar"
                    class="boton boton--secundario"
                >
                    Cancelar importación
                </button>

                <div class="acciones__derecha">
                    <span id="contador-hojas">
                        0 hojas seleccionadas
                    </span>

                    <button
                        type="submit"
                        class="boton boton--principal"
                    >
                        Importar productos
                        <span aria-hidden="true">→</span>
                    </button>
                </div>

            </footer>

        </form>

        {{-- Formulario independiente utilizado para cancelar. --}}
        <form
            id="formulario-cancelar"
            action="{{ route('catalogos.importar.cancelar') }}"
            method="POST"
            onsubmit="return confirm(
                '¿Seguro que querés cancelar la importación?'
            );"
        >
            @csrf
        </form>

    </main>

    <script>
        const tarjetasHojas = document.querySelectorAll('.hoja-card');
        const contadorHojas = document.getElementById('contador-hojas');

        /*
         * Actualiza visualmente las hojas seleccionadas
         * y muestra cuántas se importarán.
         */
        function actualizarHojasSeleccionadas() {
            let cantidad = 0;

            tarjetasHojas.forEach(function (tarjeta) {
                const checkbox = tarjeta.querySelector('.checkbox-hoja');
                const hojaSeleccionada = checkbox.checked;

                tarjeta.classList.toggle(
                    'hoja-card--seleccionada',
                    hojaSeleccionada
                );

                /*
                 * Los campos de una hoja desmarcada no se envían ni
                 * participan de la validación del navegador.
                 */
                tarjeta
                    .querySelectorAll(
                        '.fila-encabezado-input, .select-mapeo'
                    )
                    .forEach(function (control) {
                        control.disabled = !hojaSeleccionada;

                        if (
                            control.classList.contains('select-mapeo')
                        ) {
                            control.required =
                                hojaSeleccionada
                                && control.dataset.obligatorio === '1';
                        }
                    });

                if (hojaSeleccionada) {
                    cantidad++;
                }
            });

            contadorHojas.textContent =
                cantidad === 1
                    ? '1 hoja seleccionada'
                    : cantidad + ' hojas seleccionadas';
        }

        tarjetasHojas.forEach(function (tarjeta) {
            const checkbox = tarjeta.querySelector('.checkbox-hoja');

            checkbox.addEventListener(
                'change',
                actualizarHojasSeleccionadas
            );
        });

        /*
         * Cuando cambia la fila de encabezado:
         *
         * 1. Colorea la nueva fila.
         * 2. Actualiza los textos mostrados en los select.
         */
        document
            .querySelectorAll('.fila-encabezado-input')
            .forEach(function (inputFila) {

                inputFila.addEventListener('change', function () {
                    const tarjeta = this.closest('.hoja-card');
                    const numeroFila = String(this.value);

                    tarjeta
                        .querySelectorAll('.tabla-excel tbody tr')
                        .forEach(function (fila) {
                            fila.classList.toggle(
                                'fila-seleccionada',
                                fila.dataset.numeroFila === numeroFila
                            );
                        });

                    const filaElegida = tarjeta.querySelector(
                        '.tabla-excel tbody tr[data-numero-fila="'
                        + numeroFila
                        + '"]'
                    );

                    tarjeta
                        .querySelectorAll('.select-mapeo')
                        .forEach(function (select) {

                            select
                                .querySelectorAll('option[data-columna]')
                                .forEach(function (opcion) {

                                    const columna =
                                        opcion.dataset.columna;

                                    let encabezado =
                                        'Sin encabezado';

                                    if (filaElegida) {
                                        const celda =
                                            filaElegida.querySelector(
                                                'td[data-columna="'
                                                + columna
                                                + '"]'
                                            );

                                        if (
                                            celda
                                            && celda.dataset.valor.trim() !== ''
                                        ) {
                                            encabezado =
                                                celda.dataset.valor;
                                        }
                                    }

                                    opcion.textContent =
                                        columna
                                        + ' — '
                                        + encabezado;
                                });
                        });
                });
            });

        actualizarHojasSeleccionadas();
    </script>

</body>
</html>
