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
    {{-- Si se pasa class, reemplaza la clase por defecto (así no se duplica "btn"). --}}
    <button type="submit" {{ $attributes->merge([
        'class' => $attributes->has('class') ? '' : 'btn btn-sm btn-outline-danger',
        'aria-label' => $texto,
        'title' => $texto,
    ]) }}>
        <i class="bi bi-{{ $icono }}" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">{{ $texto }}</span>
    </button>
</form>
