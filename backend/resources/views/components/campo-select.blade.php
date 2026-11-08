@props([
    'name',
    'label',
    'opciones' => [],
    'value' => null,
    'vacio' => 'Seleccione…',
    'requerido' => false,
    'ayuda' => null,
])
{{-- $opciones = [valor => texto]. Con selección múltiple, name termina en [] y se agrega el atributo multiple. --}}
@php
    $base = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $base));
    $clave = str_replace(['[', ']'], ['.', ''], $base);
    $actual = old($clave, $value);
    $actual = $actual instanceof \BackedEnum ? $actual->value : $actual;
    $seleccionados = array_map('strval', (array) $actual);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($requerido) <span class="text-danger">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $errors->has($clave) || $errors->has($clave.'.*')]) }} @required($requerido)>
        @unless ($attributes->has('multiple'))
            <option value="">{{ $vacio }}</option>
        @endunless
        @foreach ($opciones as $valor => $texto)
            <option value="{{ $valor }}" @selected(in_array((string) $valor, $seleccionados, true))>{{ $texto }}</option>
        @endforeach
    </select>
    @if ($ayuda)
        <div class="form-text">{{ $ayuda }}</div>
    @endif
    @error($clave)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @error($clave.'.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
