<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Roles y permisos | Messirve</title>

    {{-- Base visual compartida con las pantallas de Productos. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/productos/catalogo.css') }}"
    >

    {{-- Estilos exclusivos del módulo de Roles. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/roles/index.css') }}"
    >

    {{-- Barra lateral del panel administrativo. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/admin/barra-lateral.css') }}"
    >
</head>

<body class="con-barra-lateral">

    @php
        $cantidadRoles = $roles->count();
        $cantidadRolesActivos = $roles->where('estado', true)->count();
        $cantidadUsuariosAsignados = $roles->sum('usuarios_count');

        $esFormularioCreacion = old('_formulario') === 'crear';
        $esFormularioEdicion = old('_formulario') === 'editar';

        $esRolDueno = $rolSeleccionado
            && (
                (int) $rolSeleccionado->idrol === 1
                || mb_strtoupper($rolSeleccionado->nombre, 'UTF-8') === 'DUEÑO'
            );

        $edicionConErroresDelRolActual = $esFormularioEdicion
            && $rolSeleccionado
            && (int) old('_rol_id') === (int) $rolSeleccionado->idrol;

        $permisosMarcadosEdicion = $edicionConErroresDelRolActual
            ? collect(old('permisos', []))->map(fn ($id) => (int) $id)
            : ($rolSeleccionado?->permisos->pluck('idpermiso')->map(fn ($id) => (int) $id) ?? collect());

        $permisosMarcadosCreacion = $esFormularioCreacion
            ? collect(old('permisos', []))->map(fn ($id) => (int) $id)
            : collect();

        $nombresModulos = [
            'productos' => 'Productos',
            'catalogos' => 'Catálogos',
            'proveedores' => 'Proveedores',
            'usuarios' => 'Usuarios',
            'roles' => 'Roles y permisos',
        ];

        $permisosAgrupados = $permisos->groupBy(
            fn ($permiso) => explode('.', $permiso->codigo, 2)[0]
        );
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos pagina-roles">

        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">
                    Administración de seguridad
                </p>

                <h1>Roles y permisos</h1>

                <p class="encabezado-principal__descripcion">
                    Creá perfiles de acceso y definí qué acciones puede realizar
                    cada tipo de empleado dentro del sistema.
                </p>
            </div>

            <div class="encabezado-principal__acciones">
                <button
                    type="button"
                    id="boton-abrir-nuevo-rol"
                    class="boton boton--primario"
                >
                    <span class="boton__simbolo" aria-hidden="true">+</span>
                    Nuevo rol
                </button>
            </div>
        </header>

        @if (session('success'))
            <section class="alerta alerta--exito" role="status">
                <strong>Operación realizada.</strong>
                <span>{{ session('success') }}</span>
            </section>
        @endif

        @if (session('error'))
            <section class="alerta alerta--error" role="alert">
                <strong>No se pudo continuar.</strong>
                <span>{{ session('error') }}</span>
            </section>
        @endif

        @if ($errors->any())
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

        <section class="resumen resumen--roles">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Roles registrados</span>

                <strong class="resumen__valor">
                    {{ number_format($cantidadRoles, 0, ',', '.') }}
                </strong>

                <small>
                    {{ $cantidadRolesActivos }} activos en este momento
                </small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Usuarios asignados</span>

                <strong class="resumen__valor">
                    {{ number_format($cantidadUsuariosAsignados, 0, ',', '.') }}
                </strong>

                <small>Usuarios internos con un rol definido</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa">
                <span class="resumen__icono" aria-hidden="true">i</span>

                <div>
                    <strong>Los permisos se heredan desde el rol</strong>

                    <p>
                        Al modificar un rol, el cambio se aplica automáticamente
                        a todos los usuarios que lo tengan asignado.
                    </p>
                </div>
            </article>
        </section>

        <div class="roles-distribucion">
            <section class="panel panel--lista-roles">
                <div class="panel__cabecera panel__cabecera--roles">
                    <div>
                        <span class="panel__numero">01</span>

                        <div>
                            <h2>Roles del sistema</h2>

                            <p>Seleccioná un rol para revisar su configuración.</p>
                        </div>
                    </div>

                    <span class="resultado-cantidad">
                        {{ $cantidadRoles }} {{ $cantidadRoles === 1 ? 'rol' : 'roles' }}
                    </span>
                </div>

                @if ($roles->isEmpty())
                    <div class="roles-vacio">
                        <strong>No hay roles registrados</strong>
                        <p>Creá el primer rol para comenzar a asignar permisos.</p>
                    </div>
                @else
                    <nav class="lista-roles" aria-label="Roles disponibles">
                        @foreach ($roles as $rol)
                            @php
                                $rolEsDueno = (int) $rol->idrol === 1
                                    || mb_strtoupper($rol->nombre, 'UTF-8') === 'DUEÑO';

                                $rolEstaSeleccionado = $rolSeleccionado
                                    && (int) $rol->idrol === (int) $rolSeleccionado->idrol;
                            @endphp

                            <a
                                href="{{ route('roles.index', ['rol' => $rol->idrol]) }}"
                                class="tarjeta-rol{{ $rolEstaSeleccionado ? ' tarjeta-rol--activa' : '' }}"
                                @if ($rolEstaSeleccionado) aria-current="page" @endif
                            >
                                <span class="tarjeta-rol__icono" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M12 2 3 6v6c0 5.5 3.8 9.7 9 10 5.2-.3 9-4.5 9-10V6l-9-4Zm0 3.2 6 2.6V12c0 3.8-2.4 6.7-6 7-3.6-.3-6-3.2-6-7V7.8l6-2.6Z"/>
                                    </svg>
                                </span>

                                <span class="tarjeta-rol__contenido">
                                    <span class="tarjeta-rol__nombre">
                                        {{ $rol->nombre }}

                                        @if ($rolEsDueno)
                                            <span class="insignia insignia--protegido">
                                                Protegido
                                            </span>
                                        @endif
                                    </span>

                                    <span class="tarjeta-rol__descripcion">
                                        {{ $rol->descripcion ?: 'Sin descripción.' }}
                                    </span>

                                    <span class="tarjeta-rol__datos">
                                        <span>
                                            {{ $rol->permisos->count() }}
                                            {{ $rol->permisos->count() === 1 ? 'permiso' : 'permisos' }}
                                        </span>

                                        <span aria-hidden="true">•</span>

                                        <span>
                                            {{ $rol->usuarios_count }}
                                            {{ $rol->usuarios_count === 1 ? 'usuario' : 'usuarios' }}
                                        </span>
                                    </span>
                                </span>

                                <span
                                    class="estado-rol {{ $rol->estado ? 'estado-rol--activo' : 'estado-rol--inactivo' }}"
                                >
                                    {{ $rol->estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </a>
                        @endforeach
                    </nav>
                @endif
            </section>

            <section class="panel panel--detalle-rol">
                <div class="panel__cabecera panel__cabecera--roles">
                    <div>
                        <span class="panel__numero">02</span>

                        <div>
                            <h2>Configuración del rol</h2>

                            <p>
                                {{ $esRolDueno
                                    ? 'El rol principal se muestra en modo de consulta.'
                                    : 'Modificá sus datos y permisos habilitados.' }}
                            </p>
                        </div>
                    </div>

                    @if ($rolSeleccionado)
                        <span
                            class="estado-rol {{ $rolSeleccionado->estado ? 'estado-rol--activo' : 'estado-rol--inactivo' }}"
                        >
                            {{ $rolSeleccionado->estado ? 'Activo' : 'Inactivo' }}
                        </span>
                    @endif
                </div>

                @if (!$rolSeleccionado)
                    <div class="roles-vacio roles-vacio--grande">
                        <strong>Seleccioná un rol</strong>
                        <p>Elegí un rol de la lista para consultar sus permisos.</p>
                    </div>
                @elseif ($esRolDueno)
                    <div class="detalle-rol detalle-rol--protegido">
                        <div class="aviso-protegido">
                            <span class="aviso-protegido__icono" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M12 2 3 6v6c0 5.5 3.8 9.7 9 10 5.2-.3 9-4.5 9-10V6l-9-4Zm0 4.1 5 2.2V12c0 3-1.8 5.4-5 5.9C8.8 17.4 7 15 7 12V8.3l5-2.2Zm-1 3v4.8l4-2.4-4-2.4Z"/>
                                </svg>
                            </span>

                            <div>
                                <strong>Rol principal protegido</strong>
                                <p>
                                    DUEÑO conserva el acceso completo y no puede
                                    desactivarse ni modificarse accidentalmente.
                                </p>
                            </div>
                        </div>

                        <div class="datos-rol-protegido">
                            <div>
                                <span>Nombre del rol</span>
                                <strong>{{ $rolSeleccionado->nombre }}</strong>
                            </div>

                            <div>
                                <span>Usuarios asignados</span>
                                <strong>{{ $rolSeleccionado->usuarios_count }}</strong>
                            </div>

                            <div>
                                <span>Estado</span>
                                <strong>Activo</strong>
                            </div>
                        </div>

                        <div class="seccion-permisos">
                            <div class="seccion-permisos__encabezado">
                                <div>
                                    <h3>Permisos incluidos</h3>
                                    <p>Acceso completo a todas las funciones registradas.</p>
                                </div>

                                <span class="contador-permisos">
                                    {{ $rolSeleccionado->permisos->count() }} de {{ $permisos->count() }}
                                </span>
                            </div>

                            <div class="permisos-grilla permisos-grilla--consulta">
                                @foreach ($permisosAgrupados as $codigoGrupo => $permisosDelGrupo)
                                    <section class="grupo-permisos">
                                        <div class="grupo-permisos__titulo">
                                            <strong>
                                                {{ $nombresModulos[$codigoGrupo] ?? ucfirst($codigoGrupo) }}
                                            </strong>

                                            <span>{{ $permisosDelGrupo->count() }}</span>
                                        </div>

                                        <div class="grupo-permisos__opciones">
                                            @foreach ($permisosDelGrupo as $permiso)
                                                <div class="permiso-opcion permiso-opcion--consulta">
                                                    <span class="permiso-opcion__marca" aria-hidden="true">✓</span>

                                                    <span class="permiso-opcion__texto">
                                                        <strong>{{ $permiso->nombre }}</strong>
                                                        <small>{{ $permiso->descripcion }}</small>
                                                        <code>{{ $permiso->codigo }}</code>
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <form
                        action="{{ route('roles.update', ['rol' => $rolSeleccionado->idrol]) }}"
                        method="POST"
                        id="formulario-editar-rol"
                        class="detalle-rol"
                        data-usuarios-asignados="{{ $rolSeleccionado->usuarios_count }}"
                        data-rol-estaba-activo="{{ $rolSeleccionado->estado ? '1' : '0' }}"
                    >
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="_formulario" value="editar">
                        <input
                            type="hidden"
                            name="_rol_id"
                            value="{{ $rolSeleccionado->idrol }}"
                        >

                        <div class="formulario-rol__datos">
                            <div class="campo">
                                <label for="nombre-editar">Nombre del rol *</label>

                                <input
                                    type="text"
                                    id="nombre-editar"
                                    name="nombre"
                                    value="{{ $edicionConErroresDelRolActual ? old('nombre') : $rolSeleccionado->nombre }}"
                                    maxlength="50"
                                    autocomplete="off"
                                    required
                                >
                            </div>

                            <div class="campo campo--descripcion">
                                <label for="descripcion-editar">Descripción</label>

                                <textarea
                                    id="descripcion-editar"
                                    name="descripcion"
                                    maxlength="150"
                                    rows="3"
                                    placeholder="Ejemplo: administra productos y catálogos"
                                >{{ $edicionConErroresDelRolActual ? old('descripcion') : $rolSeleccionado->descripcion }}</textarea>

                                <small>Máximo 150 caracteres.</small>
                            </div>

                            <div class="campo campo--estado">
                                <span class="campo__etiqueta">Estado del rol</span>

                                <label class="interruptor">
                                    <input type="hidden" name="estado" value="0">

                                    <input
                                        type="checkbox"
                                        id="estado-editar"
                                        name="estado"
                                        value="1"
                                        @checked(
                                            $edicionConErroresDelRolActual
                                                ? (string) old('estado') === '1'
                                                : $rolSeleccionado->estado
                                        )
                                    >

                                    <span class="interruptor__control" aria-hidden="true"></span>
                                    <span class="interruptor__texto">Rol habilitado</span>
                                </label>
                            </div>
                        </div>

                        <div class="seccion-permisos">
                            <div class="seccion-permisos__encabezado">
                                <div>
                                    <h3>Permisos del rol</h3>
                                    <p>Seleccioná al menos una acción permitida.</p>
                                </div>

                                <span class="contador-permisos" data-contador-permisos>
                                    0 de {{ $permisos->count() }}
                                </span>
                            </div>

                            <div class="permisos-grilla">
                                @foreach ($permisosAgrupados as $codigoGrupo => $permisosDelGrupo)
                                    <fieldset class="grupo-permisos" data-grupo-permisos>
                                        <legend class="grupo-permisos__titulo">
                                            <span>
                                                {{ $nombresModulos[$codigoGrupo] ?? ucfirst($codigoGrupo) }}
                                            </span>

                                            <button
                                                type="button"
                                                class="boton-seleccionar-grupo"
                                                data-seleccionar-grupo
                                            >
                                                Seleccionar todos
                                            </button>
                                        </legend>

                                        <div class="grupo-permisos__opciones">
                                            @foreach ($permisosDelGrupo as $permiso)
                                                <label class="permiso-opcion">
                                                    <input
                                                        type="checkbox"
                                                        name="permisos[]"
                                                        value="{{ $permiso->idpermiso }}"
                                                        @checked($permisosMarcadosEdicion->contains((int) $permiso->idpermiso))
                                                    >

                                                    <span class="permiso-opcion__marca" aria-hidden="true">✓</span>

                                                    <span class="permiso-opcion__texto">
                                                        <strong>{{ $permiso->nombre }}</strong>
                                                        <small>{{ $permiso->descripcion }}</small>
                                                        <code>{{ $permiso->codigo }}</code>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                        </div>

                        <footer class="detalle-rol__pie">
                            <p>
                                Este rol tiene
                                <strong>{{ $rolSeleccionado->usuarios_count }}</strong>
                                {{ $rolSeleccionado->usuarios_count === 1 ? 'usuario asignado' : 'usuarios asignados' }}.
                            </p>

                            <button type="submit" class="boton boton--primario">
                                Guardar cambios
                            </button>
                        </footer>
                    </form>
                @endif
            </section>
        </div>
    </main>

    <dialog
        id="modal-nuevo-rol"
        class="modal-rol"
        data-abrir-por-error="{{ $esFormularioCreacion && $errors->any() ? '1' : '0' }}"
        aria-labelledby="titulo-modal-nuevo-rol"
    >
        <form
            action="{{ route('roles.store') }}"
            method="POST"
            class="modal-rol__contenido"
        >
            @csrf

            <input type="hidden" name="_formulario" value="crear">

            <header class="modal-rol__cabecera">
                <div>
                    <span class="modal-rol__paso">Nuevo perfil de acceso</span>
                    <h2 id="titulo-modal-nuevo-rol">Crear un rol</h2>
                    <p>Definí el nombre y las acciones que tendrá permitidas.</p>
                </div>

                <button
                    type="button"
                    class="modal-rol__cerrar"
                    data-cerrar-modal
                    aria-label="Cerrar ventana"
                >
                    ×
                </button>
            </header>

            <div class="modal-rol__cuerpo">
                @if ($esFormularioCreacion && $errors->any())
                    <div class="errores-modal" role="alert">
                        <strong>No pudimos crear el rol.</strong>
                        <span>Revisá los campos marcados y volvé a intentarlo.</span>
                    </div>
                @endif

                <div class="formulario-rol__datos formulario-rol__datos--modal">
                    <div class="campo">
                        <label for="nombre-crear">Nombre del rol *</label>

                        <input
                            type="text"
                            id="nombre-crear"
                            name="nombre"
                            value="{{ $esFormularioCreacion ? old('nombre') : '' }}"
                            maxlength="50"
                            placeholder="Ejemplo: ENCARGADO DE DEPÓSITO"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="campo campo--descripcion">
                        <label for="descripcion-crear">Descripción</label>

                        <textarea
                            id="descripcion-crear"
                            name="descripcion"
                            maxlength="150"
                            rows="3"
                            placeholder="Explicá brevemente qué tareas realiza este rol"
                        >{{ $esFormularioCreacion ? old('descripcion') : '' }}</textarea>

                        <small>Máximo 150 caracteres.</small>
                    </div>

                    <div class="campo campo--estado">
                        <span class="campo__etiqueta">Estado inicial</span>

                        <label class="interruptor">
                            <input type="hidden" name="estado" value="0">

                            <input
                                type="checkbox"
                                id="estado-crear"
                                name="estado"
                                value="1"
                                @checked(!$esFormularioCreacion || (string) old('estado', '1') === '1')
                            >

                            <span class="interruptor__control" aria-hidden="true"></span>
                            <span class="interruptor__texto">Crear como activo</span>
                        </label>
                    </div>
                </div>

                <div class="seccion-permisos seccion-permisos--modal">
                    <div class="seccion-permisos__encabezado">
                        <div>
                            <h3>Permisos iniciales *</h3>
                            <p>Podrás modificarlos más adelante.</p>
                        </div>

                        <span class="contador-permisos" data-contador-permisos>
                            0 de {{ $permisos->count() }}
                        </span>
                    </div>

                    <div class="permisos-grilla">
                        @foreach ($permisosAgrupados as $codigoGrupo => $permisosDelGrupo)
                            <fieldset class="grupo-permisos" data-grupo-permisos>
                                <legend class="grupo-permisos__titulo">
                                    <span>
                                        {{ $nombresModulos[$codigoGrupo] ?? ucfirst($codigoGrupo) }}
                                    </span>

                                    <button
                                        type="button"
                                        class="boton-seleccionar-grupo"
                                        data-seleccionar-grupo
                                    >
                                        Seleccionar todos
                                    </button>
                                </legend>

                                <div class="grupo-permisos__opciones">
                                    @foreach ($permisosDelGrupo as $permiso)
                                        <label class="permiso-opcion">
                                            <input
                                                type="checkbox"
                                                name="permisos[]"
                                                value="{{ $permiso->idpermiso }}"
                                                @checked($permisosMarcadosCreacion->contains((int) $permiso->idpermiso))
                                            >

                                            <span class="permiso-opcion__marca" aria-hidden="true">✓</span>

                                            <span class="permiso-opcion__texto">
                                                <strong>{{ $permiso->nombre }}</strong>
                                                <small>{{ $permiso->descripcion }}</small>
                                                <code>{{ $permiso->codigo }}</code>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                </div>
            </div>

            <footer class="modal-rol__pie">
                <button
                    type="button"
                    class="boton boton--secundario"
                    data-cerrar-modal
                >
                    Cancelar
                </button>

                <button type="submit" class="boton boton--primario">
                    Crear rol
                </button>
            </footer>
        </form>
    </dialog>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('modal-nuevo-rol');
            const botonAbrir = document.getElementById('boton-abrir-nuevo-rol');
            const formularioEditar = document.getElementById('formulario-editar-rol');

            function abrirModal() {
                if (!modal) {
                    return;
                }

                if (typeof modal.showModal === 'function') {
                    if (!modal.open) {
                        modal.showModal();
                    }
                } else {
                    modal.setAttribute('open', 'open');
                }
            }

            function cerrarModal() {
                if (!modal) {
                    return;
                }

                if (typeof modal.close === 'function') {
                    modal.close();
                } else {
                    modal.removeAttribute('open');
                }
            }

            if (botonAbrir) {
                botonAbrir.addEventListener('click', abrirModal);
            }

            document.querySelectorAll('[data-cerrar-modal]').forEach(function (boton) {
                boton.addEventListener('click', cerrarModal);
            });

            if (modal) {
                modal.addEventListener('click', function (evento) {
                    if (evento.target === modal) {
                        cerrarModal();
                    }
                });

                if (modal.dataset.abrirPorError === '1') {
                    abrirModal();
                }
            }

            document.querySelectorAll('form').forEach(function (formulario) {
                const contador = formulario.querySelector('[data-contador-permisos]');
                const permisos = Array.from(
                    formulario.querySelectorAll('input[name="permisos[]"]')
                );

                if (!contador || permisos.length === 0) {
                    return;
                }

                function actualizarContador() {
                    const seleccionados = permisos.filter(function (permiso) {
                        return permiso.checked;
                    }).length;

                    contador.textContent = seleccionados + ' de ' + permisos.length;
                }

                formulario.querySelectorAll('[data-grupo-permisos]').forEach(function (grupo) {
                    const boton = grupo.querySelector('[data-seleccionar-grupo]');
                    const permisosDelGrupo = Array.from(
                        grupo.querySelectorAll('input[name="permisos[]"]')
                    );

                    if (!boton || permisosDelGrupo.length === 0) {
                        return;
                    }

                    function actualizarBotonGrupo() {
                        const todosMarcados = permisosDelGrupo.every(function (permiso) {
                            return permiso.checked;
                        });

                        boton.textContent = todosMarcados
                            ? 'Quitar todos'
                            : 'Seleccionar todos';
                    }

                    boton.addEventListener('click', function () {
                        const todosMarcados = permisosDelGrupo.every(function (permiso) {
                            return permiso.checked;
                        });

                        permisosDelGrupo.forEach(function (permiso) {
                            permiso.checked = !todosMarcados;
                        });

                        actualizarBotonGrupo();
                        actualizarContador();
                    });

                    permisosDelGrupo.forEach(function (permiso) {
                        permiso.addEventListener('change', function () {
                            actualizarBotonGrupo();
                            actualizarContador();
                        });
                    });

                    actualizarBotonGrupo();
                });

                actualizarContador();
            });

            if (formularioEditar) {
                formularioEditar.addEventListener('submit', function (evento) {
                    const estado = formularioEditar.querySelector('#estado-editar');
                    const usuarios = Number(
                        formularioEditar.dataset.usuariosAsignados || 0
                    );
                    const estabaActivo =
                        formularioEditar.dataset.rolEstabaActivo === '1';

                    if (
                        estado
                        && estabaActivo
                        && !estado.checked
                        && usuarios > 0
                    ) {
                        const confirmado = window.confirm(
                            'Este rol tiene ' + usuarios
                            + (usuarios === 1 ? ' usuario asignado. ' : ' usuarios asignados. ')
                            + 'Si lo desactivás, esas cuentas perderán el acceso. ¿Querés continuar?'
                        );

                        if (!confirmado) {
                            evento.preventDefault();
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
