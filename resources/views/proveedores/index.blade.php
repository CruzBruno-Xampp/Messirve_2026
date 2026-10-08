<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Proveedores | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/productos/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/proveedores/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>
<body class="con-barra-lateral">
    @php
        $usuarioAdmin = auth('web')->user();
        $puedeCrearProveedores = $usuarioAdmin?->tienePermiso('proveedores.crear') ?? false;
        $puedeEditarProveedores = $usuarioAdmin?->tienePermiso('proveedores.editar') ?? false;
        $puedeImportarCatalogos = $usuarioAdmin?->tienePermiso('catalogos.importar') ?? false;
        $puedeVerCatalogos = $usuarioAdmin?->tienePermiso('catalogos.ver') ?? false;

        $totalProveedores = (int) ($resumen->total ?? 0);
        $proveedoresActivos = (int) ($resumen->activos ?? 0);
        $proveedoresInactivos = (int) ($resumen->inactivos ?? 0);

        $errorCreacionProveedor = (bool) session('abrir_modal_nuevo_proveedor', false);
        $esFormularioCreacion = $errorCreacionProveedor || old('_formulario') === 'crear';
        $esFormularioEdicion = old('_formulario') === 'editar';

        $edicionConErroresDelProveedorActual = $esFormularioEdicion
            && $proveedorSeleccionado
            && (int) old('_proveedor_id') === (int) $proveedorSeleccionado->idproveedor;

        $hayFiltros = $busqueda !== '' || $estado !== 'todos';

        $codigoProveedor = fn ($proveedor) => 'PRV-' . str_pad(
            (string) $proveedor->idproveedor,
            6,
            '0',
            STR_PAD_LEFT
        );
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos pagina-proveedores">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Administración comercial</p>
                <h1>Proveedores de Messirve</h1>
                <p class="encabezado-principal__descripcion">
                    Consultá los proveedores registrados, actualizá sus datos y cargá nuevos
                    proveedores para trabajar con sus listas y catálogos.
                </p>
            </div>

            @if ($puedeCrearProveedores)
                <div class="encabezado-principal__acciones">
                    <button
                        type="button"
                        id="boton-abrir-nuevo-proveedor"
                        class="boton boton--primario"
                    >
                        <span class="boton__simbolo" aria-hidden="true">+</span>
                        Nuevo proveedor
                    </button>
                </div>
            @endif
        </header>

        @if (session('success'))
            <section class="alerta alerta--exito" role="status">
                <strong>Operación realizada.</strong>
                <span>{{ session('success') }}</span>
            </section>
        @endif

        @if ($errors->any() && !$esFormularioCreacion)
            <section class="alerta alerta--error alerta--lista" role="alert">
                <div>
                    <strong>Revisá los datos ingresados:</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        <section class="resumen resumen--proveedores">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Proveedores registrados</span>
                <strong class="resumen__valor">{{ number_format($totalProveedores, 0, ',', '.') }}</strong>
                <small>Total de proveedores cargados</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Estado de proveedores</span>
                <div class="resumen-estados-proveedores">
                    <span><strong>{{ $proveedoresActivos }}</strong> Activos</span>
                    <span><strong>{{ $proveedoresInactivos }}</strong> Inactivos</span>
                </div>
                <small>Podés deshabilitar proveedores sin borrar su historial</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa proveedor-info">
                <span class="resumen__icono" aria-hidden="true">i</span>
                <div>
                    <strong>Catálogos por proveedor</strong>
                    <p>
                        Cada proveedor puede tener varias listas a lo largo del tiempo.
                        Desde el perfil del proveedor podés iniciar la carga de una nueva lista o catálogo.
                    </p>
                </div>
            </article>
        </section>

        <section class="panel panel--filtros-proveedores">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Buscar proveedores</h2>
                        <p>Buscá por código, razón social, CUIT, contacto, correo o teléfono.</p>
                    </div>
                </div>
            </div>

            <form
                action="{{ route('proveedores.index') }}"
                method="GET"
                id="formulario-filtros-proveedores"
                class="filtros filtros--proveedores"
            >
                <div class="campo campo--busqueda-proveedor">
                    <label for="buscar">Buscar proveedor</label>
                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $busqueda }}"
                        placeholder="Ejemplo: PRV-000001, Tersuave, CUIT o contacto..."
                        maxlength="255"
                        autocomplete="off"
                    >
                </div>

                <div class="campo">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="todos" @selected($estado === 'todos')>Todos</option>
                        <option value="1" @selected($estado === '1')>Activos</option>
                        <option value="0" @selected($estado === '0')>Inactivos</option>
                    </select>
                </div>

                <div class="filtros__acciones filtros__acciones--proveedores">
                    @if ($hayFiltros)
                        <a href="{{ route('proveedores.index') }}" class="boton boton--secundario">
                            Limpiar
                        </a>
                    @endif

                    <span
                        id="estado-filtros-proveedores"
                        class="filtros-automaticos"
                        role="status"
                        aria-live="polite"
                    >
                        Búsqueda automática
                    </span>

                    <noscript>
                        <button type="submit" class="boton boton--primario">Buscar</button>
                    </noscript>
                </div>
            </form>
        </section>

        <div class="proveedores-distribucion">
            <section class="panel panel--tabla panel--lista-proveedores">
                <div class="panel__cabecera panel__cabecera--tabla">
                    <div>
                        <span class="panel__numero">02</span>
                        <div>
                            <h2>Proveedores registrados</h2>
                            <p>Seleccioná un proveedor para consultar o modificar sus datos.</p>
                        </div>
                    </div>

                    <span class="resultado-cantidad">
                        {{ $proveedores->firstItem() ?? 0 }}–{{ $proveedores->lastItem() ?? 0 }}
                        de {{ $proveedores->total() }}
                    </span>
                </div>

                @if ($proveedores->isEmpty())
                    <div class="estado-vacio estado-vacio--proveedores">
                        <span class="estado-vacio__icono" aria-hidden="true">i</span>
                        <h3>No encontramos proveedores</h3>
                        <p>No hay proveedores que coincidan con los filtros seleccionados.</p>

                        @if ($hayFiltros)
                            <div class="estado-vacio__acciones">
                                <a href="{{ route('proveedores.index') }}" class="boton boton--secundario">
                                    Limpiar filtros
                                </a>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="tabla-contenedor tabla-contenedor--proveedores" tabindex="0">
                        <table class="tabla-proveedores">
                            <thead>
                                <tr>
                                    <th>Proveedor</th>
                                    <th>CUIT</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Perfil</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($proveedores as $proveedor)
                                    @php
                                        $estaSeleccionado = $proveedorSeleccionado
                                            && (int) $proveedorSeleccionado->idproveedor === (int) $proveedor->idproveedor;

                                        $parametrosPerfil = request()->except('page');
                                        $parametrosPerfil['proveedor'] = $proveedor->idproveedor;

                                        $inicial = mb_strtoupper(
                                            mb_substr($proveedor->razonsocial, 0, 1, 'UTF-8'),
                                            'UTF-8'
                                        );
                                    @endphp

                                    <tr class="{{ $estaSeleccionado ? 'fila-proveedor--seleccionada' : '' }}">
                                        <td>
                                            <div class="proveedor-identidad">
                                                <span class="proveedor-avatar" aria-hidden="true">{{ $inicial }}</span>
                                                <span class="proveedor-identidad__datos">
                                                    <strong>{{ $proveedor->razonsocial }}</strong>
                                                    <small>{{ $codigoProveedor($proveedor) }}</small>
                                                </span>
                                            </div>
                                        </td>
                                        <td>{{ $proveedor->cuit ?: 'Sin cargar' }}</td>
                                        <td>
                                            <span class="contacto-proveedor">
                                                <strong>{{ $proveedor->contacto ?: 'Sin contacto' }}</strong>
                                                <small>
                                                    {{ $proveedor->telefono ?: 'Sin teléfono' }}
                                                    @if ($proveedor->email)
                                                        · {{ $proveedor->email }}
                                                    @endif
                                                </small>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="estado-proveedor {{ $proveedor->estado ? 'estado-proveedor--activo' : 'estado-proveedor--inactivo' }}">
                                                {{ $proveedor->estado ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <a
                                                href="{{ route('proveedores.index', $parametrosPerfil) }}"
                                                class="boton-ver-proveedor{{ $estaSeleccionado ? ' boton-ver-proveedor--activo' : '' }}"
                                                @if ($estaSeleccionado) aria-current="page" @endif
                                            >
                                                {{ $estaSeleccionado ? 'Seleccionado' : 'Ver perfil' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($proveedores->hasPages())
                        @php
                            $paginaInicial = max(1, $proveedores->currentPage() - 2);
                            $paginaFinal = min($proveedores->lastPage(), $proveedores->currentPage() + 2);
                        @endphp

                        <nav class="paginacion" aria-label="Paginación de proveedores">
                            @if ($proveedores->onFirstPage())
                                <span class="paginacion__boton paginacion__boton--deshabilitado">Anterior</span>
                            @else
                                <a href="{{ $proveedores->previousPageUrl() }}" class="paginacion__boton">Anterior</a>
                            @endif

                            <div class="paginacion__paginas">
                                @foreach (range($paginaInicial, $paginaFinal) as $pagina)
                                    <a
                                        href="{{ $proveedores->url($pagina) }}"
                                        class="paginacion__numero{{ $pagina === $proveedores->currentPage() ? ' paginacion__numero--activo' : '' }}"
                                        @if ($pagina === $proveedores->currentPage()) aria-current="page" @endif
                                    >
                                        {{ $pagina }}
                                    </a>
                                @endforeach
                            </div>

                            @if ($proveedores->hasMorePages())
                                <a href="{{ $proveedores->nextPageUrl() }}" class="paginacion__boton">Siguiente</a>
                            @else
                                <span class="paginacion__boton paginacion__boton--deshabilitado">Siguiente</span>
                            @endif
                        </nav>
                    @endif
                @endif
            </section>

            <aside class="panel panel--perfil-proveedor">
                <div class="panel__cabecera panel__cabecera--perfil-proveedor">
                    <div>
                        <span class="panel__numero">03</span>
                        <div>
                            <h2>Perfil seleccionado</h2>
                            <p>{{ $puedeEditarProveedores ? 'Actualizá los datos del proveedor.' : 'Consultá los datos del proveedor.' }}</p>
                        </div>
                    </div>
                </div>

                @if (!$proveedorSeleccionado)
                    <div class="perfil-proveedor-vacio">
                        <strong>Seleccioná un proveedor</strong>
                        <p>Elegí un registro de la tabla para abrir su perfil.</p>
                    </div>
                @else
                    @php
                        $inicialSeleccionado = mb_strtoupper(
                            mb_substr($proveedorSeleccionado->razonsocial, 0, 1, 'UTF-8'),
                            'UTF-8'
                        );
                    @endphp

                    <div class="perfil-proveedor-resumen">
                        <span class="perfil-proveedor-resumen__avatar" aria-hidden="true">
                            {{ $inicialSeleccionado }}
                        </span>
                        <div>
                            <span class="perfil-proveedor-resumen__etiqueta">{{ $codigoProveedor($proveedorSeleccionado) }}</span>
                            <h3>{{ $proveedorSeleccionado->razonsocial }}</h3>
                            <p>{{ $proveedorSeleccionado->email ?: 'Sin correo registrado' }}</p>
                        </div>
                        <span class="estado-proveedor {{ $proveedorSeleccionado->estado ? 'estado-proveedor--activo' : 'estado-proveedor--inactivo' }}">
                            {{ $proveedorSeleccionado->estado ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>

                    @if ($puedeImportarCatalogos || $puedeVerCatalogos)
                        <div class="acciones-catalogo-proveedor">
                            <div class="acciones-catalogo-proveedor__botones">
                                @if ($puedeImportarCatalogos)
                                    @if ($proveedorSeleccionado->estado)
                                        <a
                                            href="{{ route('catalogos.importar', ['idproveedor' => $proveedorSeleccionado->idproveedor]) }}"
                                            class="boton boton--primario boton-catalogo-proveedor"
                                        >
                                            <span aria-hidden="true">↑</span>
                                            Cargar catálogo
                                        </a>
                                    @else
                                        <span
                                            class="boton boton--secundario boton-catalogo-proveedor boton-catalogo-proveedor--deshabilitado"
                                            aria-disabled="true"
                                        >
                                            Cargar catálogo
                                        </span>
                                    @endif
                                @endif

                                @if ($puedeVerCatalogos)
                                    <a
                                        href="{{ route('productos.catalogo', [
                                            'idproveedor' => $proveedorSeleccionado->idproveedor,
                                        ]) }}"
                                        class="boton boton--secundario boton-catalogo-proveedor"
                                    >
                                        <span aria-hidden="true">◉</span>
                                        Ver catálogo
                                    </a>
                                @endif
                            </div>

                            <small>
                                @if (!$proveedorSeleccionado->estado && $puedeImportarCatalogos)
                                    El proveedor está inactivo: podés consultar su historial, pero no cargar una lista nueva.
                                @elseif ($puedeVerCatalogos)
                                    Consultá la lista vigente y las versiones históricas de este proveedor.
                                @else
                                    El proveedor quedará preseleccionado en el importador.
                                @endif
                            </small>
                        </div>
                    @endif

                    @if ($puedeEditarProveedores)
                        <form
                            action="{{ route('proveedores.update', ['proveedor' => $proveedorSeleccionado->idproveedor]) }}"
                            method="POST"
                            id="formulario-editar-proveedor"
                            class="formulario-proveedor"
                            data-proveedor-estaba-activo="{{ $proveedorSeleccionado->estado ? '1' : '0' }}"
                            data-nombre-proveedor="{{ $proveedorSeleccionado->razonsocial }}"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_formulario" value="editar">
                            <input type="hidden" name="_proveedor_id" value="{{ $proveedorSeleccionado->idproveedor }}">

                            <div class="formulario-proveedor__grilla">
                                <div class="campo formulario-proveedor__campo-completo">
                                    <label for="razonsocial-editar">Razón social *</label>
                                    <input
                                        type="text"
                                        id="razonsocial-editar"
                                        name="razonsocial"
                                        value="{{ $edicionConErroresDelProveedorActual ? old('razonsocial') : $proveedorSeleccionado->razonsocial }}"
                                        maxlength="150"
                                        required
                                    >
                                </div>

                                <div class="campo">
                                    <label for="cuit-editar">CUIT</label>
                                    <input
                                        type="text"
                                        id="cuit-editar"
                                        name="cuit"
                                        value="{{ $edicionConErroresDelProveedorActual ? old('cuit') : $proveedorSeleccionado->cuit }}"
                                        inputmode="numeric"
                                        maxlength="13"
                                        placeholder="30-12345678-9"
                                        data-cuit
                                        @if ($edicionConErroresDelProveedorActual)
                                            @error('cuit') aria-invalid="true" @enderror
                                        @endif
                                    >
                                    @if ($edicionConErroresDelProveedorActual)
                                        @error('cuit')
                                            <small class="campo__error">{{ $message }}</small>
                                        @enderror
                                    @endif
                                </div>

                                <div class="campo">
                                    <label for="contacto-editar">Persona de contacto</label>
                                    <input
                                        type="text"
                                        id="contacto-editar"
                                        name="contacto"
                                        value="{{ $edicionConErroresDelProveedorActual ? old('contacto') : $proveedorSeleccionado->contacto }}"
                                        maxlength="100"
                                        data-solo-letras
                                    >
                                </div>

                                <div class="campo">
                                    <label for="telefono-editar">Teléfono</label>
                                    <input
                                        type="tel"
                                        id="telefono-editar"
                                        name="telefono"
                                        value="{{ $edicionConErroresDelProveedorActual ? old('telefono') : $proveedorSeleccionado->telefono }}"
                                        inputmode="numeric"
                                        maxlength="20"
                                        data-solo-numeros
                                    >
                                </div>

                                <div class="campo">
                                    <label for="email-editar">Correo electrónico</label>
                                    <input
                                        type="email"
                                        id="email-editar"
                                        name="email"
                                        value="{{ $edicionConErroresDelProveedorActual ? old('email') : $proveedorSeleccionado->email }}"
                                        maxlength="150"
                                        data-correo
                                    >
                                </div>

                                <div class="campo formulario-proveedor__campo-completo">
                                    <label for="direccion-editar">Dirección</label>
                                    <input
                                        type="text"
                                        id="direccion-editar"
                                        name="direccion"
                                        value="{{ $edicionConErroresDelProveedorActual ? old('direccion') : $proveedorSeleccionado->direccion }}"
                                        maxlength="200"
                                    >
                                </div>

                                <div class="campo formulario-proveedor__campo-completo">
                                    <span class="campo__etiqueta">Estado del proveedor</span>
                                    <label class="interruptor-proveedor">
                                        <input type="hidden" name="estado" value="0">
                                        <input
                                            type="checkbox"
                                            id="estado-editar"
                                            name="estado"
                                            value="1"
                                            @checked(
                                                $edicionConErroresDelProveedorActual
                                                    ? (string) old('estado') === '1'
                                                    : $proveedorSeleccionado->estado
                                            )
                                        >
                                        <span class="interruptor-proveedor__control" aria-hidden="true"></span>
                                        <span class="interruptor-proveedor__texto">Proveedor habilitado</span>
                                    </label>
                                </div>
                            </div>

                            <footer class="formulario-proveedor__pie">
                                <span>
                                    Última actualización:
                                    {{ $proveedorSeleccionado->updated_at?->format('d/m/Y H:i') ?? 'Sin datos' }}
                                </span>
                                <button type="submit" class="boton boton--primario">Guardar cambios</button>
                            </footer>
                        </form>
                    @else
                        <div class="perfil-proveedor-consulta">
                            <div><span>CUIT</span><strong>{{ $proveedorSeleccionado->cuit ?: 'Sin cargar' }}</strong></div>
                            <div><span>Contacto</span><strong>{{ $proveedorSeleccionado->contacto ?: 'Sin cargar' }}</strong></div>
                            <div><span>Teléfono</span><strong>{{ $proveedorSeleccionado->telefono ?: 'Sin cargar' }}</strong></div>
                            <div><span>Dirección</span><strong>{{ $proveedorSeleccionado->direccion ?: 'Sin cargar' }}</strong></div>
                        </div>
                    @endif
                @endif
            </aside>
        </div>
    </main>

    @if ($puedeCrearProveedores)
        <dialog
            id="modal-nuevo-proveedor"
            class="modal-proveedor"
            data-abrir-por-error="{{ $esFormularioCreacion && $errors->any() ? '1' : '0' }}"
            aria-labelledby="titulo-modal-nuevo-proveedor"
        >
            <form
                action="{{ route('proveedores.store') }}"
                method="POST"
                class="modal-proveedor__contenido"
            >
                @csrf
                <input type="hidden" name="_formulario" value="crear">

                <header class="modal-proveedor__cabecera">
                    <div>
                        <span>Alta de proveedor</span>
                        <h2 id="titulo-modal-nuevo-proveedor">Nuevo proveedor</h2>
                        <p>Registralo para poder trabajar luego con sus listas y catálogos.</p>
                    </div>
                    <button type="button" class="modal-proveedor__cerrar" data-cerrar-modal aria-label="Cerrar ventana">×</button>
                </header>

                <div class="modal-proveedor__cuerpo">
                    @if ($esFormularioCreacion && $errors->any())
                        <section class="alerta alerta--error alerta--lista modal-proveedor__errores" role="alert">
                            <div>
                                <strong>No pudimos crear el proveedor.</strong>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>
                    @endif

                    <div class="modal-proveedor__seccion">
                        <div class="modal-proveedor__titulo-seccion">
                            <span>01</span>
                            <div>
                                <strong>Datos principales</strong>
                                <small>Identificación comercial del proveedor.</small>
                            </div>
                        </div>

                        <div class="formulario-nuevo-proveedor">
                            <div class="campo formulario-nuevo-proveedor__campo-completo">
                                <label for="razonsocial-crear">Razón social *</label>
                                <input
                                    type="text"
                                    id="razonsocial-crear"
                                    name="razonsocial"
                                    value="{{ $esFormularioCreacion ? old('razonsocial') : '' }}"
                                    maxlength="150"
                                    required
                                    @if ($esFormularioCreacion)
                                        @error('razonsocial') aria-invalid="true" @enderror
                                    @endif
                                >
                                @if ($esFormularioCreacion)
                                    @error('razonsocial')
                                        <small class="campo__error">{{ $message }}</small>
                                    @enderror
                                @endif
                            </div>

                            <div class="campo">
                                <label for="cuit-crear">CUIT</label>
                                <input
                                    type="text"
                                    id="cuit-crear"
                                    name="cuit"
                                    value="{{ $esFormularioCreacion ? old('cuit') : '' }}"
                                    inputmode="numeric"
                                    maxlength="13"
                                    placeholder="30-12345678-9"
                                    data-cuit
                                    @if ($esFormularioCreacion)
                                        @error('cuit') aria-invalid="true" @enderror
                                    @endif
                                >
                                @if ($esFormularioCreacion)
                                    @error('cuit')
                                        <small class="campo__error">{{ $message }}</small>
                                    @enderror
                                @endif
                            </div>

                            <div class="campo">
                                <label for="contacto-crear">Persona de contacto</label>
                                <input
                                    type="text"
                                    id="contacto-crear"
                                    name="contacto"
                                    value="{{ $esFormularioCreacion ? old('contacto') : '' }}"
                                    maxlength="100"
                                    data-solo-letras
                                >
                            </div>
                        </div>
                    </div>

                    <div class="modal-proveedor__seccion">
                        <div class="modal-proveedor__titulo-seccion">
                            <span>02</span>
                            <div>
                                <strong>Contacto</strong>
                                <small>Información para comunicarse con el proveedor.</small>
                            </div>
                        </div>

                        <div class="formulario-nuevo-proveedor">
                            <div class="campo">
                                <label for="telefono-crear">Teléfono</label>
                                <input
                                    type="tel"
                                    id="telefono-crear"
                                    name="telefono"
                                    value="{{ $esFormularioCreacion ? old('telefono') : '' }}"
                                    inputmode="numeric"
                                    maxlength="20"
                                    data-solo-numeros
                                >
                            </div>

                            <div class="campo">
                                <label for="email-crear">Correo electrónico</label>
                                <input
                                    type="email"
                                    id="email-crear"
                                    name="email"
                                    value="{{ $esFormularioCreacion ? old('email') : '' }}"
                                    maxlength="150"
                                    data-correo
                                    @if ($esFormularioCreacion)
                                        @error('email') aria-invalid="true" @enderror
                                    @endif
                                >
                                @if ($esFormularioCreacion)
                                    @error('email')
                                        <small class="campo__error">{{ $message }}</small>
                                    @enderror
                                @endif
                            </div>

                            <div class="campo formulario-nuevo-proveedor__campo-completo">
                                <label for="direccion-crear">Dirección</label>
                                <input
                                    type="text"
                                    id="direccion-crear"
                                    name="direccion"
                                    value="{{ $esFormularioCreacion ? old('direccion') : '' }}"
                                    maxlength="200"
                                >
                            </div>

                            <div class="campo formulario-nuevo-proveedor__campo-completo campo--estado-proveedor-nuevo">
                                <span class="campo__etiqueta">Estado inicial</span>
                                <label class="interruptor-proveedor">
                                    <input type="hidden" name="estado" value="0">
                                    <input
                                        type="checkbox"
                                        id="estado-crear"
                                        name="estado"
                                        value="1"
                                        @checked(!$esFormularioCreacion || (string) old('estado', '1') === '1')
                                    >
                                    <span class="interruptor-proveedor__control" aria-hidden="true"></span>
                                    <span class="interruptor-proveedor__texto">Crear como activo</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="modal-proveedor__pie">
                    <button type="button" class="boton boton--secundario" data-cerrar-modal>Cancelar</button>
                    <button type="submit" class="boton boton--primario">Crear proveedor</button>
                </footer>
            </form>
        </dialog>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('modal-nuevo-proveedor');
            const botonAbrir = document.getElementById('boton-abrir-nuevo-proveedor');
            const formularioFiltros = document.getElementById('formulario-filtros-proveedores');
            const estadoFiltros = document.getElementById('estado-filtros-proveedores');
            const formularioEditar = document.getElementById('formulario-editar-proveedor');

            document.querySelectorAll('[data-solo-letras]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.value = campo.value.replace(/[^\p{L}\p{M}\s'-]/gu, '');
                });
            });

            document.querySelectorAll('[data-solo-numeros]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.value = campo.value.replace(/\D/g, '').slice(0, 20);
                });
            });

            document.querySelectorAll('[data-correo]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.value = campo.value.replace(/\s/g, '').toLowerCase();
                });
            });

            document.querySelectorAll('[data-cuit]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    const digitos = campo.value.replace(/\D/g, '').slice(0, 11);
                    let valor = digitos;

                    if (digitos.length > 2 && digitos.length <= 10) {
                        valor = digitos.slice(0, 2) + '-' + digitos.slice(2);
                    } else if (digitos.length === 11) {
                        valor = digitos.slice(0, 2)
                            + '-'
                            + digitos.slice(2, 10)
                            + '-'
                            + digitos.slice(10, 11);
                    }

                    campo.value = valor;
                });
            });

            if (formularioFiltros) {
                const campoBusqueda = formularioFiltros.querySelector('#buscar');
                const selectores = formularioFiltros.querySelectorAll('select');
                let temporizador;

                function mostrarEstado(mensaje) {
                    if (estadoFiltros) estadoFiltros.textContent = mensaje;
                }

                function enviar(restaurarFoco) {
                    window.clearTimeout(temporizador);

                    if (restaurarFoco) {
                        window.sessionStorage.setItem('proveedores-restaurar-busqueda', '1');
                    } else {
                        window.sessionStorage.removeItem('proveedores-restaurar-busqueda');
                    }

                    mostrarEstado('Buscando...');
                    formularioFiltros.requestSubmit();
                }

                if (campoBusqueda) {
                    campoBusqueda.addEventListener('input', function () {
                        window.clearTimeout(temporizador);
                        mostrarEstado('Esperando que termines de escribir...');
                        temporizador = window.setTimeout(function () {
                            enviar(true);
                        }, 500);
                    });

                    if (window.sessionStorage.getItem('proveedores-restaurar-busqueda') === '1') {
                        window.sessionStorage.removeItem('proveedores-restaurar-busqueda');
                        campoBusqueda.focus();
                        campoBusqueda.setSelectionRange(
                            campoBusqueda.value.length,
                            campoBusqueda.value.length
                        );
                    }
                }

                selectores.forEach(function (selector) {
                    selector.addEventListener('change', function () {
                        enviar(false);
                    });
                });
            }

            function abrirModal() {
                if (!modal) return;
                if (typeof modal.showModal === 'function') {
                    if (!modal.open) modal.showModal();
                } else {
                    modal.setAttribute('open', 'open');
                }
            }

            function cerrarModal() {
                if (!modal) return;
                if (typeof modal.close === 'function') modal.close();
                else modal.removeAttribute('open');
            }

            if (botonAbrir) botonAbrir.addEventListener('click', abrirModal);

            document.querySelectorAll('[data-cerrar-modal]').forEach(function (boton) {
                boton.addEventListener('click', cerrarModal);
            });

            if (modal) {
                modal.addEventListener('click', function (evento) {
                    if (evento.target === modal) cerrarModal();
                });

                if (modal.dataset.abrirPorError === '1') {
                    abrirModal();
                    window.setTimeout(function () {
                        const campoConError = modal.querySelector('[aria-invalid="true"]');
                        if (campoConError) campoConError.focus();
                    }, 0);
                }
            }

            if (formularioEditar) {
                formularioEditar.addEventListener('submit', function (evento) {
                    const estado = formularioEditar.querySelector('#estado-editar');
                    const estabaActivo = formularioEditar.dataset.proveedorEstabaActivo === '1';

                    if (estado && estabaActivo && !estado.checked) {
                        const nombre = formularioEditar.dataset.nombreProveedor;
                        const confirmado = window.confirm(
                            'Vas a inactivar al proveedor "' + nombre
                            + '". Su información e historial se conservarán. ¿Querés continuar?'
                        );

                        if (!confirmado) evento.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>
