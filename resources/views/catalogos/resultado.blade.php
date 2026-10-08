<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Resultado de la importación | Messirve</title>

    <link
        rel="stylesheet"
        href="{{ asset('css/catalogos/importar.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/catalogos/resultado.css') }}"
    >
</head>

<body>

    @php
        $coleccionResultados = collect($resultados);

        $totalCreados = $coleccionResultados->sum(
            'registros_creados'
        );

        $totalActualizados = $coleccionResultados->sum(
            'registros_actualizados'
        );

        $totalOmitidos = $coleccionResultados->sum(
            'filas_omitidas'
        );

        $totalErrores = $coleccionResultados->sum(
            function (array $resultado): int {
                return count(
                    $resultado['errores_normalizacion'] ?? []
                ) + count(
                    $resultado['fallos_validacion'] ?? []
                ) + count(
                    $resultado['errores_base_datos'] ?? []
                );
            }
        );
    @endphp

    <main class="pagina-importacion">

        <header class="encabezado">
            <div>
                <p class="encabezado__seccion">
                    Administración de catálogos
                </p>

                <h1>Importación completada</h1>

                <p class="encabezado__descripcion">
                    El catálogo vigente del proveedor fue actualizado
                    correctamente.
                </p>
            </div>

            <div class="encabezado__marca">
                <span>MES.</span>SIRVE
            </div>
        </header>

        <section class="pasos" aria-label="Pasos de la importación">

            <article class="paso paso--completado">
                <span class="paso__numero">✓</span>

                <div>
                    <strong>Archivo analizado</strong>
                    <small>Excel procesado correctamente</small>
                </div>
            </article>

            <article class="paso paso--completado">
                <span class="paso__numero">✓</span>

                <div>
                    <strong>Columnas confirmadas</strong>
                    <small>Hojas y encabezados revisados</small>
                </div>
            </article>

            <article class="paso paso--completado">
                <span class="paso__numero">✓</span>

                <div>
                    <strong>Catálogo actualizado</strong>
                    <small>Productos guardados en la base</small>
                </div>
            </article>

        </section>

        <section class="resultado-exitoso">
            <div class="resultado-exitoso__icono" aria-hidden="true">
                ✓
            </div>

            <div>
                <h2>El proceso finalizó correctamente</h2>

                <p>
                    Los artículos existentes fueron actualizados,
                    los nuevos fueron agregados y los que no aparecieron
                    en la lista quedaron inactivos dentro del catálogo.
                </p>
            </div>
        </section>

        <section class="resumen-resultado" aria-label="Resumen general">

            <article>
                <span>Hojas procesadas</span>
                <strong>{{ $coleccionResultados->count() }}</strong>
            </article>

            <article>
                <span>Artículos nuevos</span>
                <strong>{{ number_format($totalCreados, 0, ',', '.') }}</strong>
            </article>

            <article>
                <span>Artículos actualizados</span>
                <strong>{{ number_format($totalActualizados, 0, ',', '.') }}</strong>
            </article>

            <article>
                <span>Filas omitidas</span>
                <strong>{{ number_format($totalOmitidos, 0, ',', '.') }}</strong>
            </article>

        </section>

        @if ($totalErrores > 0)
            <section class="alerta alerta--error">
                <strong>Se detectaron observaciones.</strong>

                <p>
                    Revisá el detalle de cada hoja antes de utilizar
                    la información importada.
                </p>
            </section>
        @endif

        <section class="detalle-resultados">

            <div class="detalle-resultados__titulo">
                <h2>Detalle por hoja</h2>

                <p>
                    Resumen de las filas encontradas en cada hoja
                    seleccionada.
                </p>
            </div>

            <div class="lista-resultados">

                @foreach ($resultados as $resultado)
                    @php
                        $erroresHoja =
                            count(
                                $resultado['errores_normalizacion'] ?? []
                            )
                            + count(
                                $resultado['fallos_validacion'] ?? []
                            )
                            + count(
                                $resultado['errores_base_datos'] ?? []
                            );
                    @endphp

                    <article class="resultado-hoja">

                        <header class="resultado-hoja__cabecera">
                            <div>
                                <span>Hoja procesada</span>
                                <h3>{{ $resultado['hoja'] }}</h3>
                            </div>

                            <span class="resultado-hoja__estado">
                                Completada
                            </span>
                        </header>

                        <div class="resultado-hoja__datos">

                            <div>
                                <span>Filas leídas</span>
                                <strong>
                                    {{ number_format(
                                        $resultado['filas_leidas'],
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </div>

                            <div>
                                <span>Filas válidas</span>
                                <strong>
                                    {{ number_format(
                                        $resultado['filas_normalizadas'],
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </div>

                            <div>
                                <span>Nuevos</span>
                                <strong>
                                    {{ number_format(
                                        $resultado['registros_creados'],
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </div>

                            <div>
                                <span>Actualizados</span>
                                <strong>
                                    {{ number_format(
                                        $resultado['registros_actualizados'],
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </div>

                            <div>
                                <span>Omitidos</span>
                                <strong>
                                    {{ number_format(
                                        $resultado['filas_omitidas'],
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </div>

                            <div>
                                <span>Errores</span>
                                <strong>{{ $erroresHoja }}</strong>
                            </div>

                        </div>

                    </article>
                @endforeach

            </div>

        </section>

        <footer class="acciones acciones--resultado">

            <a
                href="{{ route('catalogos.importar') }}"
                class="boton boton--principal"
            >
                Importar otra lista
                <span aria-hidden="true">→</span>
            </a>

        </footer>

    </main>

</body>
</html>
