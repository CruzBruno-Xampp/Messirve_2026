<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="stylesheet" href="{{ asset('css/register.css') }}">
  <title>Crear Cuenta - Mes.Sirve Pinturería</title>
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
        <h2>Crear Cuenta</h2>
        <div class="subrayado-titulo"></div>

        <form action="{{ route('cliente.register.store') }}" method="POST">
    @csrf

    @if (session('success'))
      <p class="mensaje-exito">
          {{ session('success') }}
      </p>
    @endif

     @error('registro')
        <p class="mensaje-error mensaje-error-general">
            {{ $message }}
        </p>
    @enderror
          <div class="fila-nombres">
            <div class="grupo-input mitad">
                <img src="{{ asset('images/usuario.png') }}" alt="Usuario">
                <input
                    type="text"
                    name="nombre"
                    value="{{ old('nombre') }}"
                    placeholder="Nombre"
                    maxlength="100"
                    autocomplete="given-name"
                    required>
            </div>
            <div class="grupo-input mitad">
              <img src="{{ asset('images/usuario.png') }}" alt="Usuario">
              <input
                    type="text"
                    name="apellido"
                    value="{{ old('apellido') }}"
                    placeholder="Apellido"
                    maxlength="100"
                    autocomplete="family-name"
                    required
                >
            </div>
          </div>
          @error('nombre')
              <p class="mensaje-error">{{ $message }}</p>
          @enderror

          @error('apellido')
              <p class="mensaje-error">{{ $message }}</p>
          @enderror
          <div class="grupo-input">
            <img src="{{ asset('images/email.png') }}" alt="Email">
              <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="Email"
                maxlength="150"
                autocomplete="email"
                required
            >
          </div>
             @error('email')
              <p class="mensaje-error">{{ $message }}</p>
            @enderror
          <div class="grupo-input">
            <img src="{{ asset('images/telefono.png') }}" alt="Teléfono">
            <input
              type="tel"
              name="telefono"
              value="{{ old('telefono') }}"
              placeholder="Teléfono"
              minlength="8"
              maxlength="15"
              inputmode="numeric"
              pattern="[0-9]{8,15}"
              title="Ingresá solamente números, entre 8 y 15 dígitos"
              autocomplete="tel"
              required
            >
          </div>
           @error('telefono')
            <p class="mensaje-error">{{ $message }}</p>
          @enderror
          <div class="grupo-input password">
            <img
                src="{{ asset('images/candadito.png') }}"
                alt="Contraseña">

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Contraseña"
                autocomplete="new-password"
                autocapitalize="off"
                spellcheck="false"
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

        <div class="grupo-input password">
            <img
                src="{{ asset('images/candadito.png') }}"
                alt="Confirmar contraseña">

          <input
              type="password"
              id="password_confirmation"
              name="password_confirmation"
              placeholder="Confirmar Contraseña"
              autocomplete="new-password"
              autocapitalize="off"
              spellcheck="false"
              class="no-native-eye"
              required>

          <span
              class="material-symbols-outlined toggle-password"
              onclick="togglePassword('password_confirmation', this)"
          >
              visibility_off
          </span>
      </div>

          <button type="submit">Crear Cuenta</button>
        </form>

        <div class="separador"></div>
        <p class="cuenta">¿Ya tienes una cuenta? <a href="/login">Iniciar sesión acá</a></p>
      </div>
    </div>
  </div>

  <!-- Script para alternar el ojo -->
  <script>
    function togglePassword(id, icon) {
      const input = document.getElementById(id);
      
      if (input.type === "password") {
        input.type = "text";
        icon.textContent = "visibility"; // vuelve al ojo abierto
      } else {
        input.type = "password";
        
        icon.textContent = "visibility_off"; // cambia al ojo tachado
      }
    }
  </script>

  <!-- Importa los íconos de Google -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</body>
</html>
