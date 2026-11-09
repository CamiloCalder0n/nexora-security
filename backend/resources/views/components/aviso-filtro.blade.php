@props(['campos'])
{{-- Aviso común de los listados: un filtro inválido redirige al listado limpio con los errores en $errors. --}}
@if ($errors->hasAny($campos))
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-funnel me-1" aria-hidden="true"></i>Algún filtro no era válido; se muestran todos los registros.
        <ul class="mb-0 mt-1">
            @foreach ($campos as $campo)
                @foreach ($errors->get($campo) as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
