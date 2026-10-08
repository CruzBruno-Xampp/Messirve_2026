@php
    $usuarioAdmin = auth('web')->user();
    $puedeVerProductos = $usuarioAdmin?->tienePermiso('productos.ver') ?? false;
    $puedeVerCatalogo = $usuarioAdmin?->tienePermiso('catalogos.ver') ?? false;
    $puedeVerStock = $usuarioAdmin?->tienePermiso('stock.ver') ?? false;
    $puedeVerVentas = $usuarioAdmin?->tienePermiso('ventas.ver') ?? false;
    $puedeVerClientes = $usuarioAdmin?->tienePermiso('clientes.ver') ?? false;
    $puedeVerProveedores = $usuarioAdmin?->tienePermiso('proveedores.ver') ?? false;
    $puedeVerReportes = $usuarioAdmin?->tienePermiso('reportes.ver') ?? false;
    $puedeGestionarUsuarios = $usuarioAdmin?->tienePermiso('usuarios.gestionar') ?? false;
    $puedeGestionarRoles = $usuarioAdmin?->tienePermiso('roles.gestionar') ?? false;
    $mostrarModuloProductos = $puedeVerProductos || $puedeVerCatalogo;

    $moduloProductosActivo = request()->routeIs('productos.*')
        || request()->routeIs('catalogos.importar*');

    $moduloUsuariosActivo = request()->routeIs('usuarios.*');
    $moduloRolesActivo = request()->routeIs('roles.*');
    $moduloStockActivo = request()->routeIs('stock.*');
    $moduloVentasActivo = request()->routeIs('ventas.*');
    $moduloClientesActivo = request()->routeIs('clientes.*');
    $moduloProveedoresActivo = request()->routeIs('proveedores.*');
    $moduloReportesActivo = request()->routeIs('reportes.*');
@endphp

<aside
    id="barra-lateral-admin"
    class="barra-lateral-admin"
    aria-label="Navegación administrativa"
