<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="stylesheet" href="{{ asset('css/recuperarcontra.css') }}">
  <title>Recuperar Contraseña - Mes.Sirve Pinturería</title>
</head>
<body>
  <div class="contenedor">
    <!-- Lado izquierdo -->
    <div class="logo-superior">
      <img src="{{ asset('images/rodillossjblue.png') }}" alt="Rodillo superior azul">
    </div>

    <div class="lado-izquierdo">
      <div class="logo-central">
        <h1>MES.<span>SIRVE</span></h1>
        <h2>Pinturería</h2>
        <div class="rodillo">
          <img src="{{ asset('images/rodillologo.png') }}" alt="Rodillo inferior">
        </div>
      </div>

      <div class="bienvenida">
        <h3>Bienvenido a</h3>
        <h4>Mes.Sirve Pinturería</h4>
        <div class="subrayado"></div>
      </div>

      <div class="redes-sociales">
        <div class="red"><img src="{{ asset('images/ig.png') }}" alt="Instagram"><p>Instagram</p></div>
        <div class="red"><img src="{{ asset('images/feis.png') }}" alt="Facebook"><p>Facebook</p></div>
        <div class="red"><img src="{{ asset('images/wapp.png') }}" alt="WhatsApp"><p>WhatsApp</p></div>
      </div>
    </div>

    <!-- Lado derecho -->
    <div class="lado-derecho">
      <div class="circulos">
        <span></span><span></span><span></span>
        <span></span><span></span><span></span>
        <span></span><span></span><span></span>
      </div>

      <div class="login-box">
        <h2>Recuperar contraseña</h2>
        <div class="subrayado-titulo"></div>

        <div class="icono-email">
          <img src="{{ asset('images/emailazul.png') }}" alt="Ícono de correo grande">
        </div>

        <p class="texto-info">
          Ingresa tu correo y te enviaremos instrucciones<br>
          para restablecer tu contraseña
        </p>

        <form action="{{ route('password.email') }}" method="POST">
          @csrf

          @if (session('success'))
            <p class="mensaje-exito">{{ session('success') }}</p>
          @endif
          <div class="grupo-input">
            <img src="{{ asset('images/email2.png') }}" alt="Ícono de correo pequeño">
            <input
              type="email"
              name="email"
              value="{{ old('email') }}"
              placeholder="Email"
              maxlength="150"
              autocomplete="email"
              required>
          </div>

          @error('email')
            <p class="mensaje-error">{{ $message }}</p>
          @enderror

          <button type="submit">Enviar correo</button>
        </form>

        <div class="separador"></div>
        <p class="cuenta"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
      </div>
    </div>
  </div>
</body>
</html>
