<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Personal | Messirve</title>

    {{-- Base visual utilizada por los módulos administrativos. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/productos/catalogo.css') }}"
    >

    {{-- Estilos exclusivos de la administración del personal. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/usuarios/index.css') }}"
    >

    {{-- Barra lateral compartida por el panel administrativo. --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/admin/barra-lateral.css') }}"
    >
</head>

<body class="con-barra-lateral">

    @php
        $totalUsuarios = (int) ($resumen->total ?? 0);
        $usuariosActivos = (int) ($resumen->activos ?? 0);
        $usuariosInactivos = (int) ($resumen->inactivos ?? 0);

        $esFormularioCreacion = old('_formulario') === 'crear';
        $esFormularioEdicion = old('_formulario') === 'editar';

        $usuarioSeleccionadoEsDueno = $usuarioSeleccionado
            && (
                (int) $usuarioSeleccionado->id === 1
                || (
                    $usuarioSeleccionado->rol
                    && mb_strtoupper(
                        $usuarioSeleccionado->rol->nombre,
                        'UTF-8'
                    ) === 'DUEÑO'
                )
            );

        $edicionConErroresDelUsuarioActual = $esFormularioEdicion
            && $usuarioSeleccionado
            && (int) old('_usuario_id') === (int) $usuarioSeleccionado->id;

        $hayFiltros = $busqueda !== ''
            || $estado !== 'todos'
            || $idRol !== null
            || $idSucursal !== null;

        $sucursalesActivas = $sucursales
            ->filter(fn ($sucursal) => $sucursal->estado)
            ->values();
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos pagina-usuarios">

        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">
                    Administración del personal
                </p>

                <h1>Personal de Messirve</h1>

                <p class="encabezado-principal__descripcion">
                    Consultá las cuentas internas, su sucursal y el rol que
                    determina las acciones disponibles dentro del sistema.
                </p>
            </div>

            <div class="encabezado-principal__acciones">
                <button
                    type="button"
                    id="boton-abrir-nuevo-usuario"
                    class="boton boton--primario"
                    @disabled($rolesAsignables->isEmpty() || $sucursalesActivas->isEmpty())
                >
                    <span class="boton__simbolo" aria-hidden="true">+</span>
                    Nuevo usuario
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

        @if ($rolesAsignables->isEmpty() || $sucursalesActivas->isEmpty())
            <section class="alerta alerta--advertencia" role="alert">
                <strong>No se pueden registrar usuarios todavía.</strong>
                <span>
                    Necesitás al menos un rol asignable y una sucursal activa.
                </span>
            </section>
        @endif

        <section class="resumen resumen--usuarios">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Personal registrado</span>

                <strong class="resumen__valor">
                    {{ number_format($totalUsuarios, 0, ',', '.') }}
                </strong>

                <small>Total de cuentas internas</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Estado de las cuentas</span>

                <div class="resumen-estados">
                    <span>
                        <strong>{{ $usuariosActivos }}</strong>
                        Activos
                    </span>

                    <span>
                        <strong>{{ $usuariosInactivos }}</strong>
                        Inactivos
                    </span>
                </div>

                <small>El acceso se bloquea al inactivar una cuenta</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa">
                <span class="resumen__icono" aria-hidden="true">i</span>

                <div>
                    <strong>Cada usuario recibe los permisos de su rol</strong>

                    <p>
                        Para cambiar las acciones permitidas modificá el rol.
                        Para cambiar responsabilidades asigná otro rol al usuario.
                    </p>
                </div>
            </article>
        </section>

        <section class="panel panel--filtros-usuarios">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>

                    <div>
                        <h2>Buscar personal</h2>

                        <p>
                            Buscá por nombre, apellido, correo o teléfono y
                            combiná los filtros disponibles.
                        </p>
                    </div>
                </div>
            </div>

            <form
                action="{{ route('usuarios.index') }}"
                method="GET"
                id="formulario-filtros-usuarios"
                class="filtros filtros--usuarios"
            >
                <div class="campo campo--busqueda-usuario">
                    <label for="buscar">Buscar usuario</label>

                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $busqueda }}"
                        placeholder="Ejemplo: Juan, gerente@messirve.com..."
                        maxlength="255"
                        autocomplete="off"
                    >
                </div>

                <div class="campo">
                    <label for="rol">Rol</label>

                    <select id="rol" name="rol">
                        <option value="">Todos los roles</option>

                        @foreach ($roles as $rol)
                            <option
                                value="{{ $rol->idrol }}"
                                @selected($idRol === (int) $rol->idrol)
                            >
                                {{ $rol->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="sucursal">Sucursal</label>

                    <select id="sucursal" name="sucursal">
                        <option value="">Todas las sucursales</option>

                        @foreach ($sucursales as $sucursal)
                            <option
                                value="{{ $sucursal->idsucursal }}"
                                @selected($idSucursal === (int) $sucursal->idsucursal)
                            >
                                {{ $sucursal->nombre }}
                                {{ $sucursal->estado ? '' : '(Inactiva)' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="estado">Estado</label>

                    <select id="estado" name="estado">
                        <option value="todos" @selected($estado === 'todos')>
                            Todos
                        </option>

                        <option value="1" @selected($estado === '1')>
                            Activos
                        </option>

                        <option value="0" @selected($estado === '0')>
                            Inactivos
                        </option>
                    </select>
                </div>

                <div class="filtros__acciones filtros__acciones--usuarios">
                    @if ($hayFiltros)
                        <a
                            href="{{ route('usuarios.index') }}"
                            class="boton boton--secundario"
                        >
                            Limpiar
                        </a>
                    @endif

                    <span
                        id="estado-filtros-usuarios"
                        class="filtros-automaticos"
                        role="status"
                        aria-live="polite"
                    >
                        Búsqueda automática
                    </span>

                    <noscript>
                        <button type="submit" class="boton boton--primario">
                            Buscar
                        </button>
                    </noscript>
                </div>
            </form>
        </section>

        <div class="usuarios-distribucion">
            <section class="panel panel--tabla panel--lista-usuarios">
                <div class="panel__cabecera panel__cabecera--tabla">
                    <div>
                        <span class="panel__numero">02</span>

                        <div>
                            <h2>Personal registrado</h2>

                            <p>
                                Seleccioná una cuenta para consultar o modificar
                                su perfil laboral.
                            </p>
                        </div>
                    </div>

                    <span class="resultado-cantidad">
                        {{ $usuarios->firstItem() ?? 0 }}–{{ $usuarios->lastItem() ?? 0 }}
                        de {{ $usuarios->total() }}
                    </span>
                </div>

                @if ($usuarios->isEmpty())
                    <div class="estado-vacio estado-vacio--usuarios">
                        <span class="estado-vacio__icono" aria-hidden="true">i</span>

                        <h3>No encontramos usuarios</h3>

                        <p>
                            No hay personal que coincida con la búsqueda y los
                            filtros seleccionados.
                        </p>

                        @if ($hayFiltros)
                            <div class="estado-vacio__acciones">
                                <a
                                    href="{{ route('usuarios.index') }}"
                                    class="boton boton--secundario"
                                >
                                    Limpiar filtros
                                </a>
                            </div>
                        @endif
                    </div>
                @else
                    <div
                        class="tabla-contenedor tabla-contenedor--usuarios"
                        tabindex="0"
                        aria-label="Listado desplazable del personal"
                    >
                        <table class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th>Personal</th>
                                    <th>Correo</th>
                                    <th>Sucursal</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Perfil</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($usuarios as $usuario)
                                    @php
                                        $usuarioEsDueno = (int) $usuario->id === 1
                                            || (
                                                $usuario->rol
                                                && mb_strtoupper(
                                                    $usuario->rol->nombre,
                                                    'UTF-8'
                                                ) === 'DUEÑO'
                                            );

                                        $estaSeleccionado = $usuarioSeleccionado
                                            && (int) $usuarioSeleccionado->id === (int) $usuario->id;

                                        $parametrosPerfil = request()->except('page');
                                        $parametrosPerfil['usuario'] = $usuario->id;

                                        $iniciales = mb_strtoupper(
                                            mb_substr($usuario->nombre, 0, 1, 'UTF-8')
                                            . mb_substr($usuario->apellido, 0, 1, 'UTF-8'),
                                            'UTF-8'
                                        );
                                    @endphp

                                    <tr class="{{ $estaSeleccionado ? 'fila-usuario--seleccionada' : '' }}">
                                        <td>
                                            <div class="usuario-identidad">
                                                <span class="usuario-avatar" aria-hidden="true">
                                                    {{ $iniciales }}
                                                </span>

                                                <span class="usuario-identidad__datos">
                                                    <strong>
                                                        {{ $usuario->nombre }} {{ $usuario->apellido }}
                                                    </strong>

                                                    <small>
                                                        Tel. {{ $usuario->telefono }}

                                                        @if ($usuarioEsDueno)
                                                            <span class="insignia-protegido">
                                                                Protegido
                                                            </span>
                                                        @endif
                                                    </small>
                                                </span>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="dato-usuario dato-usuario--correo">
                                                {{ $usuario->email }}
                                            </span>
                                        </td>

                                        <td>
                                            <span class="dato-usuario">
                                                {{ $usuario->sucursal?->nombre ?? 'Todas las sucursales' }}
                                            </span>
                                        </td>

                                        <td>
                                            <span class="rol-etiqueta">
                                                {{ $usuario->rol?->nombre ?? 'Sin rol' }}
                                            </span>
                                        </td>

                                        <td>
                                            <span
                                                class="estado-usuario {{ $usuario->estado ? 'estado-usuario--activo' : 'estado-usuario--inactivo' }}"
                                            >
                                                {{ $usuario->estado ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>

                                        <td>
                                            <a
                                                href="{{ route('usuarios.index', $parametrosPerfil) }}"
                                                class="boton-ver-perfil{{ $estaSeleccionado ? ' boton-ver-perfil--activo' : '' }}"
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

                    @if ($usuarios->hasPages())
                        @php
                            $paginaInicial = max(1, $usuarios->currentPage() - 2);
                            $paginaFinal = min(
                                $usuarios->lastPage(),
                                $usuarios->currentPage() + 2
                            );
                        @endphp

                        <nav class="paginacion" aria-label="Paginación del personal">
                            @if ($usuarios->onFirstPage())
                                <span class="paginacion__boton paginacion__boton--deshabilitado">
                                    Anterior
                                </span>
                            @else
                                <a
                                    href="{{ $usuarios->previousPageUrl() }}"
                                    class="paginacion__boton"
                                >
                                    Anterior
                                </a>
                            @endif

                            <div class="paginacion__paginas">
                                @foreach (range($paginaInicial, $paginaFinal) as $pagina)
                                    <a
                                        href="{{ $usuarios->url($pagina) }}"
                                        class="paginacion__numero{{ $pagina === $usuarios->currentPage() ? ' paginacion__numero--activo' : '' }}"
                                        @if ($pagina === $usuarios->currentPage()) aria-current="page" @endif
                                    >
                                        {{ $pagina }}
                                    </a>
                                @endforeach
                            </div>

                            @if ($usuarios->hasMorePages())
                                <a
                                    href="{{ $usuarios->nextPageUrl() }}"
                                    class="paginacion__boton"
                                >
                                    Siguiente
                                </a>
                            @else
                                <span class="paginacion__boton paginacion__boton--deshabilitado">
                                    Siguiente
                                </span>
                            @endif
                        </nav>
                    @endif
                @endif
            </section>

            <aside class="panel panel--perfil-usuario">
                <div class="panel__cabecera panel__cabecera--perfil">
                    <div>
                        <span class="panel__numero">03</span>

                        <div>
                            <h2>Perfil seleccionado</h2>

                            <p>
                                {{ $usuarioSeleccionadoEsDueno
                                    ? 'La cuenta principal se muestra en modo de consulta.'
                                    : 'Actualizá sus datos laborales y su rol.' }}
                            </p>
                        </div>
                    </div>
                </div>

                @if (!$usuarioSeleccionado)
                    <div class="perfil-vacio">
                        <span class="perfil-vacio__icono" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm-9 9a9 9 0 0 1 18 0H3Z"/>
                            </svg>
                        </span>

                        <strong>Seleccioná un usuario</strong>
                        <p>Elegí una cuenta de la tabla para abrir su perfil.</p>
                    </div>
                @else
                    @php
                        $inicialesSeleccionado = mb_strtoupper(
                            mb_substr(
                                $usuarioSeleccionado->nombre,
                                0,
                                1,
                                'UTF-8'
                            )
                            . mb_substr(
                                $usuarioSeleccionado->apellido,
                                0,
                                1,
                                'UTF-8'
                            ),
                            'UTF-8'
                        );

                        $permisosHeredados = $usuarioSeleccionado->rol?->permisos
                            ?? collect();
                    @endphp

                    <div class="perfil-resumen">
                        <span class="perfil-resumen__avatar" aria-hidden="true">
                            {{ $inicialesSeleccionado }}
                        </span>

                        <div>
                            <span class="perfil-resumen__etiqueta">Usuario seleccionado</span>

                            <h3>
                                {{ $usuarioSeleccionado->nombre }}
                                {{ $usuarioSeleccionado->apellido }}
                            </h3>

                            <p>{{ $usuarioSeleccionado->email }}</p>
                        </div>

                        <span
                            class="estado-usuario {{ $usuarioSeleccionado->estado ? 'estado-usuario--activo' : 'estado-usuario--inactivo' }}"
                        >
                            {{ $usuarioSeleccionado->estado ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>

                    @if ($usuarioSeleccionadoEsDueno)
                        <div class="perfil-protegido">
                            <span aria-hidden="true">!</span>

                            <div>
                                <strong>Cuenta DUEÑO protegida</strong>
                                <p>
                                    Conserva el acceso global y no puede cambiarse
                                    ni desactivarse desde esta pantalla.
                                </p>
                            </div>
                        </div>

                        <div class="perfil-datos-consulta">
                            <div>
                                <span>Teléfono</span>
                                <strong>{{ $usuarioSeleccionado->telefono }}</strong>
                            </div>

                            <div>
                                <span>Sucursal</span>
                                <strong>Todas las sucursales</strong>
                            </div>

                            <div>
                                <span>Rol asignado</span>
                                <strong>{{ $usuarioSeleccionado->rol?->nombre ?? 'Sin rol' }}</strong>
                            </div>
                        </div>
                    @else
                        <form
                            action="{{ route('usuarios.update', ['usuario' => $usuarioSeleccionado->id]) }}"
                            method="POST"
                            id="formulario-editar-usuario"
                            class="formulario-perfil"
                            data-usuario-estaba-activo="{{ $usuarioSeleccionado->estado ? '1' : '0' }}"
                            data-nombre-usuario="{{ $usuarioSeleccionado->nombre }} {{ $usuarioSeleccionado->apellido }}"
                        >
                            @csrf
                            @method('PUT')

                            <input type="hidden" name="_formulario" value="editar">
                            <input
                                type="hidden"
                                name="_usuario_id"
                                value="{{ $usuarioSeleccionado->id }}"
                            >

                            <div class="formulario-perfil__grilla">
                                <div class="campo">
                                    <label for="nombre-editar">Nombre *</label>

                                    <input
                                        type="text"
                                        id="nombre-editar"
                                        name="nombre"
                                        value="{{ $edicionConErroresDelUsuarioActual ? old('nombre') : $usuarioSeleccionado->nombre }}"
                                        maxlength="100"
                                        pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' -]+"
                                        title="Ingresá solamente letras, espacios, apóstrofes o guiones."
                                        data-solo-letras
                                        data-nombre-campo="nombre"
                                        autocomplete="given-name"
                                        required
                                    >
                                </div>

                                <div class="campo">
                                    <label for="apellido-editar">Apellido *</label>

                                    <input
                                        type="text"
                                        id="apellido-editar"
                                        name="apellido"
                                        value="{{ $edicionConErroresDelUsuarioActual ? old('apellido') : $usuarioSeleccionado->apellido }}"
                                        maxlength="100"
                                        pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' -]+"
                                        title="Ingresá solamente letras, espacios, apóstrofes o guiones."
                                        data-solo-letras
                                        data-nombre-campo="apellido"
                                        autocomplete="family-name"
                                        required
                                    >
                                </div>

                                <div class="campo formulario-perfil__campo-completo">
                                    <label for="email-editar">Correo electrónico *</label>

                                    <input
                                        type="email"
                                        id="email-editar"
                                        name="email"
                                        value="{{ $edicionConErroresDelUsuarioActual ? old('email') : $usuarioSeleccionado->email }}"
                                        maxlength="255"
                                        pattern="[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}"
                                        title="Ingresá un correo válido, por ejemplo usuario@dominio.com."
                                        data-correo
                                        autocomplete="email"
                                        required
                                    >
                                </div>

                                <div class="campo">
                                    <label for="telefono-editar">Teléfono *</label>

                                    <input
                                        type="tel"
                                        id="telefono-editar"
                                        name="telefono"
                                        value="{{ $edicionConErroresDelUsuarioActual ? old('telefono') : $usuarioSeleccionado->telefono }}"
                                        inputmode="numeric"
                                        minlength="7"
                                        maxlength="20"
                                        pattern="[0-9]{7,20}"
                                        title="Ingresá entre 7 y 20 números, sin espacios ni símbolos."
                                        data-solo-numeros
                                        autocomplete="tel"
                                        required
                                    >
                                </div>

                                <div class="campo">
                                    <label for="sucursal-editar">Sucursal *</label>

                                    <select
                                        id="sucursal-editar"
                                        name="idsucursal"
                                        required
                                    >
                                        <option value="">Seleccionar sucursal</option>

                                        @foreach ($sucursalesActivas as $sucursal)
                                            <option
                                                value="{{ $sucursal->idsucursal }}"
                                                @selected(
                                                    (int) (
                                                        $edicionConErroresDelUsuarioActual
                                                            ? old('idsucursal')
                                                            : $usuarioSeleccionado->idsucursal
                                                    ) === (int) $sucursal->idsucursal
                                                )
                                            >
                                                {{ $sucursal->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="campo formulario-perfil__campo-completo">
                                    <label for="rol-editar">Rol asignado *</label>

                                    <select id="rol-editar" name="idrol" required>
                                        <option value="">Seleccionar rol</option>

                                        @foreach ($rolesAsignables as $rol)
                                            <option
                                                value="{{ $rol->idrol }}"
                                                @selected(
                                                    (int) (
                                                        $edicionConErroresDelUsuarioActual
                                                            ? old('idrol')
                                                            : $usuarioSeleccionado->idrol
                                                    ) === (int) $rol->idrol
                                                )
                                            >
                                                {{ $rol->nombre }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <small>
                                        Los permisos se actualizarán al guardar el nuevo rol.
                                    </small>
                                </div>

                                <div class="campo formulario-perfil__campo-completo">
                                    <span class="campo__etiqueta">Estado de la cuenta</span>

                                    <label class="interruptor">
                                        <input type="hidden" name="estado" value="0">

                                        <input
                                            type="checkbox"
                                            id="estado-editar"
                                            name="estado"
                                            value="1"
                                            @checked(
                                                $edicionConErroresDelUsuarioActual
                                                    ? (string) old('estado') === '1'
                                                    : $usuarioSeleccionado->estado
                                            )
                                        >

                                        <span class="interruptor__control" aria-hidden="true"></span>
                                        <span class="interruptor__texto">Usuario habilitado</span>
                                    </label>
                                </div>
                            </div>

                            <details
                                class="cambio-password"
                                @if ($edicionConErroresDelUsuarioActual && old('password')) open @endif
                            >
                                <summary>Cambiar contraseña</summary>

                                <p>
                                    Dejá ambos campos vacíos para conservar la contraseña actual.
                                </p>

                                <div class="formulario-perfil__grilla">
                                    <div class="campo">
                                        <label for="password-editar">Nueva contraseña</label>

                                        <div class="campo-password">
                                            <input
                                                type="password"
                                                id="password-editar"
                                                name="password"
                                                autocomplete="new-password"
                                            >

                                            <button
                                                type="button"
                                                class="campo-password__boton"
                                                data-alternar-password="password-editar"
                                                aria-label="Mostrar contraseña"
                                            >
                                                Mostrar
                                            </button>
                                        </div>
                                    </div>

                                    <div class="campo">
                                        <label for="password-confirmation-editar">
                                            Confirmar contraseña
                                        </label>

                                        <div class="campo-password">
                                            <input
                                                type="password"
                                                id="password-confirmation-editar"
                                                name="password_confirmation"
                                                autocomplete="new-password"
                                            >

                                            <button
                                                type="button"
                                                class="campo-password__boton"
                                                data-alternar-password="password-confirmation-editar"
                                                aria-label="Mostrar confirmación de contraseña"
                                            >
                                                Mostrar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </details>

                            <footer class="formulario-perfil__pie">
                                <span>
                                    Última actualización:
                                    {{ $usuarioSeleccionado->updated_at?->format('d/m/Y H:i') ?? 'Sin datos' }}
                                </span>

                                <button type="submit" class="boton boton--primario">
                                    Guardar cambios
                                </button>
                            </footer>
                        </form>
                    @endif

                    <section class="permisos-heredados">
                        <div class="permisos-heredados__cabecera">
                            <div>
                                <h3>Permisos heredados</h3>

                                <p>
                                    Rol actual:
                                    <strong>{{ $usuarioSeleccionado->rol?->nombre ?? 'Sin rol' }}</strong>
                                </p>
                            </div>

                            <span>
                                {{ $permisosHeredados->count() }}
                                {{ $permisosHeredados->count() === 1 ? 'permiso' : 'permisos' }}
                            </span>
                        </div>

                        @if ($permisosHeredados->isEmpty())
                            <p class="permisos-heredados__vacio">
                                El rol actual no tiene permisos registrados.
                            </p>
                        @else
                            <div class="permisos-heredados__lista">
                                @foreach ($permisosHeredados as $permiso)
                                    <div class="permiso-heredado">
                                        <span aria-hidden="true">✓</span>

                                        <div>
                                            <strong>{{ $permiso->nombre }}</strong>
                                            <small>{{ $permiso->codigo }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif
            </aside>
        </div>
    </main>

    <dialog
        id="modal-nuevo-usuario"
        class="modal-usuario"
        data-abrir-por-error="{{ $esFormularioCreacion && $errors->any() ? '1' : '0' }}"
        aria-labelledby="titulo-modal-nuevo-usuario"
    >
        <form
            action="{{ route('usuarios.store') }}"
            method="POST"
            class="modal-usuario__contenido"
        >
            @csrf

            <input type="hidden" name="_formulario" value="crear">

            <header class="modal-usuario__cabecera">
                <div>
                    <span>Alta de personal</span>
                    <h2 id="titulo-modal-nuevo-usuario">Nuevo usuario</h2>
                    <p>Creá la cuenta y asignale su sucursal y rol inicial.</p>
                </div>

                <button
                    type="button"
                    class="modal-usuario__cerrar"
                    data-cerrar-modal
                    aria-label="Cerrar ventana"
                >
                    ×
                </button>
            </header>

            <div class="modal-usuario__cuerpo">
                @if ($esFormularioCreacion && $errors->any())
                    <div class="errores-modal" role="alert">
                        <strong>No pudimos crear el usuario.</strong>
                        <span>Corregí los campos indicados y volvé a intentarlo.</span>
                    </div>
                @endif

                <div class="modal-usuario__seccion">
                    <div class="modal-usuario__titulo-seccion">
                        <span>01</span>

                        <div>
                            <strong>Datos personales</strong>
                            <small>Información de contacto del empleado.</small>
                        </div>
                    </div>

                    <div class="formulario-nuevo-usuario">
                        <div class="campo">
                            <label for="nombre-crear">Nombre *</label>

                            <input
                                type="text"
                                id="nombre-crear"
                                name="nombre"
                                value="{{ $esFormularioCreacion ? old('nombre') : '' }}"
                                maxlength="100"
                                pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' -]+"
                                title="Ingresá solamente letras, espacios, apóstrofes o guiones."
                                data-solo-letras
                                data-nombre-campo="nombre"
                                autocomplete="given-name"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="apellido-crear">Apellido *</label>

                            <input
                                type="text"
                                id="apellido-crear"
                                name="apellido"
                                value="{{ $esFormularioCreacion ? old('apellido') : '' }}"
                                maxlength="100"
                                pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' -]+"
                                title="Ingresá solamente letras, espacios, apóstrofes o guiones."
                                data-solo-letras
                                data-nombre-campo="apellido"
                                autocomplete="family-name"
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="email-crear">Correo electrónico *</label>

                            <input
                                type="email"
                                id="email-crear"
                                name="email"
                                value="{{ $esFormularioCreacion ? old('email') : '' }}"
                                maxlength="255"
                                autocomplete="email"
                                placeholder="empleado@messirve.com"
                                pattern="[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}"
                                title="Ingresá un correo válido, por ejemplo usuario@dominio.com."
                                data-correo
                                required
                            >
                        </div>

                        <div class="campo">
                            <label for="telefono-crear">Teléfono *</label>

                            <input
                                type="tel"
                                id="telefono-crear"
                                name="telefono"
                                value="{{ $esFormularioCreacion ? old('telefono') : '' }}"
                                inputmode="numeric"
                                minlength="7"
                                maxlength="20"
                                autocomplete="tel"
                                placeholder="Solamente números"
                                pattern="[0-9]{7,20}"
                                title="Ingresá entre 7 y 20 números, sin espacios ni símbolos."
                                data-solo-numeros
                                required
                            >
                        </div>
                    </div>
                </div>

                <div class="modal-usuario__seccion">
                    <div class="modal-usuario__titulo-seccion">
                        <span>02</span>

                        <div>
                            <strong>Asignación laboral</strong>
                            <small>Ubicación, rol y estado inicial.</small>
                        </div>
                    </div>

                    <div class="formulario-nuevo-usuario formulario-nuevo-usuario--laboral">
                        <div class="campo">
                            <label for="sucursal-crear">Sucursal *</label>

                            <select
                                id="sucursal-crear"
                                name="idsucursal"
                                required
                            >
                                <option value="">Seleccionar sucursal</option>

                                @foreach ($sucursalesActivas as $sucursal)
                                    <option
                                        value="{{ $sucursal->idsucursal }}"
                                        @selected(
                                            $esFormularioCreacion
                                            && (int) old('idsucursal') === (int) $sucursal->idsucursal
                                        )
                                    >
                                        {{ $sucursal->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="campo">
                            <label for="rol-crear">Rol *</label>

                            <select id="rol-crear" name="idrol" required>
                                <option value="">Seleccionar rol</option>

                                @foreach ($rolesAsignables as $rol)
                                    <option
                                        value="{{ $rol->idrol }}"
                                        @selected(
                                            $esFormularioCreacion
                                            && (int) old('idrol') === (int) $rol->idrol
                                        )
                                    >
                                        {{ $rol->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="campo campo--estado-nuevo">
                            <span class="campo__etiqueta">Estado inicial</span>

                            <label class="interruptor">
                                <input type="hidden" name="estado" value="0">

                                <input
                                    type="checkbox"
                                    id="estado-crear"
                                    name="estado"
                                    value="1"
                                    @checked(
                                        !$esFormularioCreacion
                                        || (string) old('estado', '1') === '1'
                                    )
                                >

                                <span class="interruptor__control" aria-hidden="true"></span>
                                <span class="interruptor__texto">Crear como activo</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-usuario__seccion">
                    <div class="modal-usuario__titulo-seccion">
                        <span>03</span>

                        <div>
                            <strong>Contraseña inicial</strong>
                            <small>Mínimo 8 caracteres, mayúscula, minúscula y número.</small>
                        </div>
                    </div>

                    <div class="formulario-nuevo-usuario">
                        <div class="campo">
                            <label for="password-crear">Contraseña *</label>

                            <div class="campo-password">
                                <input
                                    type="password"
                                    id="password-crear"
                                    name="password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="campo-password__boton"
                                    data-alternar-password="password-crear"
                                    aria-label="Mostrar contraseña"
                                >
                                    Mostrar
                                </button>
                            </div>
                        </div>

                        <div class="campo">
                            <label for="password-confirmation-crear">
                                Confirmar contraseña *
                            </label>

                            <div class="campo-password">
                                <input
                                    type="password"
                                    id="password-confirmation-crear"
                                    name="password_confirmation"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="campo-password__boton"
                                    data-alternar-password="password-confirmation-crear"
                                    aria-label="Mostrar confirmación de contraseña"
                                >
                                    Mostrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="modal-usuario__pie">
                <button
                    type="button"
                    class="boton boton--secundario"
                    data-cerrar-modal
                >
                    Cancelar
                </button>

                <button type="submit" class="boton boton--primario">
                    Crear usuario
                </button>
            </footer>
        </form>
    </dialog>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('modal-nuevo-usuario');
            const botonAbrir = document.getElementById(
                'boton-abrir-nuevo-usuario'
            );
            const formularioEditar = document.getElementById(
                'formulario-editar-usuario'
            );
            const formularioFiltros = document.getElementById(
                'formulario-filtros-usuarios'
            );
            const estadoFiltros = document.getElementById(
                'estado-filtros-usuarios'
            );

            /*
             * Validaciones visibles mientras se escribe. Las mismas reglas
             * también se verifican en UsuarioController antes de guardar.
             */
            document.querySelectorAll('[data-solo-letras]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.setCustomValidity('');
                    campo.value = campo.value.replace(/[^\p{L}\s'-]/gu, '');
                });

                campo.addEventListener('invalid', function () {
                    if (campo.validity.valueMissing) {
                        campo.setCustomValidity(
                            'El ' + campo.dataset.nombreCampo + ' es obligatorio.'
                        );
                    } else if (campo.validity.patternMismatch) {
                        campo.setCustomValidity(
                            'Ingresá solamente letras en el ' + campo.dataset.nombreCampo + '.'
                        );
                    }
                });
            });

            document.querySelectorAll('[data-solo-numeros]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.setCustomValidity('');
                    campo.value = campo.value.replace(/\D/g, '').slice(0, 20);
                });

                campo.addEventListener('invalid', function () {
                    if (campo.validity.valueMissing) {
                        campo.setCustomValidity('El teléfono es obligatorio.');
                    } else {
                        campo.setCustomValidity(
                            'Ingresá entre 7 y 20 números, sin espacios ni símbolos.'
                        );
                    }
                });
            });

            document.querySelectorAll('[data-correo]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.setCustomValidity('');

                    let correoLimpio = campo.value
                        .replace(/\s/g, '')
                        .replace(/[^A-Za-z0-9@._+-]/g, '')
                        .toLowerCase();

                    const partes = correoLimpio.split('@');

                    if (partes.length > 2) {
                        correoLimpio = partes.shift() + '@' + partes.join('');
                    }

                    campo.value = correoLimpio;
                });

                campo.addEventListener('invalid', function () {
                    if (campo.validity.valueMissing) {
                        campo.setCustomValidity('El correo electrónico es obligatorio.');
                    } else {
                        campo.setCustomValidity(
                            'Ingresá un correo válido, por ejemplo usuario@dominio.com.'
                        );
                    }
                });
            });

            /*
             * Búsqueda automática: espera un instante después de escribir y
             * aplica inmediatamente cualquier cambio de los combobox.
             */
            if (formularioFiltros) {
                const campoBusqueda = formularioFiltros.querySelector('#buscar');
                const selectores = formularioFiltros.querySelectorAll('select');
                let temporizadorBusqueda;

                function indicarBusqueda(mensaje) {
                    if (estadoFiltros) {
                        estadoFiltros.textContent = mensaje;
                    }
                }

                function enviarFiltros(restaurarFoco) {
                    window.clearTimeout(temporizadorBusqueda);

                    if (restaurarFoco) {
                        window.sessionStorage.setItem(
                            'usuarios-restaurar-busqueda',
                            '1'
                        );
                    } else {
                        window.sessionStorage.removeItem(
                            'usuarios-restaurar-busqueda'
                        );
                    }

                    indicarBusqueda('Buscando...');
                    formularioFiltros.requestSubmit();
                }

                if (campoBusqueda) {
                    campoBusqueda.addEventListener('input', function () {
                        window.clearTimeout(temporizadorBusqueda);
                        indicarBusqueda('Esperando que termines de escribir...');

                        temporizadorBusqueda = window.setTimeout(function () {
                            enviarFiltros(true);
                        }, 500);
                    });

                    if (
                        window.sessionStorage.getItem(
                            'usuarios-restaurar-busqueda'
                        ) === '1'
                    ) {
                        window.sessionStorage.removeItem(
                            'usuarios-restaurar-busqueda'
                        );
                        campoBusqueda.focus();
                        campoBusqueda.setSelectionRange(
                            campoBusqueda.value.length,
                            campoBusqueda.value.length
                        );
                    }
                }

                selectores.forEach(function (selector) {
                    selector.addEventListener('change', function () {
                        enviarFiltros(false);
                    });
                });

                formularioFiltros.addEventListener('submit', function () {
                    indicarBusqueda('Buscando...');
                });
            }

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

            document.querySelectorAll('[data-alternar-password]').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    const campo = document.getElementById(
                        boton.dataset.alternarPassword
                    );

                    if (!campo) {
                        return;
                    }

                    const seMostrara = campo.type === 'password';
                    campo.type = seMostrara ? 'text' : 'password';
                    boton.textContent = seMostrara ? 'Ocultar' : 'Mostrar';
                    boton.setAttribute(
                        'aria-label',
                        seMostrara ? 'Ocultar contraseña' : 'Mostrar contraseña'
                    );
                });
            });

            if (formularioEditar) {
                formularioEditar.addEventListener('submit', function (evento) {
                    const estado = formularioEditar.querySelector(
                        '#estado-editar'
                    );
                    const estabaActivo =
                        formularioEditar.dataset.usuarioEstabaActivo === '1';

                    if (estado && estabaActivo && !estado.checked) {
                        const nombre = formularioEditar.dataset.nombreUsuario;
                        const confirmado = window.confirm(
                            'Al inactivar a ' + nombre
                            + ', esa persona no podrá iniciar sesión. ¿Querés continuar?'
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
