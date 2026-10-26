<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="stylesheet" href="{{ asset('css/login.css') }}">
  <title>Iniciar Sesión - Mes.Sirve Pinturería</title>
</head>
<body>
  <div class="contenedor">
    <!-- Lado izquierdo -->
    <div class="logo-superior">
        <img src="{{ asset('images/rodillossj.png') }}" alt="Logo superior Mes.Sirve">
    </div>
    <div class="lado-izquierdo">
      <div class="logo-central">
        <h1>MES.<span>SIRVE</span></h1>
        <h2>Pinturería</h2>
        <div class="rodillo">
          <img src="{{ asset('images/rodillologo.png') }}" alt="Rodillo">
        </div>

        <div class="pintura-animada" aria-hidden="true">
          <div class="pintura-animada__pista">
            <span class="pintura-animada__trazo"></span>
            <span class="pintura-animada__brillo"></span>

            <div class="mini-rodillo">
              <span class="mini-rodillo__carga"></span>
              <span class="mini-rodillo__soporte"></span>
              <span class="mini-rodillo__mango"></span>
            </div>
          </div>
        </div>
      </div>

        <div class="bienvenida">
          <h3>Bienvenido a</h3>
          <h4>Mes.Sirve Pinturería</h4>
          <div class="subrayado"></div>
        </div>

      <div class="redes-sociales">
        <a
          class="red red--enlace"
          href="https://www.instagram.com/pintureriamessirve/"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Abrir Instagram de Mes.Sirve Pinturería"
        >
          <img src="{{ asset('images/ig.png') }}" alt="">
          <p>Instagram</p>
        </a>

        <a
          class="red red--enlace"
          href="https://www.facebook.com/messirvepintu"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Abrir Facebook de Mes.Sirve Pinturería"
        >
          <img src="{{ asset('images/feis.png') }}" alt="">
          <p>Facebook</p>
        </a>

        <div class="red red--inactiva" aria-label="WhatsApp próximamente">
          <img src="{{ asset('images/wapp.png') }}" alt="WhatsApp">
          <p>WhatsApp</p>
        </div>
      </div>
    </div>

    <!-- Lado derecho -->
    <div class="lado-derecho">
      <div class="circulos">
        <span></span><span></span><span></span>
        <span></span><span></span><span></span>
        <span></span><span></span><span></span>
      </div>
<div class="pincel-fondo">
        <img src="{{ asset('images/pincellogo.png') }}" alt="Pincel decorativo">
      </div>
      <div class="login-box">
        <h2>Iniciar Sesión</h2>
        <div class="subrayado-titulo"></div>

        <form action="{{ route('cliente.login.store') }}" method="POST">
          @csrf

          @if (session('success'))
            <p class="mensaje-exito">{{ session('success') }}</p>
          @endif

          @error('login')
            <p class="mensaje-error mensaje-error-general">{{ $message }}</p>
          @enderror
          <div class="grupo-input">
            <img src="{{ asset('images/usuario.png') }}" alt="Usuario">
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

          <div class="grupo-input password">
            <img src="{{ asset('images/candadito.png') }}" alt="Contraseña">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Contraseña"
              autocomplete="current-password"
              class="no-native-eye"
              required>
            <span
              class="material-symbols-outlined toggle-password"
              onclick="togglePassword('password', this)"
            >
              visibility_off
            </span>
          </div>

          @error('password')
            <p class="mensaje-error">{{ $message }}</p>
          @enderror

          <div class="opciones">
            <div class="recordarme">
              <input type="checkbox" id="recordar">
              <label for="recordar">Recordarme</label>
            </div>
            <a href="{{ route('password.request') }}">¿Olvidaste la contraseña?</a>
          </div>

          <button type="submit">Ingresar</button>
        </form>

        <div class="separador"><span>o continúa con</span></div>

        <button class="google">
          <img src="{{ asset('images/google.png') }}" alt="Google">
          Continuar con Google
        </button>
      </div>
    </div>
  </div>

  <script>
    function togglePassword(id, icon) {
      const input = document.getElementById(id);

      if (input.type === "password") {
        input.type = "text";
        icon.textContent = "visibility";
      } else {
        input.type = "password";
        icon.textContent = "visibility_off";
      }
    }
  </script>

  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</body>
</html>
