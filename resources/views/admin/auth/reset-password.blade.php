<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Nueva contraseña administrativa | Messirve</title>

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
                        <p>Seguridad</p>
                        <h2>Crear nueva contraseña</h2>
                    </div>
                </header>

                <p class="texto-ayuda-auth">
                    Elegí una contraseña nueva para tu cuenta administrativa.
                </p>

                @if ($errors->any())
                    <section class="alerta alerta--error" role="alert">
                        <strong>Revisá los datos ingresados.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <form
                    action="{{ route('admin.password.update') }}"
                    method="POST"
                    class="formulario"
                    id="formulario-restablecer"
                >
                    @csrf

                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="campo">
                        <label for="email">Correo electrónico</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $email) }}"
                            maxlength="150"
                            autocomplete="email"
                            class="email-bloqueado"
                            readonly
                            required
                        >
                    </div>

                    <div class="campo">
                        <label for="password">Nueva contraseña</label>

                        <div class="campo-password">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                placeholder="Ingresá la nueva contraseña"
                                required
                            >

                            <button
                                type="button"
                                class="boton-password"
                                data-toggle-password="password"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false"
                            >
                                Mostrar
                            </button>
                        </div>

                        <ul class="requisitos-password" aria-live="polite">
                            <li class="requisito-password" data-requisito="longitud">
                                Mínimo 8 caracteres
                            </li>
                            <li class="requisito-password" data-requisito="mayuscula">
                                Al menos una letra mayúscula
                            </li>
                            <li class="requisito-password" data-requisito="simbolo">
                                Al menos un símbolo
                            </li>
                        </ul>
                    </div>

                    <div class="campo">
                        <label for="password_confirmation">Confirmar contraseña</label>

                        <div class="campo-password">
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                autocomplete="new-password"
                                placeholder="Repetí la nueva contraseña"
                                required
                            >

                            <button
                                type="button"
                                class="boton-password"
                                data-toggle-password="password_confirmation"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false"
                            >
                                Mostrar
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="boton-ingresar">
                        Guardar nueva contraseña
                        <span aria-hidden="true">→</span>
                    </button>
                </form>

                <a href="{{ route('admin.login') }}" class="volver-sitio">
                    ← Volver a iniciar sesión
                </a>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const password = document.getElementById('password');
            const requisitos = {
                longitud: document.querySelector('[data-requisito="longitud"]'),
                mayuscula: document.querySelector('[data-requisito="mayuscula"]'),
                simbolo: document.querySelector('[data-requisito="simbolo"]'),
            };

            function marcar(elemento, cumple) {
                if (!elemento) return;
                elemento.classList.toggle('requisito-password--cumplido', cumple);
            }

            function revisarPassword() {
                const valor = password.value;

                marcar(requisitos.longitud, valor.length >= 8);
                marcar(requisitos.mayuscula, /[A-Z]/.test(valor));
                marcar(requisitos.simbolo, /[^A-Za-z0-9\s]/.test(valor));
            }

            password.addEventListener('input', revisarPassword);
            revisarPassword();

            document.querySelectorAll('[data-toggle-password]').forEach(function (boton) {
                boton.addEventListener('click', function () {
                    const input = document.getElementById(this.dataset.togglePassword);
                    const mostrar = input.type === 'password';

                    input.type = mostrar ? 'text' : 'password';
                    this.textContent = mostrar ? 'Ocultar' : 'Mostrar';
                    this.setAttribute('aria-pressed', String(mostrar));
                    this.setAttribute(
                        'aria-label',
                        mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
                    );
                });
            });
        });
    </script>
</body>
</html>
