<!doctype html>
<html lang="es">
<head>
    @include('layouts.partials.head')
</head>
<body>
@php($usuario = auth()->user())

<aside class="nx-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="menuPanel" aria-labelledby="menuPanelTitulo">
    <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
        <a href="{{ route('panel.inicio') }}" class="nx-marca fs-5" id="menuPanelTitulo">
            <i class="bi bi-shield-lock me-1"></i>Nexora
        </a>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menuPanel" aria-label="Cerrar menú"></button>
    </div>
    <div class="offcanvas-body d-block p-0 pt-1">
        @include('layouts.partials.menu')
    </div>
</aside>

<div class="nx-contenido">
    <header class="bg-white border-bottom">
        <div class="container-fluid px-3 px-md-4 py-2 d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-light border d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuPanel" aria-controls="menuPanel" aria-label="Abrir menú">
                <i class="bi bi-list"></i>
            </button>
            <span class="fw-semibold text-truncate">@yield('titulo', 'Panel')</span>
            <div class="ms-auto dropdown">
                <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i>
                    <span class="d-none d-sm-inline">{{ $usuario->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small nx-texto-suave">{{ $usuario->email }}</span></li>
                    <li><span class="dropdown-item-text small">Rol: <strong>{{ ucfirst($usuario->rol->value) }}</strong></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="container-fluid px-3 px-md-4 py-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h1 class="h4 mb-0">@yield('titulo', 'Panel')</h1>
                @hasSection('subtitulo')
                    <p class="nx-texto-suave small mb-0">@yield('subtitulo')</p>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">@yield('acciones')</div>
        </div>

        @include('layouts.partials.mensajes')

        @yield('contenido')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
@stack('scripts')
</body>
</html>
