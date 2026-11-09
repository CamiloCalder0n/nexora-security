@php
    // Tras un error se respeta lo que el usuario dejó marcado (aunque haya desmarcado todo).
    $conErrores = $errors->any();
    $asignadas = array_map('strval', (array) ($conErrores ? old('unidades', []) : $residente->unidades->pluck('id')->all()));
    $titulares = array_map('strval', (array) ($conErrores ? old('titular_de', []) : $residente->unidades->filter(fn ($u) => $u->pivot->es_titular)->pluck('id')->all()));
@endphp
@csrf
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <x-campo name="nombre" label="Nombre completo" :value="$residente->nombre" maxlength="120" requerido />
        <div class="row">
            <div class="col-sm-6">
                <x-campo name="documento" label="Documento" :value="$residente->documento" maxlength="20" requerido />
            </div>
            <div class="col-sm-6">
                <x-campo name="telefono" label="Teléfono" type="tel" :value="$residente->telefono" maxlength="20" />
            </div>
        </div>
        <x-campo-select name="tipo_residente" label="Tipo de residente" :opciones="$tipos" :value="$residente->tipo_residente" requerido />
    </div>

    <div class="col-12 col-lg-6">
        <fieldset>
            <legend class="form-label fs-6 mb-1">Unidades <span class="text-danger">*</span></legend>
            <div class="form-text mt-0 mb-2">Marque dónde vive y, si aplica, de cuáles es titular.</div>
            <div @class(['border rounded overflow-auto', 'border-danger' => $errors->has('unidades') || $errors->has('unidades.*')]) style="max-height: 18rem;">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr><th>Unidad</th><th class="text-center">Asignada</th><th class="text-center">Titular</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($unidades as $unidad)
                            <tr>
                                <td><label for="unidad_{{ $unidad->id }}" class="mb-0">{{ $unidad->etiqueta }}</label></td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input" id="unidad_{{ $unidad->id }}" name="unidades[]" value="{{ $unidad->id }}" @checked(in_array((string) $unidad->id, $asignadas, true))>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input" name="titular_de[]" value="{{ $unidad->id }}" aria-label="Titular de {{ $unidad->etiqueta }}" @checked(in_array((string) $unidad->id, $titulares, true))>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center nx-texto-suave py-3">No hay unidades registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @foreach (['unidades', 'unidades.*', 'titular_de', 'titular_de.*'] as $clave)
                @error($clave)
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            @endforeach
        </fieldset>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
    <a href="{{ $residente->exists ? route('panel.porteria.residentes.show', $residente) : route('panel.porteria.residentes.index') }}" class="btn btn-light border">Cancelar</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
</div>
