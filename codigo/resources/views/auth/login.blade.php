<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión | Registro Civil</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
<link href="{{ asset('css/municipal-sistema.css') }}?v={{ filemtime(public_path('css/municipal-sistema.css')) }}" rel="stylesheet"></head>
<body class="municipal-system login-bg d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-lg login-card">
        <div class="card-body p-5">
           <div class="text-center mb-4">
           <img src="{{ asset('img/logo.jpg') }}" alt="Escudo Municipal" style="width:90px;height:90px;object-fit:contain;">
           <h5 class="fw-bold mt-2 mb-0">MUNICIPALIDAD DISTRITAL</h5>
           <p class="text-muted mb-0">Andrés Avelino Cáceres Dorregaray</p>
        <p class="text-muted small">Sistema Web de Registro Civil</p>
        </div>

            @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <h1 class="mb-3">Acceso del personal municipal</h1><form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label for="login-usuario" class="form-label">Usuario</label>
                    <input id="login-usuario" type="text" name="username" autocomplete="username" class="form-control" value="{{ old('username') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="login-clave" class="form-label">Contraseña</label>
                    <input id="login-clave" type="password" name="password" autocomplete="current-password" class="form-control" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
                </button>
            </form>
            <div class="text-center mt-3"><a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a><div class="small text-muted mt-1">Solo administradores. Asistentes: contacte al administrador.</div></div>
        </div>
    </div>
<script src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}"></script>
</body>
</html>
