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

    <title>Acceso administrativo | Messirve</title>

    <link
        rel="stylesheet"
        href="{{ asset('css/admin/login.css') }}"
    >
</head>

<body>

    <main class="pagina-login">

        <section class="panel-marca">
            <div class="marca">
                <span>MES.</span>SIRVE
            </div>

            <div class="panel-marca__contenido">
                <p class="etiqueta">Sistema interno</p>

                <h1>Administrá la pinturería desde un solo lugar.</h1>

                <p>
                    Acceso exclusivo para el dueño, gerentes
                    y empleados autorizados.
                </p>

                <ul>
                    <li>Catálogos y productos</li>
                    <li>Stock y proveedores</li>
                    <li>Ventas, compras y reportes</li>
                </ul>
            </div>

            <small>MESSIRVE · Panel administrativo</small>
        </section>

        <section class="panel-formulario">
            <div class="formulario-contenedor">

                <header class="formulario-encabezado">
                    <span class="formulario-encabezado__icono">M</span>

                    <div>
                        <p>Bienvenido</p>
                        <h2>Iniciar sesión administrativa</h2>
                    </div>
                </header>

                @if ($errors->any())
                    <section class="alerta alerta--error" role="alert">
                        <strong>No se pudo iniciar sesión.</strong>

                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if (session('success'))
                    <section class="alerta alerta--exito" role="status">
                        {{ session('success') }}
                    </section>
                @endif

                <form
                    action="{{ route('admin.login.store') }}"
                    method="POST"
                    class="formulario"
                >
                    @csrf

                    <div class="campo">
                        <label for="email">
                            Correo electrónico
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            maxlength="150"
                            autocomplete="username"
                            placeholder="empleado@messirve.com"
                            autofocus
                            required
                        >
                    </div>

                    <div class="campo">
                        <label for="password">
                            Contraseña
                        </label>

                        <div class="campo-password">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Ingresá tu contraseña"
                                required
                            >

                            <button
                                type="button"
                                id="alternar-password"
                                class="boton-password"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false"
                            >
                                Mostrar
                            </button>
                        </div>
                    </div>

                    <label class="recordar" for="recordar">
                        <input
                            type="checkbox"
                            id="recordar"
                            name="recordar"
                            value="1"
                            @checked(old('recordar'))
                        >

                        <span>Mantener la sesión iniciada</span>
                    </label>

                    <button type="submit" class="boton-ingresar">
                        Ingresar al sistema
                        <span aria-hidden="true">→</span>
                    </button>

                </form>

                <a href="{{ url('/') }}" class="volver-sitio">
                    ← Volver al sitio de clientes
                </a>

            </div>
        </section>

    </main>

    <script>
        const inputPassword = document.getElementById('password');
        const botonPassword = document.getElementById(
            'alternar-password'
        );

        botonPassword.addEventListener('click', function () {
            const mostrar = inputPassword.type === 'password';

            inputPassword.type = mostrar ? 'text' : 'password';
            this.textContent = mostrar ? 'Ocultar' : 'Mostrar';
            this.setAttribute('aria-pressed', String(mostrar));
            this.setAttribute(
                'aria-label',
                mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
            );
        });
    </script>

</body>
</html>
