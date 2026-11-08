<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('titulo')@yield('titulo') · @endif{{ config('app.name') }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    :root {
        --nx-fondo: #f5f6f8;
        --nx-borde: #e3e6ea;
        --nx-texto-suave: #6b7280;
        --nx-acento: #1f3a5f;
        --nx-sidebar: 248px;
        --bs-primary: #1f3a5f;
        --bs-primary-rgb: 31, 58, 95;
        --bs-link-color: #1f3a5f;
        --bs-link-color-rgb: 31, 58, 95;
        --bs-link-hover-color: #142740;
        --bs-body-font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    body { background: var(--nx-fondo); color: #1f2937; }
    .btn-primary { --bs-btn-bg: #1f3a5f; --bs-btn-border-color: #1f3a5f; --bs-btn-hover-bg: #172d4a; --bs-btn-hover-border-color: #172d4a; --bs-btn-active-bg: #142740; --bs-btn-disabled-bg: #1f3a5f; --bs-btn-disabled-border-color: #1f3a5f; }
    .btn-outline-primary { --bs-btn-color: #1f3a5f; --bs-btn-border-color: #1f3a5f; --bs-btn-hover-bg: #1f3a5f; --bs-btn-hover-border-color: #1f3a5f; --bs-btn-active-bg: #1f3a5f; }
    .page-link { color: #1f3a5f; }
    .active > .page-link { background-color: #1f3a5f; border-color: #1f3a5f; }
    .form-control:focus, .form-select:focus { border-color: #8aa0bd; box-shadow: 0 0 0 .2rem rgba(31, 58, 95, .15); }
    .nx-sidebar { width: var(--nx-sidebar); background: #fff; border-right: 1px solid var(--nx-borde); }
    .nx-marca { font-weight: 600; letter-spacing: -.01em; color: var(--nx-acento); text-decoration: none; }
    .nx-menu .nav-link { color: #374151; border-radius: .375rem; padding: .45rem .75rem; display: flex; gap: .6rem; align-items: center; font-size: .925rem; }
    .nx-menu .nav-link:hover { background: #f1f3f6; }
    .nx-menu .nav-link.active { background: #e8edf4; color: var(--nx-acento); font-weight: 600; }
    .nx-menu .nx-seccion { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: var(--nx-texto-suave); padding: .9rem .75rem .3rem; }
    .nx-tarjeta { background: #fff; border: 1px solid var(--nx-borde); border-radius: .5rem; }
    .nx-tarjeta > .card-header { background: #fff; border-bottom: 1px solid var(--nx-borde); }
    .nx-texto-suave { color: var(--nx-texto-suave); }
    .table > :not(caption) > * > * { padding: .6rem .75rem; }
    .table thead th { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: var(--nx-texto-suave); font-weight: 600; background: #fafbfc; }
    @media (min-width: 992px) {
        .nx-sidebar { position: fixed; inset: 0 auto 0 0; }
        .nx-contenido { margin-left: var(--nx-sidebar); }
    }
</style>
@stack('estilos')
