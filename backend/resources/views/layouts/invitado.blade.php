<!doctype html>
<html lang="es">
<head>
    @include('layouts.partials.head')
</head>
<body class="d-flex align-items-center min-vh-100 py-4">
<main class="container" style="max-width: 420px;">
    <div class="text-center mb-4">
        <span class="nx-marca fs-3"><i class="bi bi-shield-lock me-1"></i>Nexora Security</span>
    </div>
    @yield('contenido')
</main>
</body>
</html>
