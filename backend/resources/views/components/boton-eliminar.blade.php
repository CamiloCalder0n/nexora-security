@props([
    'action',
    'texto' => 'Eliminar',
    'confirmar' => '¿Seguro que desea eliminar este registro?',
    'metodo' => 'DELETE',
    'icono' => 'trash',
])
<form method="POST" action="{{ $action }}" class="d-inline" onsubmit="return confirm(@js($confirmar));">
    @csrf
    @method($metodo)
    <button type="submit" {{ $attributes->merge(['class' => 'btn btn-sm btn-outline-danger']) }} title="{{ $texto }}">
        <i class="bi bi-{{ $icono }}"></i><span class="d-none d-xl-inline ms-1">{{ $texto }}</span>
    </button>
</form>
