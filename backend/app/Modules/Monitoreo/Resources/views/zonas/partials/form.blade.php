@php
    $franjas = old('franjas', $zona->relationLoaded('franjas')
        ? $zona->franjas->map(fn ($f) => [
            'dia_semana' => $f->dia_semana,
            'hora_inicio' => substr($f->hora_inicio, 0, 5),
            'hora_fin' => substr($f->hora_fin, 0, 5),
        ])->all()
        : []);
    $franjas = array_values(is_array($franjas) ? $franjas : []);
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="nx-tarjeta p-3 p-md-4 h-100">
            <h2 class="h6 mb-3">Datos de la zona</h2>
            <x-campo name="nombre" label="Nombre" :value="$zona->nombre" requerido maxlength="80" autofocus />
            <x-campo-select name="tipo" label="Tipo" :opciones="$tipos" :value="$zona->tipo" requerido />
            <x-campo-select name="prioridad_base" label="Prioridad base" :opciones="[1 => 'Baja', 2 => 'Media', 3 => 'Alta']"
                :value="$zona->prioridad_base" requerido ayuda="Prioridad con la que nacen las alertas de esta zona." />
            <div class="row g-2">
                <div class="col-sm-6">
                    <x-campo name="ventana_correlacion_min" label="Ventana de correlación (min)" type="number" min="1" max="120"
                        :value="$zona->ventana_correlacion_min" requerido ayuda="Minutos en que un registro de portería explica la presencia." />
                </div>
                <div class="col-sm-6">
                    <x-campo name="ventana_silencio_min" label="Ventana de silencio (min)" type="number" min="1" max="120"
                        :value="$zona->ventana_silencio_min" requerido ayuda="Minutos sin repetir alertas en la zona." />
                </div>
            </div>
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input @error('activa') is-invalid @enderror" role="switch" id="activa" name="activa" value="1"
                    @checked(session()->hasOldInput() ? (bool) old('activa') : $zona->activa)>
                <label class="form-check-label" for="activa">Zona activa</label>
                <div class="form-text">Una zona inactiva no se vigila ni genera alertas.</div>
                @error('activa')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="nx-tarjeta p-3 p-md-4 h-100">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <h2 class="h6 mb-0">Franjas de vigilancia</h2>
                <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-franja"><i class="bi bi-plus-lg me-1"></i>Agregar franja</button>
            </div>
            <p class="small nx-texto-suave mb-3">
                La zona solo se vigila dentro de estas franjas. Si la hora de fin es menor que la de inicio, la franja cruza la medianoche;
                si son iguales, cubre el día completo. Máximo 14 franjas.
            </p>
            @error('franjas')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

            <div id="lista-franjas" class="d-flex flex-column gap-2">
                @foreach ($franjas as $i => $franja)
                    @include('monitoreo::zonas.partials.franja', ['i' => $i, 'franja' => $franja])
                @endforeach
            </div>
            <p id="sin-franjas" class="small nx-texto-suave fst-italic mb-0 {{ count($franjas) ? 'd-none' : '' }}">
                Sin franjas: la zona no se vigila en ningún horario.
            </p>
        </div>
    </div>
</div>

<template id="plantilla-franja">
    @include('monitoreo::zonas.partials.franja', ['i' => '__i__', 'franja' => []])
</template>

@push('scripts')
<script>
(() => {
    const lista = document.getElementById('lista-franjas');
    const vacio = document.getElementById('sin-franjas');
    const plantilla = document.getElementById('plantilla-franja');
    const boton = document.getElementById('agregar-franja');
    const MAXIMO = 14;
    let siguiente = lista.children.length;

    const refrescar = () => {
        vacio.classList.toggle('d-none', lista.children.length > 0);
        boton.disabled = lista.children.length >= MAXIMO;
    };

    boton.addEventListener('click', () => {
        const html = plantilla.innerHTML.replaceAll('__i__', siguiente++);
        lista.insertAdjacentHTML('beforeend', html);
        lista.lastElementChild.querySelector('select')?.focus();
        refrescar();
    });

    lista.addEventListener('click', (e) => {
        const quitar = e.target.closest('[data-quitar-franja]');
        if (quitar) {
            quitar.closest('[data-franja]').remove();
            refrescar();
        }
    });

    refrescar();
})();
</script>
@endpush
