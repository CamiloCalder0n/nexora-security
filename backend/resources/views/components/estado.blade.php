@props(['valor'])
{{-- Insignia sobria para estados (vigente, usada, activa, abierta…). Acepta enum, string o bool. --}}
@php
    $texto = match (true) {
        $valor instanceof \BackedEnum => $valor->value,
        is_bool($valor) => $valor ? 'activo' : 'inactivo',
        default => (string) $valor,
    };
    $clase = match ($texto) {
        'vigente', 'activa', 'activo' => 'text-bg-success',
        'anulada', 'inactiva', 'inactivo', 'sin_senal' => 'text-bg-secondary',
        'expirada' => 'text-bg-warning',
        'abierta' => 'text-bg-danger',
        default => 'text-bg-light border',
    };
@endphp
<span class="badge rounded-pill {{ $clase }} fw-medium">{{ $slot->isEmpty() ? ucfirst(str_replace('_', ' ', $texto)) : $slot }}</span>
