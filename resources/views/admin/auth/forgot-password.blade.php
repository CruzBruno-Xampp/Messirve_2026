<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Recuperar contraseña administrativa | Messirve</title>

    <link rel="stylesheet" href="{{ asset('css/admin/login.css') }}">
</head>
<body>
    <main class="pagina-login">
        <section class="panel-marca">
            <div class="marca">
                <span>MES.</span>SIRVE
            </div>

            <div class="panel-marca__contenido">
                <p class="etiqueta">Panel administrativo</p>

                <h1>Todo el negocio, en un solo lugar.</h1>

                <p>
                    Acceso interno para administrar productos, stock,
                    compras y ventas de la pinturería.
                </p>

                <div class="escena-pintura" aria-hidden="true">
                    <div class="escena-pintura__pared">
                        <span class="escena-pintura__trazo escena-pintura__trazo--1"></span>
                        <span class="escena-pintura__trazo escena-pintura__trazo--2"></span>
                        <span class="escena-pintura__trazo escena-pintura__trazo--3"></span>

                        <div class="rodillo-pintura">
                            <span class="rodillo-pintura__carga"></span>
                            <span class="rodillo-pintura__brazo"></span>
                            <span class="rodillo-pintura__mango"></span>
                        </div>
                    </div>

                    <div class="escena-pintura__leyenda">
                        <span class="escena-pintura__punto"></span>
                        Gestión interna Messirve
                    </div>
                </div>
            </div>

            <small>MESSIRVE · Sistema interno</small>
        </section>

        <section class="panel-formulario">
            <div class="formulario-contenedor">
                <header class="formulario-encabezado">
                    <span class="formulario-encabezado__icono">M</span>

                    <div>
                        <p>Recuperación</p>
                        <h2>Recuperar contraseña</h2>
                    </div>
                </header>

                <p class="texto-ayuda-auth">
                    Ingresá el correo de tu cuenta administrativa. Si la cuenta
                    está activa, te enviaremos un enlace para crear una contraseña nueva.
                </p>

                @if ($errors->any())
                    <section class="alerta alerta--error" role="alert">
                        <strong>No se pudo continuar.</strong>
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
                    action="{{ route('admin.password.email') }}"
                    method="POST"
                    class="formulario"
                >
                    @csrf

                    <div class="campo">
                        <label for="email">Correo electrónico</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            maxlength="150"
                            autocomplete="email"
                            placeholder="empleado@messirve.com"
                            autofocus
                            required
                        >
                    </div>

                    <button type="submit" class="boton-ingresar">
                        Enviar enlace de recuperación
                        <span aria-hidden="true">→</span>
                    </button>
                </form>

                <a href="{{ route('admin.login') }}" class="volver-sitio">
                    ← Volver a iniciar sesión
                </a>
            </div>
        </section>
    </main>
</body>
</html>
