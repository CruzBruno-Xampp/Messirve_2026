<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Clientes | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/productos/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/clientes/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/clientes/tipos.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/barra-lateral.css') }}">
</head>
<body class="con-barra-lateral">
    @php
        $usuarioAdmin = auth('web')->user();
        $puedeCrearClientes = $usuarioAdmin?->tienePermiso('clientes.crear') ?? false;
        $puedeEditarClientes = $usuarioAdmin?->tienePermiso('clientes.editar') ?? false;

        $totalClientes = (int) ($resumen->total ?? 0);
        $clientesActivos = (int) ($resumen->activos ?? 0);
        $clientesInactivos = (int) ($resumen->inactivos ?? 0);

        $errorCreacionCliente = (bool) session('abrir_modal_nuevo_cliente', false);
        $esFormularioCreacion = $errorCreacionCliente || old('_formulario') === 'crear';
        $esFormularioEdicion = old('_formulario') === 'editar';

        $erroresTiposCliente = $errors->getBag('tiposCliente');
        $abrirModalTiposCliente = (bool) session('abrir_modal_tipos_cliente', false)
            || $erroresTiposCliente->any();
        $formularioTipoConError = old('_formulario_tipo');
        $idTipoConError = (int) old('_tipo_id', 0);

        $edicionConErroresDelClienteActual = $esFormularioEdicion
            && $clienteSeleccionado
            && (int) old('_cliente_id') === (int) $clienteSeleccionado->idcliente;

        $hayFiltros = $busqueda !== ''
            || $estado !== 'todos'
            || $idTipoCliente !== null;

        $codigoCliente = fn ($cliente) => 'CLI-' . str_pad(
            (string) $cliente->idcliente,
            6,
            '0',
            STR_PAD_LEFT
        );
    @endphp

    @include('partials.barra-lateral')

    <main class="pagina-productos pagina-clientes">
        <header class="encabezado-principal">
            <div>
                <p class="encabezado-principal__seccion">Administración comercial</p>
                <h1>Clientes de Messirve</h1>
                <p class="encabezado-principal__descripcion">
                    Consultá los clientes registrados, actualizá sus datos y cargá
                    nuevos clientes directamente desde el local.
                </p>
            </div>

            <div class="encabezado-principal__acciones encabezado-principal__acciones--clientes">
                <button
                    type="button"
                    id="boton-abrir-tipos-cliente"
                    class="boton boton--secundario"
                >
                    Tipos de clientes
                </button>

                @if ($puedeCrearClientes)
                    <button
                        type="button"
                        id="boton-abrir-nuevo-cliente"
                        class="boton boton--primario"
                        @disabled($tiposClienteActivos->isEmpty())
                    >
                        <span class="boton__simbolo" aria-hidden="true">+</span>
                        Nuevo cliente
                    </button>
                @endif
            </div>
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

        @if ($tiposClienteActivos->isEmpty())
            <section class="alerta alerta--advertencia" role="alert">
                <strong>No hay tipos de cliente activos.</strong>
                <span>Activá al menos un tipo de cliente antes de registrar nuevos clientes.</span>
            </section>
        @endif

        <section class="resumen resumen--clientes">
            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Clientes registrados</span>
                <strong class="resumen__valor">{{ number_format($totalClientes, 0, ',', '.') }}</strong>
                <small>Total de clientes cargados</small>
            </article>

            <article class="resumen__tarjeta">
                <span class="resumen__etiqueta">Estado de clientes</span>
                <div class="resumen-estados-clientes">
                    <span><strong>{{ $clientesActivos }}</strong> Activos</span>
                    <span><strong>{{ $clientesInactivos }}</strong> Inactivos</span>
                </div>
                <small>Los clientes inactivos no aparecen en Nueva Venta</small>
            </article>

            <article class="resumen__tarjeta resumen__tarjeta--informativa cliente-info">
                <span class="resumen__icono" aria-hidden="true">i</span>
                <div>
                    <strong>Alta rápida desde el local</strong>
                    <p>
                        Un cliente creado desde esta pantalla puede utilizarse de inmediato
                        en una venta. Su contraseña web no se modifica desde administración.
                    </p>
                </div>
            </article>
        </section>

        <section class="panel panel--filtros-clientes">
            <div class="panel__cabecera">
                <div>
                    <span class="panel__numero">01</span>
                    <div>
                        <h2>Buscar clientes</h2>
                        <p>Buscá por código, nombre, documento, correo o teléfono.</p>
                    </div>
                </div>
            </div>

            <form
                action="{{ route('clientes.index') }}"
                method="GET"
                id="formulario-filtros-clientes"
                class="filtros filtros--clientes"
            >
                <div class="campo campo--busqueda-cliente">
                    <label for="buscar">Buscar cliente</label>
                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="{{ $busqueda }}"
                        placeholder="Ejemplo: CLI-000004, Gonzalo, DNI o teléfono..."
                        maxlength="255"
                        autocomplete="off"
                    >
                </div>

                <div class="campo">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <option value="">Todos los tipos</option>
                        @foreach ($tiposCliente as $tipo)
                            <option
                                value="{{ $tipo->idtipocliente }}"
                                @selected($idTipoCliente === (int) $tipo->idtipocliente)
                            >
                                {{ $tipo->nombre }}{{ $tipo->estado ? '' : ' (Inactivo)' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="campo">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="todos" @selected($estado === 'todos')>Todos</option>
                        <option value="1" @selected($estado === '1')>Activos</option>
                        <option value="0" @selected($estado === '0')>Inactivos</option>
                    </select>
                </div>

                <div class="filtros__acciones filtros__acciones--clientes">
                    @if ($hayFiltros)
                        <a href="{{ route('clientes.index') }}" class="boton boton--secundario">
                            Limpiar
                        </a>
                    @endif

                    <span
                        id="estado-filtros-clientes"
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

        <div class="clientes-distribucion">
            <section class="panel panel--tabla panel--lista-clientes">
                <div class="panel__cabecera panel__cabecera--tabla">
                    <div>
                        <span class="panel__numero">02</span>
                        <div>
                            <h2>Clientes registrados</h2>
                            <p>Seleccioná un cliente para consultar o modificar sus datos.</p>
                        </div>
                    </div>

                    <span class="resultado-cantidad">
                        {{ $clientes->firstItem() ?? 0 }}–{{ $clientes->lastItem() ?? 0 }}
                        de {{ $clientes->total() }}
                    </span>
                </div>

                @if ($clientes->isEmpty())
                    <div class="estado-vacio estado-vacio--clientes">
                        <span class="estado-vacio__icono" aria-hidden="true">i</span>
                        <h3>No encontramos clientes</h3>
                        <p>No hay clientes que coincidan con los filtros seleccionados.</p>

                        @if ($hayFiltros)
                            <div class="estado-vacio__acciones">
                                <a href="{{ route('clientes.index') }}" class="boton boton--secundario">
                                    Limpiar filtros
                                </a>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="tabla-contenedor tabla-contenedor--clientes" tabindex="0">
                        <table class="tabla-clientes">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Documento</th>
                                    <th>Contacto</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Perfil</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clientes as $cliente)
                                    @php
                                        $estaSeleccionado = $clienteSeleccionado
                                            && (int) $clienteSeleccionado->idcliente === (int) $cliente->idcliente;

                                        $parametrosPerfil = request()->except('page');
                                        $parametrosPerfil['cliente'] = $cliente->idcliente;

                                        $iniciales = mb_strtoupper(
                                            mb_substr($cliente->nombre, 0, 1, 'UTF-8')
                                            . mb_substr($cliente->apellido, 0, 1, 'UTF-8'),
                                            'UTF-8'
                                        );
                                    @endphp

                                    <tr class="{{ $estaSeleccionado ? 'fila-cliente--seleccionada' : '' }}">
                                        <td>
                                            <div class="cliente-identidad">
                                                <span class="cliente-avatar" aria-hidden="true">{{ $iniciales }}</span>
                                                <span class="cliente-identidad__datos">
                                                    <strong>{{ $cliente->nombre }} {{ $cliente->apellido }}</strong>
                                                    <small>{{ $codigoCliente($cliente) }}</small>
                                                </span>
                                            </div>
                                        </td>
                                        <td>{{ $cliente->documento ?: 'Sin cargar' }}</td>
                                        <td>
                                            <span class="contacto-cliente">
                                                <strong>{{ $cliente->telefono ?: 'Sin teléfono' }}</strong>
                                                <small>{{ $cliente->email ?: 'Sin correo' }}</small>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="tipo-cliente-etiqueta">
                                                {{ $cliente->tipoCliente?->nombre ?? 'Sin tipo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="estado-cliente {{ $cliente->estado ? 'estado-cliente--activo' : 'estado-cliente--inactivo' }}">
                                                {{ $cliente->estado ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <a
                                                href="{{ route('clientes.index', $parametrosPerfil) }}"
                                                class="boton-ver-cliente{{ $estaSeleccionado ? ' boton-ver-cliente--activo' : '' }}"
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

                    @if ($clientes->hasPages())
                        @php
                            $paginaInicial = max(1, $clientes->currentPage() - 2);
                            $paginaFinal = min($clientes->lastPage(), $clientes->currentPage() + 2);
                        @endphp

                        <nav class="paginacion" aria-label="Paginación de clientes">
                            @if ($clientes->onFirstPage())
                                <span class="paginacion__boton paginacion__boton--deshabilitado">Anterior</span>
                            @else
                                <a href="{{ $clientes->previousPageUrl() }}" class="paginacion__boton">Anterior</a>
                            @endif

                            <div class="paginacion__paginas">
                                @foreach (range($paginaInicial, $paginaFinal) as $pagina)
                                    <a
                                        href="{{ $clientes->url($pagina) }}"
                                        class="paginacion__numero{{ $pagina === $clientes->currentPage() ? ' paginacion__numero--activo' : '' }}"
                                        @if ($pagina === $clientes->currentPage()) aria-current="page" @endif
                                    >
                                        {{ $pagina }}
                                    </a>
                                @endforeach
                            </div>

                            @if ($clientes->hasMorePages())
                                <a href="{{ $clientes->nextPageUrl() }}" class="paginacion__boton">Siguiente</a>
                            @else
                                <span class="paginacion__boton paginacion__boton--deshabilitado">Siguiente</span>
                            @endif
                        </nav>
                    @endif
                @endif
            </section>

            <aside class="panel panel--perfil-cliente">
                <div class="panel__cabecera panel__cabecera--perfil-cliente">
                    <div>
                        <span class="panel__numero">03</span>
                        <div>
                            <h2>Perfil seleccionado</h2>
                            <p>{{ $puedeEditarClientes ? 'Actualizá los datos del cliente.' : 'Consultá los datos del cliente.' }}</p>
                        </div>
                    </div>
                </div>

                @if (!$clienteSeleccionado)
                    <div class="perfil-cliente-vacio">
                        <strong>Seleccioná un cliente</strong>
                        <p>Elegí un registro de la tabla para abrir su perfil.</p>
                    </div>
                @else
                    @php
                        $inicialesSeleccionado = mb_strtoupper(
                            mb_substr($clienteSeleccionado->nombre, 0, 1, 'UTF-8')
                            . mb_substr($clienteSeleccionado->apellido, 0, 1, 'UTF-8'),
                            'UTF-8'
                        );
                    @endphp

                    <div class="perfil-cliente-resumen">
                        <span class="perfil-cliente-resumen__avatar" aria-hidden="true">
                            {{ $inicialesSeleccionado }}
                        </span>
                        <div>
                            <span class="perfil-cliente-resumen__etiqueta">{{ $codigoCliente($clienteSeleccionado) }}</span>
                            <h3>{{ $clienteSeleccionado->nombre }} {{ $clienteSeleccionado->apellido }}</h3>
                            <p>{{ $clienteSeleccionado->email ?: 'Sin correo registrado' }}</p>
                        </div>
                        <span class="estado-cliente {{ $clienteSeleccionado->estado ? 'estado-cliente--activo' : 'estado-cliente--inactivo' }}">
                            {{ $clienteSeleccionado->estado ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>

                    @if ($puedeEditarClientes)
                        <form
                            action="{{ route('clientes.update', ['cliente' => $clienteSeleccionado->idcliente]) }}"
                            method="POST"
                            id="formulario-editar-cliente"
                            class="formulario-cliente"
                            data-cliente-estaba-activo="{{ $clienteSeleccionado->estado ? '1' : '0' }}"
                            data-nombre-cliente="{{ $clienteSeleccionado->nombre }} {{ $clienteSeleccionado->apellido }}"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_formulario" value="editar">
                            <input type="hidden" name="_cliente_id" value="{{ $clienteSeleccionado->idcliente }}">

                            <div class="formulario-cliente__grilla">
                                <div class="campo">
                                    <label for="nombre-editar">Nombre *</label>
                                    <input
                                        type="text"
                                        id="nombre-editar"
                                        name="nombre"
                                        value="{{ $edicionConErroresDelClienteActual ? old('nombre') : $clienteSeleccionado->nombre }}"
                                        maxlength="100"
                                        data-solo-letras
                                        required
                                    >
                                </div>

                                <div class="campo">
                                    <label for="apellido-editar">Apellido *</label>
                                    <input
                                        type="text"
                                        id="apellido-editar"
                                        name="apellido"
                                        value="{{ $edicionConErroresDelClienteActual ? old('apellido') : $clienteSeleccionado->apellido }}"
                                        maxlength="100"
                                        data-solo-letras
                                        required
                                    >
                                </div>

                                <div class="campo">
                                    <label for="documento-editar">Documento</label>
                                    <input
                                        type="text"
                                        id="documento-editar"
                                        name="documento"
                                        value="{{ $edicionConErroresDelClienteActual ? old('documento') : $clienteSeleccionado->documento }}"
                                        inputmode="numeric"
                                        maxlength="20"
                                        pattern="[0-9]*"
                                        data-solo-numeros
                                        autocomplete="off"
                                        @if ($edicionConErroresDelClienteActual)
                                            @error('documento') aria-invalid="true" @enderror
                                        @endif
                                    >
                                    @if ($edicionConErroresDelClienteActual)
                                        @error('documento')
                                            <small style="display:block; margin-top:6px; color:#b42318; font-weight:700;">{{ $message }}</small>
                                        @enderror
                                    @endif
                                </div>

                                <div class="campo">
                                    <label for="telefono-editar">Teléfono</label>
                                    <input
                                        type="tel"
                                        id="telefono-editar"
                                        name="telefono"
                                        value="{{ $edicionConErroresDelClienteActual ? old('telefono') : $clienteSeleccionado->telefono }}"
                                        inputmode="numeric"
                                        maxlength="20"
                                        data-solo-numeros
                                    >
                                </div>

                                <div class="campo formulario-cliente__campo-completo">
                                    <label for="email-editar">Correo electrónico</label>
                                    <input
                                        type="email"
                                        id="email-editar"
                                        name="email"
                                        value="{{ $edicionConErroresDelClienteActual ? old('email') : $clienteSeleccionado->email }}"
                                        maxlength="150"
                                        data-correo
                                    >
                                </div>

                                <div class="campo formulario-cliente__campo-completo">
                                    <label for="direccion-editar">Dirección</label>
                                    <input
                                        type="text"
                                        id="direccion-editar"
                                        name="direccion"
                                        value="{{ $edicionConErroresDelClienteActual ? old('direccion') : $clienteSeleccionado->direccion }}"
                                        maxlength="200"
                                    >
                                </div>

                                <div class="campo">
                                    <label for="codigopostal-editar">Código postal</label>
                                    <input
                                        type="text"
                                        id="codigopostal-editar"
                                        name="codigopostal"
                                        value="{{ $edicionConErroresDelClienteActual ? old('codigopostal') : $clienteSeleccionado->codigopostal }}"
                                        maxlength="20"
                                    >
                                </div>

                                <div class="campo">
                                    <label for="tipo-editar">Tipo de cliente *</label>
                                    <select id="tipo-editar" name="idtipocliente" required>
                                        @foreach ($tiposClienteActivos as $tipo)
                                            <option
                                                value="{{ $tipo->idtipocliente }}"
                                                @selected(
                                                    (int) (
                                                        $edicionConErroresDelClienteActual
                                                            ? old('idtipocliente')
                                                            : $clienteSeleccionado->idtipocliente
                                                    ) === (int) $tipo->idtipocliente
                                                )
                                            >
                                                {{ $tipo->nombre }}{{ (float) $tipo->descuento > 0 ? ' · ' . number_format((float) $tipo->descuento, 2, ',', '.') . '% OFF' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="campo formulario-cliente__campo-completo">
                                    <span class="campo__etiqueta">Estado del cliente</span>
                                    <label class="interruptor-cliente">
                                        <input type="hidden" name="estado" value="0">
                                        <input
                                            type="checkbox"
                                            id="estado-editar"
                                            name="estado"
                                            value="1"
                                            @checked(
                                                $edicionConErroresDelClienteActual
                                                    ? (string) old('estado') === '1'
                                                    : $clienteSeleccionado->estado
                                            )
                                        >
                                        <span class="interruptor-cliente__control" aria-hidden="true"></span>
                                        <span>Cliente habilitado</span>
                                    </label>
                                </div>
                            </div>

                            <div class="aviso-password-cliente">
                                <strong>Contraseña web</strong>
                                <span>No se muestra ni se modifica desde esta pantalla.</span>
                            </div>

                            <footer class="formulario-cliente__pie">
                                <span>
                                    Última actualización:
                                    {{ $clienteSeleccionado->updated_at?->format('d/m/Y H:i') ?? 'Sin datos' }}
                                </span>
                                <button type="submit" class="boton boton--primario">Guardar cambios</button>
                            </footer>
                        </form>
                    @else
                        <div class="perfil-cliente-consulta">
                            <div><span>Documento</span><strong>{{ $clienteSeleccionado->documento ?: 'Sin cargar' }}</strong></div>
                            <div><span>Teléfono</span><strong>{{ $clienteSeleccionado->telefono ?: 'Sin cargar' }}</strong></div>
                            <div><span>Dirección</span><strong>{{ $clienteSeleccionado->direccion ?: 'Sin cargar' }}</strong></div>
                            <div><span>Código postal</span><strong>{{ $clienteSeleccionado->codigopostal ?: 'Sin cargar' }}</strong></div>
                            <div><span>Tipo</span><strong>{{ $clienteSeleccionado->tipoCliente?->nombre ?? 'Sin tipo' }}</strong></div>
                        </div>
                    @endif
                @endif
            </aside>
        </div>
    </main>

    @if ($puedeCrearClientes)
        <dialog
            id="modal-nuevo-cliente"
            class="modal-cliente"
            data-abrir-por-error="{{ $esFormularioCreacion && $errors->any() ? '1' : '0' }}"
            aria-labelledby="titulo-modal-nuevo-cliente"
        >
            <form
                action="{{ route('clientes.store') }}"
                method="POST"
                class="modal-cliente__contenido"
            >
                @csrf
                <input type="hidden" name="_formulario" value="crear">

                <header class="modal-cliente__cabecera">
                    <div>
                        <span>Alta de cliente</span>
                        <h2 id="titulo-modal-nuevo-cliente">Nuevo cliente</h2>
                        <p>Registralo para poder seleccionarlo inmediatamente en una venta.</p>
                    </div>
                    <button type="button" class="modal-cliente__cerrar" data-cerrar-modal aria-label="Cerrar ventana">×</button>
                </header>

                <div class="modal-cliente__cuerpo">
                    @if ($esFormularioCreacion && $errors->any())
                        <section class="alerta alerta--error alerta--lista" role="alert" style="margin: 0 0 18px;">
                            <div>
                                <strong>No pudimos crear el cliente.</strong>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </section>
                    @endif

                    <div class="modal-cliente__seccion">
                        <div class="modal-cliente__titulo-seccion">
                            <span>01</span>
                            <div>
                                <strong>Datos principales</strong>
                                <small>Nombre y datos de identificación.</small>
                            </div>
                        </div>

                        <div class="formulario-nuevo-cliente">
                            <div class="campo">
                                <label for="nombre-crear">Nombre *</label>
                                <input type="text" id="nombre-crear" name="nombre" value="{{ $esFormularioCreacion ? old('nombre') : '' }}" maxlength="100" data-solo-letras required>
                            </div>
                            <div class="campo">
                                <label for="apellido-crear">Apellido *</label>
                                <input type="text" id="apellido-crear" name="apellido" value="{{ $esFormularioCreacion ? old('apellido') : '' }}" maxlength="100" data-solo-letras required>
                            </div>
                            <div class="campo">
                                <label for="documento-crear">Documento</label>
                                <input
                                    type="text"
                                    id="documento-crear"
                                    name="documento"
                                    value="{{ $esFormularioCreacion ? old('documento') : '' }}"
                                    inputmode="numeric"
                                    maxlength="20"
                                    pattern="[0-9]*"
                                    data-solo-numeros
                                    @if ($esFormularioCreacion)
                                        @error('documento') aria-invalid="true" @enderror
                                    @endif
                                >
                                @if ($esFormularioCreacion)
                                    @error('documento')
                                        <small style="display:block; margin-top:6px; color:#b42318; font-weight:700;">{{ $message }}</small>
                                    @enderror
                                @endif
                            </div>
                            <div class="campo">
                                <label for="tipo-crear">Tipo de cliente *</label>
                                <select id="tipo-crear" name="idtipocliente" required>
                                    @foreach ($tiposClienteActivos as $tipo)
                                        <option
                                            value="{{ $tipo->idtipocliente }}"
                                            @selected(
                                                (int) (
                                                    $esFormularioCreacion
                                                        ? old('idtipocliente', $tipoPredeterminado?->idtipocliente)
                                                        : $tipoPredeterminado?->idtipocliente
                                                ) === (int) $tipo->idtipocliente
                                            )
                                        >
                                            {{ $tipo->nombre }}{{ (float) $tipo->descuento > 0 ? ' · ' . number_format((float) $tipo->descuento, 2, ',', '.') . '% OFF' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-cliente__seccion">
                        <div class="modal-cliente__titulo-seccion">
                            <span>02</span>
                            <div>
                                <strong>Contacto</strong>
                                <small>Estos datos pueden completarse o corregirse más adelante.</small>
                            </div>
                        </div>

                        <div class="formulario-nuevo-cliente">
                            <div class="campo">
                                <label for="telefono-crear">Teléfono</label>
                                <input type="tel" id="telefono-crear" name="telefono" value="{{ $esFormularioCreacion ? old('telefono') : '' }}" inputmode="numeric" maxlength="20" data-solo-numeros>
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
                                    @error('email') aria-invalid="true" @enderror
                                >
                                @error('email')
                                    <small style="display:block; margin-top:6px; color:#b42318; font-weight:700;">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="campo formulario-nuevo-cliente__campo-completo">
                                <label for="direccion-crear">Dirección</label>
                                <input type="text" id="direccion-crear" name="direccion" value="{{ $esFormularioCreacion ? old('direccion') : '' }}" maxlength="200">
                            </div>
                            <div class="campo">
                                <label for="codigopostal-crear">Código postal</label>
                                <input type="text" id="codigopostal-crear" name="codigopostal" value="{{ $esFormularioCreacion ? old('codigopostal') : '' }}" maxlength="20">
                            </div>
                            <div class="campo campo--estado-cliente-nuevo">
                                <span class="campo__etiqueta">Estado inicial</span>
                                <label class="interruptor-cliente">
                                    <input type="hidden" name="estado" value="0">
                                    <input type="checkbox" id="estado-crear" name="estado" value="1" @checked(!$esFormularioCreacion || (string) old('estado', '1') === '1')>
                                    <span class="interruptor-cliente__control" aria-hidden="true"></span>
                                    <span>Crear como activo</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="alta-cliente-web">
                        <strong>Acceso a la web</strong>
                        <p>
                            Este alta no crea una contraseña. Si el cliente quiere ingresar a la web,
                            podrá definirla mediante el proceso de recuperación de contraseña.
                        </p>
                    </div>
                </div>

                <footer class="modal-cliente__pie">
                    <button type="button" class="boton boton--secundario" data-cerrar-modal>Cancelar</button>
                    <button type="submit" class="boton boton--primario">Crear cliente</button>
                </footer>
            </form>
        </dialog>
    @endif


    <dialog
        id="modal-tipos-cliente"
        class="modal-cliente modal-tipos-cliente"
        data-abrir-por-error="{{ $abrirModalTiposCliente ? '1' : '0' }}"
        aria-labelledby="titulo-modal-tipos-cliente"
    >
        <div class="modal-cliente__contenido modal-tipos-cliente__contenido">
            <header class="modal-cliente__cabecera">
                <div>
                    <span>Configuración comercial</span>
                    <h2 id="titulo-modal-tipos-cliente">Tipos de clientes</h2>
                    <p>Definí descuentos sobre el PVP y en qué canal se aplican.</p>
                </div>
                <button type="button" class="modal-cliente__cerrar" data-cerrar-modal-tipos aria-label="Cerrar ventana">×</button>
            </header>

            <div class="modal-cliente__cuerpo modal-tipos-cliente__cuerpo">
                @if ($formularioTipoConError === 'crear' && $erroresTiposCliente->any())
                    <section class="alerta alerta--error alerta--lista tipos-cliente__alerta" role="alert">
                        <div>
                            <strong>No pudimos crear el tipo de cliente.</strong>
                            <ul>
                                @foreach ($erroresTiposCliente->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
                @endif

                @if ($puedeCrearClientes)
                    <section class="tipos-cliente__seccion tipos-cliente__seccion--nuevo">
                        <div class="tipos-cliente__titulo-seccion">
                            <span>01</span>
                            <div>
                                <strong>Nuevo tipo de cliente</strong>
                                <small>El descuento se calcula sobre el PVP del producto.</small>
                            </div>
                        </div>

                        <form action="{{ route('clientes.tipos.store') }}" method="POST" class="formulario-tipo-cliente">
                            @csrf
                            <input type="hidden" name="_formulario_tipo" value="crear">

                            <div class="formulario-tipo-cliente__grilla">
                                <div class="campo">
                                    <label for="tipo-nombre-crear">Nombre *</label>
                                    <input
                                        type="text"
                                        id="tipo-nombre-crear"
                                        name="tipo_nombre"
                                        value="{{ $formularioTipoConError === 'crear' ? old('tipo_nombre') : '' }}"
                                        maxlength="50"
                                        data-nombre-tipo
                                        autocomplete="off"
                                        @if ($formularioTipoConError === 'crear' && $erroresTiposCliente->has('tipo_nombre')) aria-invalid="true" @endif
                                        required
                                    >
                                    @if ($formularioTipoConError === 'crear')
                                        @foreach ($erroresTiposCliente->get('tipo_nombre') as $error)
                                            <small class="campo-error">{{ $error }}</small>
                                        @endforeach
                                    @endif
                                </div>

                                <div class="campo">
                                    <label for="tipo-descuento-crear">Descuento sobre PVP (%) *</label>
                                    <input
                                        type="number"
                                        id="tipo-descuento-crear"
                                        name="tipo_descuento"
                                        value="{{ $formularioTipoConError === 'crear' ? old('tipo_descuento', '0') : '0' }}"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        inputmode="decimal"
                                        data-descuento-tipo
                                        @if ($formularioTipoConError === 'crear' && $erroresTiposCliente->has('tipo_descuento')) aria-invalid="true" @endif
                                        required
                                    >
                                    @if ($formularioTipoConError === 'crear')
                                        @foreach ($erroresTiposCliente->get('tipo_descuento') as $error)
                                            <small class="campo-error">{{ $error }}</small>
                                        @endforeach
                                    @endif
                                </div>

                                <div class="campo formulario-tipo-cliente__campo-completo">
                                    <label for="tipo-descripcion-crear">Descripción</label>
                                    <input
                                        type="text"
                                        id="tipo-descripcion-crear"
                                        name="tipo_descripcion"
                                        value="{{ $formularioTipoConError === 'crear' ? old('tipo_descripcion') : '' }}"
                                        maxlength="50"
                                        placeholder="Ejemplo: Empresas constructoras"
                                    >
                                </div>

                                <div class="campo formulario-tipo-cliente__campo-completo">
                                    <span class="campo__etiqueta">Aplicar descuento en *</span>
                                    <div class="canales-tipo-cliente">
                                        <label class="canal-tipo-cliente">
                                            <input type="hidden" name="tipo_aplica_web" value="0">
                                            <input type="checkbox" name="tipo_aplica_web" value="1" @checked($formularioTipoConError === 'crear' ? (string) old('tipo_aplica_web', '1') === '1' : true)>
                                            <span>Web</span>
                                            <small>Tienda online</small>
                                        </label>
                                        <label class="canal-tipo-cliente">
                                            <input type="hidden" name="tipo_aplica_local" value="0">
                                            <input type="checkbox" name="tipo_aplica_local" value="1" @checked($formularioTipoConError === 'crear' ? (string) old('tipo_aplica_local', '1') === '1' : true)>
                                            <span>Local</span>
                                            <small>Nueva Venta</small>
                                        </label>
                                    </div>
                                    @if ($formularioTipoConError === 'crear')
                                        @foreach ($erroresTiposCliente->get('tipo_canales') as $error)
                                            <small class="campo-error">{{ $error }}</small>
                                        @endforeach
                                    @endif
                                </div>

                                <div class="campo formulario-tipo-cliente__campo-completo">
                                    <span class="campo__etiqueta">Estado inicial</span>
                                    <label class="interruptor-cliente">
                                        <input type="hidden" name="tipo_estado" value="0">
                                        <input type="checkbox" name="tipo_estado" value="1" @checked($formularioTipoConError === 'crear' ? (string) old('tipo_estado', '1') === '1' : true)>
                                        <span class="interruptor-cliente__control" aria-hidden="true"></span>
                                        <span>Crear como activo</span>
                                    </label>
                                </div>
                            </div>

                            <div class="formulario-tipo-cliente__acciones">
                                <button type="submit" class="boton boton--primario">Crear tipo</button>
                            </div>
                        </form>
                    </section>
                @endif

                <section class="tipos-cliente__seccion">
                    <div class="tipos-cliente__titulo-seccion">
                        <span>02</span>
                        <div>
                            <strong>Tipos registrados</strong>
                            <small>Los tipos inactivos no pueden asignarse a clientes nuevos.</small>
                        </div>
                    </div>

                    <div class="tipos-cliente__lista">
                        @foreach ($tiposCliente as $tipo)
                            @php
                                $tipoEsClienteWeb = $tipo->esClienteWeb();
                                $edicionTipoConError = $formularioTipoConError === 'editar'
                                    && $idTipoConError === (int) $tipo->idtipocliente;
                            @endphp

                            <details class="tipo-cliente-card" @if ($edicionTipoConError) open @endif>
                                <summary>
                                    <div class="tipo-cliente-card__resumen">
                                        <strong>{{ $tipo->nombre }}</strong>
                                        @if ($tipoEsClienteWeb)
                                            <span class="tipo-cliente-card__protegido">Protegido</span>
                                        @endif
                                        <small>{{ $tipo->descripcion ?: 'Sin descripción' }}</small>
                                    </div>
                                    <div class="tipo-cliente-card__datos">
                                        <span><b>{{ number_format((float) $tipo->descuento, 2, ',', '.') }}%</b> descuento</span>
                                        <span>{{ $tipo->aplica_web ? 'Web' : '' }}{{ $tipo->aplica_web && $tipo->aplica_local ? ' + ' : '' }}{{ $tipo->aplica_local ? 'Local' : '' }}</span>
                                        <span class="{{ $tipo->estado ? 'tipo-cliente-card__activo' : 'tipo-cliente-card__inactivo' }}">{{ $tipo->estado ? 'Activo' : 'Inactivo' }}</span>
                                        <span>{{ (int) ($tipo->clientes_count ?? 0) }} cliente(s)</span>
                                    </div>
                                </summary>

                                <div class="tipo-cliente-card__contenido">
                                    @if ($edicionTipoConError && $erroresTiposCliente->any())
                                        <section class="alerta alerta--error alerta--lista tipos-cliente__alerta" role="alert">
                                            <div>
                                                <strong>No pudimos actualizar este tipo.</strong>
                                                <ul>
                                                    @foreach ($erroresTiposCliente->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </section>
                                    @endif

                                    @if ($puedeEditarClientes)
                                        <form
                                            action="{{ route('clientes.tipos.update', ['tipoCliente' => $tipo->idtipocliente]) }}"
                                            method="POST"
                                            class="formulario-tipo-cliente"
                                            @if (!$tipoEsClienteWeb)
                                                data-confirmar-inactivacion-tipo="{{ (int) ($tipo->clientes_count ?? 0) }}"
                                                data-nombre-tipo="{{ $tipo->nombre }}"
                                            @endif
                                        >
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="_formulario_tipo" value="editar">
                                            <input type="hidden" name="_tipo_id" value="{{ $tipo->idtipocliente }}">

                                            <div class="formulario-tipo-cliente__grilla">
                                                <div class="campo">
                                                    <label>Nombre *</label>
                                                    @if ($tipoEsClienteWeb)
                                                        <input type="text" value="CLIENTE_WEB" disabled>
                                                        <input type="hidden" name="tipo_nombre" value="CLIENTE_WEB">
                                                    @else
                                                        <input
                                                            type="text"
                                                            name="tipo_nombre"
                                                            value="{{ $edicionTipoConError ? old('tipo_nombre') : $tipo->nombre }}"
                                                            maxlength="50"
                                                            data-nombre-tipo
                                                            @if ($edicionTipoConError && $erroresTiposCliente->has('tipo_nombre')) aria-invalid="true" @endif
                                                            required
                                                        >
                                                    @endif
                                                </div>

                                                <div class="campo">
                                                    <label>Descuento sobre PVP (%) *</label>
                                                    @if ($tipoEsClienteWeb)
                                                        <input type="number" value="0.00" disabled>
                                                        <input type="hidden" name="tipo_descuento" value="0">
                                                    @else
                                                        <input
                                                            type="number"
                                                            name="tipo_descuento"
                                                            value="{{ $edicionTipoConError ? old('tipo_descuento') : number_format((float) $tipo->descuento, 2, '.', '') }}"
                                                            min="0"
                                                            max="100"
                                                            step="0.01"
                                                            inputmode="decimal"
                                                            data-descuento-tipo
                                                            @if ($edicionTipoConError && $erroresTiposCliente->has('tipo_descuento')) aria-invalid="true" @endif
                                                            required
                                                        >
                                                    @endif
                                                </div>

                                                <div class="campo formulario-tipo-cliente__campo-completo">
                                                    <label>Descripción</label>
                                                    <input
                                                        type="text"
                                                        name="tipo_descripcion"
                                                        value="{{ $edicionTipoConError ? old('tipo_descripcion') : $tipo->descripcion }}"
                                                        maxlength="50"
                                                    >
                                                </div>

                                                <div class="campo formulario-tipo-cliente__campo-completo">
                                                    <span class="campo__etiqueta">Aplicar descuento en *</span>
                                                    <div class="canales-tipo-cliente">
                                                        @if ($tipoEsClienteWeb)
                                                            <label class="canal-tipo-cliente canal-tipo-cliente--bloqueado">
                                                                <input type="checkbox" checked disabled>
                                                                <span>Web</span><small>Protegido</small>
                                                            </label>
                                                            <label class="canal-tipo-cliente canal-tipo-cliente--bloqueado">
                                                                <input type="checkbox" checked disabled>
                                                                <span>Local</span><small>Protegido</small>
                                                            </label>
                                                            <input type="hidden" name="tipo_aplica_web" value="1">
                                                            <input type="hidden" name="tipo_aplica_local" value="1">
                                                        @else
                                                            <label class="canal-tipo-cliente">
                                                                <input type="hidden" name="tipo_aplica_web" value="0">
                                                                <input type="checkbox" name="tipo_aplica_web" value="1" @checked($edicionTipoConError ? (string) old('tipo_aplica_web') === '1' : (bool) $tipo->aplica_web)>
                                                                <span>Web</span><small>Tienda online</small>
                                                            </label>
                                                            <label class="canal-tipo-cliente">
                                                                <input type="hidden" name="tipo_aplica_local" value="0">
                                                                <input type="checkbox" name="tipo_aplica_local" value="1" @checked($edicionTipoConError ? (string) old('tipo_aplica_local') === '1' : (bool) $tipo->aplica_local)>
                                                                <span>Local</span><small>Nueva Venta</small>
                                                            </label>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="campo formulario-tipo-cliente__campo-completo">
                                                    <span class="campo__etiqueta">Estado</span>
                                                    @if ($tipoEsClienteWeb)
                                                        <label class="interruptor-cliente interruptor-cliente--bloqueado">
                                                            <input type="checkbox" checked disabled>
                                                            <span class="interruptor-cliente__control" aria-hidden="true"></span>
                                                            <span>CLIENTE_WEB siempre activo</span>
                                                        </label>
                                                        <input type="hidden" name="tipo_estado" value="1">
                                                    @else
                                                        <label class="interruptor-cliente">
                                                            <input type="hidden" name="tipo_estado" value="0">
                                                            <input
                                                                type="checkbox"
                                                                class="estado-tipo-cliente"
                                                                name="tipo_estado"
                                                                value="1"
                                                                @checked($edicionTipoConError ? (string) old('tipo_estado') === '1' : (bool) $tipo->estado)
                                                            >
                                                            <span class="interruptor-cliente__control" aria-hidden="true"></span>
                                                            <span>Tipo habilitado</span>
                                                        </label>
                                                    @endif
                                                </div>
                                            </div>

                                            @if ($tipoEsClienteWeb)
                                                <div class="tipo-cliente-protegido-aviso">
                                                    <strong>Tipo protegido del sistema</strong>
                                                    <span>CLIENTE_WEB mantiene nombre, 0% de descuento, ambos canales y estado activo. Solo podés modificar su descripción.</span>
                                                </div>
                                            @endif

                                            <div class="formulario-tipo-cliente__acciones">
                                                <button type="submit" class="boton boton--primario">Guardar tipo</button>
                                            </div>
                                        </form>
                                    @else
                                        <p class="tipo-cliente-card__solo-lectura">No tenés permiso para modificar tipos de clientes.</p>
                                    @endif
                                </div>
                            </details>
                        @endforeach
                    </div>
                </section>
            </div>

            <footer class="modal-cliente__pie">
                <button type="button" class="boton boton--secundario" data-cerrar-modal-tipos>Cerrar</button>
            </footer>
        </div>
    </dialog>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('modal-nuevo-cliente');
            const botonAbrir = document.getElementById('boton-abrir-nuevo-cliente');
            const formularioFiltros = document.getElementById('formulario-filtros-clientes');
            const estadoFiltros = document.getElementById('estado-filtros-clientes');
            const formularioEditar = document.getElementById('formulario-editar-cliente');

            document.querySelectorAll('[data-solo-letras]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.value = campo.value.replace(/[^\p{L}\s'-]/gu, '');
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

            if (formularioFiltros) {
                const campoBusqueda = formularioFiltros.querySelector('#buscar');
                const selectores = formularioFiltros.querySelectorAll('select');
                let temporizador;

                function estado(mensaje) {
                    if (estadoFiltros) estadoFiltros.textContent = mensaje;
                }

                function enviar(restaurarFoco) {
                    window.clearTimeout(temporizador);
                    if (restaurarFoco) {
                        window.sessionStorage.setItem('clientes-restaurar-busqueda', '1');
                    } else {
                        window.sessionStorage.removeItem('clientes-restaurar-busqueda');
                    }
                    estado('Buscando...');
                    formularioFiltros.requestSubmit();
                }

                if (campoBusqueda) {
                    campoBusqueda.addEventListener('input', function () {
                        window.clearTimeout(temporizador);
                        estado('Esperando que termines de escribir...');
                        temporizador = window.setTimeout(function () {
                            enviar(true);
                        }, 500);
                    });

                    if (window.sessionStorage.getItem('clientes-restaurar-busqueda') === '1') {
                        window.sessionStorage.removeItem('clientes-restaurar-busqueda');
                        campoBusqueda.focus();
                        campoBusqueda.setSelectionRange(campoBusqueda.value.length, campoBusqueda.value.length);
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
                    const estabaActivo = formularioEditar.dataset.clienteEstabaActivo === '1';

                    if (estado && estabaActivo && !estado.checked) {
                        const nombre = formularioEditar.dataset.nombreCliente;
                        const confirmado = window.confirm(
                            'Al inactivar a ' + nombre
                            + ', dejará de aparecer en la selección de clientes de Nueva Venta. ¿Querés continuar?'
                        );

                        if (!confirmado) evento.preventDefault();
                    }
                });
            }
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalTipos = document.getElementById('modal-tipos-cliente');
            const botonAbrirTipos = document.getElementById('boton-abrir-tipos-cliente');

            function abrirModalTipos() {
                if (!modalTipos) return;
                if (typeof modalTipos.showModal === 'function') {
                    if (!modalTipos.open) modalTipos.showModal();
                } else {
                    modalTipos.setAttribute('open', 'open');
                }
            }

            function cerrarModalTipos() {
                if (!modalTipos) return;
                if (typeof modalTipos.close === 'function') modalTipos.close();
                else modalTipos.removeAttribute('open');
            }

            if (botonAbrirTipos) botonAbrirTipos.addEventListener('click', abrirModalTipos);

            document.querySelectorAll('[data-cerrar-modal-tipos]').forEach(function (boton) {
                boton.addEventListener('click', cerrarModalTipos);
            });

            if (modalTipos) {
                modalTipos.addEventListener('click', function (evento) {
                    if (evento.target === modalTipos) cerrarModalTipos();
                });

                if (modalTipos.dataset.abrirPorError === '1') {
                    abrirModalTipos();
                    window.setTimeout(function () {
                        const campoConError = modalTipos.querySelector('[aria-invalid="true"]');
                        if (campoConError) campoConError.focus();
                    }, 0);
                }
            }

            document.querySelectorAll('[data-nombre-tipo]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    campo.value = campo.value
                        .replace(/[^\p{L}0-9\s'-]/gu, '')
                        .toUpperCase();
                });
            });

            document.querySelectorAll('[data-descuento-tipo]').forEach(function (campo) {
                campo.addEventListener('input', function () {
                    if (campo.value === '') return;
                    const numero = Number(campo.value);
                    if (Number.isNaN(numero)) return;
                    if (numero < 0) campo.value = '0';
                    if (numero > 100) campo.value = '100';
                });
            });

            document.querySelectorAll('[data-confirmar-inactivacion-tipo]').forEach(function (formulario) {
                formulario.addEventListener('submit', function (evento) {
                    const estado = formulario.querySelector('.estado-tipo-cliente');
                    const cantidadClientes = Number(formulario.dataset.confirmarInactivacionTipo || '0');
                    const nombreTipo = formulario.dataset.nombreTipo || 'este tipo';

                    if (estado && !estado.checked && cantidadClientes > 0) {
                        const confirmado = window.confirm(
                            nombreTipo + ' está asignado a ' + cantidadClientes
                            + ' cliente(s). Los clientes conservarán el tipo, pero ya no podrá asignarse a nuevos clientes. ¿Querés continuar?'
                        );

                        if (!confirmado) evento.preventDefault();
                    }
                });
            });
        });
    </script>

</body>
</html>
