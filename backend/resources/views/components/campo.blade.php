@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'ayuda' => null,
    'requerido' => false,
])
@php
    $id = $attributes->get('id', str_replace(['[', ']', '.'], '_', $name));
    $clave = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($requerido) <span class="text-danger">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($clave)]) }} @required($requerido)>{{ old($clave, $value) }}</textarea>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : old($clave, $value) }}" {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($clave)]) }} @required($requerido)>
    @endif
    @if ($ayuda)
        <div class="form-text">{{ $ayuda }}</div>
    @endif
    @error($clave)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