>
    <div class="barra-lateral__cabecera">
        <a
            href="{{ route('productos.index') }}"
            class="barra-lateral__marca"
            aria-label="Messirve, administración"
        >
            <span class="barra-lateral__marca-icono" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M3 5.5h11.2a2.8 2.8 0 0 1 2.8 2.8v.2h2a2 2 0 0 1 2 2v2.2a2 2 0 0 1-2 2h-5.4v5.8a1.5 1.5 0 0 1-3 0v-7.3a1.5 1.5 0 0 1 1.5-1.5H19v-1.2h-2.1A2.8 2.8 0 0 1 14.2 8H3a2 2 0 0 1-2-2V4.5a2 2 0 0 1 2-2h11a1.5 1.5 0 0 1 0 3H3v0Z"/>
                </svg>
            </span>

            <span class="barra-lateral__marca-texto">
                <strong>MES.SIRVE</strong>
                <small>Pinturería</small>
            </span>
        </a>

        <button
            type="button"
            id="boton-menu-administrativo"
            class="barra-lateral__boton-movil"
            aria-controls="navegacion-administrativa"
            aria-expanded="false"
            aria-label="Abrir menú de administración"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <nav
        id="navegacion-administrativa"
        class="barra-lateral__navegacion"
        aria-label="Módulos del sistema"
    >
        <span class="barra-lateral__titulo">Menú</span>

        <span
            class="barra-lateral__item barra-lateral__item--deshabilitado"
            aria-disabled="true"
            title="Módulo pendiente"
        >
            <span class="barra-lateral__icono" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/>
                </svg>
            </span>
            <span class="barra-lateral__texto">Dashboard</span>
        </span>

        @if ($mostrarModuloProductos)
        <div class="barra-lateral__grupo barra-lateral__grupo--productos">
            <button
                type="button"
                id="boton-menu-productos"
                class="barra-lateral__item barra-lateral__item--boton{{ $moduloProductosActivo ? ' barra-lateral__item--activo' : '' }}"
                aria-controls="submenu-productos"
                aria-expanded="false"
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="m12 2 9 5-9 5-9-5 9-5Zm-7 8.4 6 3.3v7.8l-6-3.3v-7.8Zm8 11.1v-7.8l6-3.3v7.8l-6 3.3Z"/>
                    </svg>
                </span>

                <span class="barra-lateral__texto">Productos</span>

                <svg
                    class="barra-lateral__flecha"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path d="m9 18 6-6-6-6"/>
                </svg>
            </button>

            <div
                id="submenu-productos"
                class="barra-lateral__submenu"
                aria-label="Acciones de productos"
                hidden
            >
                <div class="barra-lateral__submenu-cabecera">
                    <strong>Productos</strong>
                    <small>Elegí una acción</small>
                </div>

                @if ($puedeVerProductos)
                    <a
                    href="{{ route('productos.index') }}"
                    class="barra-lateral__submenu-enlace{{ request()->routeIs('productos.index') ? ' barra-lateral__submenu-enlace--activo' : '' }}"
                    @if (request()->routeIs('productos.index')) aria-current="page" @endif
                >
                    <span class="barra-lateral__submenu-icono" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M4 5h16v4H4V5Zm0 6h16v4H4v-4Zm0 6h16v2H4v-2Z"/>
                        </svg>
                    </span>
                    <span>
                        <strong>Ver productos</strong>
                        <small>Productos ya cargados</small>
                    </span>
                    </a>
                @endif

                @if ($puedeVerCatalogo)
                    <a
                    href="{{ route('productos.catalogo') }}"
                    class="barra-lateral__submenu-enlace{{ request()->routeIs('productos.catalogo*') ? ' barra-lateral__submenu-enlace--activo' : '' }}"
                    @if (request()->routeIs('productos.catalogo*')) aria-current="page" @endif
                >
                    <span class="barra-lateral__submenu-icono" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M3 4h8v7H3V4Zm10 0h8v7h-8V4ZM3 13h8v7H3v-7Zm10 0h8v7h-8v-7Z"/>
                        </svg>
                    </span>
                    <span>
                        <strong>Ver catálogo</strong>
                        <small>Artículos importados</small>
                    </span>
                    </a>
                @endif
            </div>
        </div>
        @endif

        @if ($puedeVerStock)
            <a
                href="{{ route('stock.index') }}"
                class="barra-lateral__item{{ $moduloStockActivo ? ' barra-lateral__item--activo' : '' }}"
                @if ($moduloStockActivo) aria-current="page" @endif
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3 7 12 2l9 5v10l-9 5-9-5V7Zm9 7.2L18.7 10 12 6.2 5.3 10l6.7 4.2Zm-7-2.4v4l6 3.3v-3.2l-6-4.1Zm8 7.3 6-3.3v-4L13 16v3.1Z"/>
                    </svg>
                </span>

                <span class="barra-lateral__texto">Stock</span>
            </a>
        @endif

        @if ($puedeVerVentas)
            <a
                href="{{ route('ventas.index') }}"
                class="barra-lateral__item{{ $moduloVentasActivo ? ' barra-lateral__item--activo' : '' }}"
                @if ($moduloVentasActivo) aria-current="page" @endif
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3 3h2l2 11h10l2-7H7.4L7 5h14l-2.5 11H6L3 3Zm5 15a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm9 0a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/>
                    </svg>
                </span>

                <span class="barra-lateral__texto">Ventas</span>
            </a>
        @endif

        @if ($puedeVerClientes)
            <a
                href="{{ route('clientes.index') }}"
                class="barra-lateral__item{{ $moduloClientesActivo ? ' barra-lateral__item--activo' : '' }}"
                @if ($moduloClientesActivo) aria-current="page" @endif
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-1a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20v-2a6 6 0 0 1 12 0v2H2Zm13 0v-2a7.9 7.9 0 0 0-1.2-4.2A5 5 0 0 1 22 18v2h-7Z"/>
                    </svg>
                </span>
                <span class="barra-lateral__texto">Clientes</span>
            </a>
        @endif

        @if ($puedeVerProveedores)
            <a
                href="{{ route('proveedores.index') }}"
                class="barra-lateral__item{{ $moduloProveedoresActivo ? ' barra-lateral__item--activo' : '' }}"
                @if ($moduloProveedoresActivo) aria-current="page" @endif
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3 4h12v10H3V4Zm13 4h3l3 4v5h-2a3 3 0 0 0-6 0h-4a3 3 0 0 0-6 0H2v-2h15V9h-1V8ZM7 15a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm10 0a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/>
                    </svg>
                </span>
                <span class="barra-lateral__texto">Proveedores</span>
            </a>
        @endif

        @if ($puedeVerReportes)
            <a
                href="{{ route('reportes.index') }}"
                class="barra-lateral__item{{ $moduloReportesActivo ? ' barra-lateral__item--activo' : '' }}"
                @if ($moduloReportesActivo) aria-current="page" @endif
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4 20V10h3v10H4Zm6 0V4h3v16h-3Zm6 0v-7h3v7h-3ZM3 22h18v-2H3v2Z"/>
                    </svg>
                </span>

                <span class="barra-lateral__texto">Reportes</span>
            </a>
        @endif

        @if ($puedeGestionarUsuarios)
            <a
                href="{{ route('usuarios.index') }}"
                class="barra-lateral__item{{ $moduloUsuariosActivo ? ' barra-lateral__item--activo' : '' }}"
                @if ($moduloUsuariosActivo) aria-current="page" @endif
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6-1a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20v-2a6 6 0 0 1 12 0v2H2Zm13 0v-2a7.9 7.9 0 0 0-1.2-4.2A5 5 0 0 1 22 18v2h-7Z"/>
                    </svg>
                </span>

                <span class="barra-lateral__texto">Personal</span>
            </a>
        @endif

        @if ($puedeGestionarRoles)
            <a
                href="{{ route('roles.index') }}"
                class="barra-lateral__item{{ $moduloRolesActivo ? ' barra-lateral__item--activo' : '' }}"
                @if ($moduloRolesActivo) aria-current="page" @endif
            >
                <span class="barra-lateral__icono" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 2 3 6v6c0 5.5 3.8 9.7 9 10 5.2-.3 9-4.5 9-10V6l-9-4Zm0 3.2 6 2.6V12c0 3.8-2.4 6.7-6 7-3.6-.3-6-3.2-6-7V7.8l6-2.6Zm0 2.8a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Zm-4 8.5c1-1.7 2.3-2.5 4-2.5s3 .8 4 2.5A6.2 6.2 0 0 1 12 18a6.2 6.2 0 0 1-4-1.5Z"/>
                    </svg>
                </span>

                <span class="barra-lateral__texto">Roles</span>
            </a>
        @endif
    </nav>

    <div class="barra-lateral__pie">
        <span>Panel administrativo</span>
    </div>
