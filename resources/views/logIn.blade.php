<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar Sesión - EGAU Chess</title>
    <!-- Fuentes de Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <!-- Hoja de estilos externa -->
    <link rel="stylesheet" href="{{ asset('css/login/logIn.css') }}">
</head>

<body>

    <div class="wrapper">

        <!-- ===== CAJA IZQUIERDA: Logo AMAAC ===== -->
        <div class="box-logo">
            <a href="{{ route('landing') }}">
                <img src="{{ asset('Logos/LogoAmaac.png') }}" alt="Logo AMAAC">
            </a>
            <span class="amaac-label">Asociación AMAAC</span>
        </div>

        <!-- ===== CAJA DERECHA: Formulario + Logo EGAU ===== -->
        <div class="box-form">

            <!-- Logo EGAU (el que no dice ALT) -->
            <a href="{{ route('landing') }}">
                <img src="{{ asset('Logos/LogoEgau.png') }}" alt="Logo EGAU Chess" class="egau-logo">
            </a>

            <!-- Nombre con fuente elegante -->
            <h1 class="egau-name">EGAU Chess</h1>

            <!-- Tabs con pill naranja deslizante -->
            <div class="tabs">
                <!-- Pill animado -->
                <div id="tab-pill"></div>
                <button class="tab-btn active" id="tab-alumno" type="button"
                    onclick="switchTab('alumno')">Alumno</button>
                <button class="tab-btn" id="tab-personal" type="button"
                    onclick="switchTab('personal')">Personal</button>
            </div>

            <!-- Mensaje de error del servidor (si aplica) -->
            @if ($errors->any())
                <div class="server-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Formulario conectado al backend -->
            <form id="login-form" action="{{ route('login.post') }}" method="POST" style="width:100%;" novalidate>
                @csrf
                <input type="hidden" id="tipo-usuario" name="tipo" value="{{ old('tipo', 'alumno') }}">

                <div class="form-group">
                    <label for="username">Correo / Usuario</label>
                    <input type="email" id="username" name="username" placeholder="Ingresa tu correo" required
                         value="{{ old('username') }}" autocomplete="off">
                    <span class="error-msg" id="error-username">
                        {{ $errors->first('username') }}
                    </span>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                 <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required
                     minlength="6" autocomplete="new-password"> 
                    <div class="forgot-wrapper">
                        <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
                    </div>
                    <span class="error-msg" id="error-password">
                        {{ $errors->first('password') }}
                    </span>
                </div>

                <button type="submit" class="login-btn">Iniciar sesión</button>
            </form>

        </div>
    </div>

    <!-- JS de animaciones y validación del lado del cliente -->
    <script src="{{ asset('animaciones/login/logIn.js') }}"></script>

</body>

</html>