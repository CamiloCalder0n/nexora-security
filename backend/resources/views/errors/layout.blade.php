<!doctype html>
<html lang="es">
<head>
    @include('layouts.partials.head')
</head>
<body class="d-flex align-items-center min-vh-100 py-4">
<main class="container text-center" style="max-width: 520px;">
    <div class="nx-tarjeta p-4 p-md-5">
        <div class="display-5 fw-semibold" style="color: var(--nx-acento);">@yield('codigo')</div>
        <h1 class="h4 mt-2">@yield('titulo')</h1>
        <p class="nx-texto-suave mb-4">@yield('mensaje')</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="javascript:history.back()" class="btn btn-light border">Volver</a>
            <a href="{{ url('/') }}" class="btn btn-primary">Ir al inicio</a>
        </div>
    </div>
    <p class="small nx-texto-suave mt-3 mb-0"><i class="bi bi-shield-lock me-1"></i>Nexora Security</p>
</main>
</body>
</html>