</aside>

<div
    id="fondo-menu-administrativo"
    class="barra-lateral__fondo"
    aria-hidden="true"
></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const barra = document.getElementById('barra-lateral-admin');
        const botonMovil = document.getElementById('boton-menu-administrativo');
        const fondo = document.getElementById('fondo-menu-administrativo');
        const botonProductos = document.getElementById('boton-menu-productos');
        const submenuProductos = document.getElementById('submenu-productos');

        if (!barra || !botonMovil || !fondo) {
            return;
        }

        function cerrarSubmenuProductos() {
            if (!botonProductos || !submenuProductos) {
                return;
            }

            submenuProductos.hidden = true;
            botonProductos.setAttribute('aria-expanded', 'false');
        }

        function alternarSubmenuProductos() {
            if (!botonProductos || !submenuProductos) {
                return;
            }

            const debeAbrirse = submenuProductos.hidden;

            submenuProductos.hidden = !debeAbrirse;
            botonProductos.setAttribute(
                'aria-expanded',
                debeAbrirse ? 'true' : 'false'
            );
        }

        function cerrarMenuMovil() {
            barra.classList.remove('barra-lateral-admin--abierta');
            document.body.classList.remove('menu-administrativo-abierto');
            botonMovil.setAttribute('aria-expanded', 'false');
        }

        function alternarMenuMovil() {
            const seAbrira = !barra.classList.contains(
                'barra-lateral-admin--abierta'
            );

            barra.classList.toggle('barra-lateral-admin--abierta', seAbrira);
            document.body.classList.toggle(
                'menu-administrativo-abierto',
                seAbrira
            );
            botonMovil.setAttribute(
                'aria-expanded',
                seAbrira ? 'true' : 'false'
            );
        }

        if (botonProductos && submenuProductos) {
            botonProductos.addEventListener('click', function (evento) {
                evento.stopPropagation();
                alternarSubmenuProductos();
            });
        }

        botonMovil.addEventListener('click', alternarMenuMovil);
        fondo.addEventListener('click', cerrarMenuMovil);

        document.addEventListener('click', function (evento) {
            if (!botonProductos) {
                return;
            }

            const grupoProductos = botonProductos.closest(
                '.barra-lateral__grupo--productos'
            );

            if (grupoProductos && !grupoProductos.contains(evento.target)) {
                cerrarSubmenuProductos();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrarSubmenuProductos();
                cerrarMenuMovil();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                cerrarMenuMovil();
            }
        });
    });
</script>
