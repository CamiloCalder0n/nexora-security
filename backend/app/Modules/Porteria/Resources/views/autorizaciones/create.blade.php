@extends('layouts.panel')

@section('titulo', 'Nueva autorización')
@section('subtitulo', 'Autoriza el ingreso de un visitante a nombre de un residente.')

@section('acciones')
    <a href="{{ route('panel.porteria.autorizaciones.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
@endsection

@section('contenido')
    <div class="nx-tarjeta p-3 p-md-4">
        <form method="POST" action="{{ route('panel.porteria.autorizaciones.store') }}" novalidate>
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <x-campo-select name="residente_id" label="Residente" :opciones="$residentes" vacio="Seleccione el residente…" requerido />
                </div>
                <div class="col-md-6">
                    <x-campo-select name="unidad_id" label="Unidad" :opciones="$unidades" vacio="Seleccione la unidad…" ayuda="Solo las unidades del residente elegido." requerido />
                </div>
            </div>

            @include('porteria::autorizaciones._formulario', ['autorizacion' => null])

            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route('panel.porteria.autorizaciones.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear autorización</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const unidadesPorResidente = @json($unidadesPorResidente);
        const residente = document.getElementById('residente_id');
        const unidad = document.getElementById('unidad_id');

        const filtrar = () => {
            const permitidas = (unidadesPorResidente[residente.value] ?? []).map(String);
            for (const opcion of unidad.options) {
                if (opcion.value === '') continue;
                const visible = residente.value === '' || permitidas.includes(opcion.value);
                opcion.hidden = !visible;
                opcion.disabled = !visible;
            }
            if (unidad.selectedOptions[0]?.disabled) unidad.value = '';
            if (residente.value !== '' && unidad.value === '' && permitidas.length === 1) unidad.value = permitidas[0];
        };

        residente.addEventListener('change', filtrar);
        filtrar();
    })();
</script>
@endpush
