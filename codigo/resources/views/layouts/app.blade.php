<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Registro Civil') | Municipalidad</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('img/logo.jpg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/interface-clarity.css') }}?v={{ filemtime(public_path('css/interface-clarity.css')) }}" rel="stylesheet">
    @stack('estilos')
    <link href="{{ asset('css/municipal-sistema.css') }}?v={{ filemtime(public_path('css/municipal-sistema.css')) }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/formularios-municipales.css') }}?v={{ filemtime(public_path('css/formularios-municipales.css')) }}"></head>
<body class="municipal-system">
    <a class="skip-content" href="#contenido-principal">Ir al contenido principal</a>
    <div class="d-flex" id="app-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar-municipal d-flex flex-column p-3" id="sidebar">
            <button type="button" id="cerrarMenu" class="btn btn-outline-light d-lg-none align-self-end mb-2" aria-label="Cerrar menú">Cerrar ×</button>
            <div class="text-center mb-4">
                <img src="{{ asset('img/logo.jpg') }}" alt="Escudo Municipal" style="width:70px;height:70px;object-fit:contain;">
                <h6 class="text-white mt-2 mb-0">MUNICIPALIDAD DISTRITAL</h6>
                <small class="text-white-50">Andrés Avelino Cáceres Dorregaray</small>
            </div>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-section-label" aria-hidden="true">ATENCIÓN Y ARCHIVO</li>
                <li class="nav-item"><a href="{{ route('atencion.index') }}" class="nav-link {{ request()->routeIs('atencion.*') ? 'active' : '' }}"><i class="bi bi-inbox me-2"></i> Solicitudes ciudadanas</a></li>
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid me-2"></i> Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('actas.create') }}" class="nav-link {{ request()->routeIs('actas.create') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-plus me-2"></i> Registrar acta
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('actas.index') }}" class="nav-link {{ request()->routeIs('actas.index') || request()->routeIs('actas.show') ? 'active' : '' }}">
                        <i class="bi bi-search me-2"></i> Buscar actas
                    </a>
                </li>
                <li class="nav-item"><a href="{{ route('reportes.index') }}" class="nav-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}"><i class="bi bi-bar-chart-line me-2"></i> Reportes</a></li>
                <li class="nav-item"><a href="{{ route('incidencias.index') }}" class="nav-link {{ request()->routeIs('incidencias.*') ? 'active' : '' }}"><i class="bi bi-exclamation-circle me-2"></i> Incidencias @if($pendientesPdfMenu > 0)<span class="incidencias-notification badge rounded-pill text-bg-danger ms-2" aria-label="{{ $pendientesPdfMenu }} {{ $pendientesPdfMenu === 1 ? 'acta pendiente' : 'actas pendientes' }} de PDF">{{ $pendientesPdfMenu }}</span>@endif</a></li>
                <li class="nav-item"><a href="{{ route('perfil.edit') }}" class="nav-link {{ request()->routeIs('perfil.*') ? 'active' : '' }}"><i class="bi bi-person-circle me-2"></i> Mi perfil</a></li>
                @can('viewAny', App\Models\User::class)
                <li class="nav-section-label" aria-hidden="true">ADMINISTRACIÓN</li>
                <li class="nav-item">
                    <a href="{{ route('usuarios.index') }}" class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                        <i class="bi bi-people me-2"></i> Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('auditoria.index') }}" class="nav-link {{ request()->routeIs('auditoria.*') ? 'active' : '' }}">
                        <i class="bi bi-shield-check me-2"></i> Historial de actividades
                    </a>
                </li>

                @endcan
            </ul>
            <div class="mt-auto pt-3 border-top border-light-subtle">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-link text-white-50 text-decoration-none p-0">
                        <i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión
                    </button>
                </form>
            </div>
        </nav>
        <button type="button" id="fondoMenu" class="fondo-menu" aria-label="Cerrar menú" hidden></button>

        <!-- Contenido -->
        <div class="flex-grow-1 contenido-app">
            <header class="header-municipal d-flex align-items-center justify-content-between px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="abrirMenu" class="btn btn-outline-primary d-lg-none" aria-controls="sidebar" aria-expanded="false">☰ Menú</button>
                    <div class="system-identity"><span>Registro Civil</span><small>Gestión municipal</small></div>
                </div>
                <div class="profile-box d-flex align-items-center justify-content-end gap-3">
                    <div class="profile-avatar d-flex align-items-center justify-content-center">
                        @if(auth()->user()->foto)<img src="{{ route('perfil.foto') }}" alt="Su foto de perfil" style="width:100%;height:100%;object-fit:cover;border-radius:50%">@else{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@endif
                    </div>
                    <div class="text-start">
                        <div class="fw-semibold profile-name">{{ auth()->user()->name }}</div>
                        <small class="profile-role">
                            {{ auth()->user()->isAdmin() ? 'Administrador' : 'Asistente' }}
                        </small>
                    </div>
                    <a href="{{ route('perfil.edit') }}" class="profile-action d-flex align-items-center justify-content-center" aria-label="Abrir mi perfil"><i class="bi bi-person" aria-hidden="true"></i></a>
                </div>
            </header>

            <main id="contenido-principal" class="p-4 ui-clear" tabindex="-1">
                @if (session('exito'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar aviso"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar aviso"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Se encontraron errores:</strong>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar aviso"></button>
                    </div>
                @endif

                @hasSection('descripcion')
                <div class="page-heading mb-4"><h1 class="h2 fw-bold mb-2">@yield('titulo')</h1><p class="text-muted mb-0">@yield('descripcion')</p></div>
                @endif
                @yield('contenido')
            </main>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
<script>
// Menú en pantallas pequeñas, con cierre y manejo del foco.
(function () {
    const panel = document.getElementById('sidebar');
    const abrir = document.getElementById('abrirMenu');
    const cerrar = document.getElementById('cerrarMenu');
    const fondo = document.getElementById('fondoMenu');
    if (!panel || !abrir || !cerrar || !fondo) return;
    const movil = window.matchMedia('(max-width: 991.98px)');
    function estado(abierto, devolverFoco) {
        panel.classList.toggle('abierto', abierto);
        abrir.setAttribute('aria-expanded', String(abierto));
        fondo.hidden = !abierto;
        document.body.classList.toggle('menu-abierto', abierto);
        panel.inert = movil.matches && !abierto;
        if (abierto) cerrar.focus();
        else if (devolverFoco) abrir.focus();
    }
    abrir.addEventListener('click', () => estado(true));
    cerrar.addEventListener('click', () => estado(false, true));
    fondo.addEventListener('click', () => estado(false, true));
    document.addEventListener('keydown', function (e) {
        if (!panel.classList.contains('abierto')) return;
        if (e.key === 'Escape') estado(false, true);
        if (e.key === 'Tab') {
            const elementos = [...panel.querySelectorAll('a[href],button')].filter(el => el.offsetParent !== null);
            const primero = elementos[0], ultimo = elementos[elementos.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        }
    });
    movil.addEventListener('change', () => estado(false));
    estado(false);
})();
</script>
    @stack('scripts')
<script src="{{ asset('js/municipal-interfaz.js') }}?v={{ filemtime(public_path('js/municipal-interfaz.js')) }}"></script>
<script src="{{ asset('js/password-visibility.js') }}?v={{ filemtime(public_path('js/password-visibility.js')) }}"></script>
<script src="{{ asset('js/formularios-municipales.js') }}?v={{ filemtime(public_path('js/formularios-municipales.js')) }}"></script></body>
</html>
