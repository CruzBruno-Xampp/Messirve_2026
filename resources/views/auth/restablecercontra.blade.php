<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="stylesheet" href="{{ asset('css/register.css') }}">
  <title>Nueva Contraseña - Mes.Sirve Pinturería</title>
</head>
<body>
  <div class="contenedor">
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

    <div class="lado-derecho">
      <div class="circulos">
        <span></span><span></span><span></span>
        <span></span><span></span><span></span>
        <span></span><span></span><span></span>
      </div>

      <div class="login-box">
        <h2>Crear nueva contraseña</h2>
        <div class="subrayado-titulo"></div>

        <p class="texto-info">
          Ingresá una nueva contraseña para tu cuenta.
        </p>

        <form action="{{ route('password.update') }}" method="POST">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">

          <div class="grupo-input">
            <img src="{{ asset('images/email2.png') }}" alt="Correo electrónico">
            <input
              type="email"
              name="email"
              value="{{ old('email', $email) }}"
              placeholder="Email"
              maxlength="150"
              autocomplete="email"
              required>
          </div>
          @error('email')
            <p class="mensaje-error">{{ $message }}</p>
          @enderror

          <div class="grupo-input password">
            <img src="{{ asset('images/candadito.png') }}" alt="Nueva contraseña">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Nueva Contraseña"
              autocomplete="new-password"
              class="no-native-eye"
              required>
            <span class="material-symbols-outlined toggle-password" onclick="togglePassword('password', this)">
              visibility_off
            </span>
          </div>
          @error('password')
            <p class="mensaje-error">{{ $message }}</p>
          @enderror

          <div class="grupo-input password">
            <img src="{{ asset('images/candadito.png') }}" alt="Confirmar contraseña">
            <input
              type="password"
              id="password_confirmation"
              name="password_confirmation"
              placeholder="Confirmar Contraseña"
              autocomplete="new-password"
              class="no-native-eye"
              required>
            <span class="material-symbols-outlined toggle-password" onclick="togglePassword('password_confirmation', this)">
              visibility_off
            </span>
          </div>

          <button type="submit">Guardar cambios</button>
        </form>

        <div class="separador"></div>
        <p class="cuenta"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
      </div>
    </div>
  </div>

  <script>
    function togglePassword(id, icon) {
      const input = document.getElementById(id);

      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility';
      } else {
        input.type = 'password';
        icon.textContent = 'visibility_off';
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
</body>
</html>
