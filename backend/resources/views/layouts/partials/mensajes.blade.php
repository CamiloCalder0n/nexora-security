@if (session('exito'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i>{{ session('exito') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif
@foreach (['regla', 'zona', 'autorizacion'] as $clave)
    @error($clave)
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-1"></i>{{ $message }}</div>
    @enderror
@endforeach
