@php
    [$etiqueta, $clase] = match ((int) $nivel) {
        3 => ['Alta', 'text-bg-danger'],
        2 => ['Media', 'text-bg-warning'],
        default => ['Baja', 'text-bg-light border'],
    };
@endphp
<span class="badge rounded-pill {{ $clase }} fw-medium">{{ $etiqueta }}</span>
