@extends('layouts.panel')

@section('titulo', 'Nueva autorización')
@section('subtitulo', 'Avisa a portería que esperas una visita.')

@section('acciones')
    <a href="{{ route('panel.porteria.mis-autorizaciones.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
@endsection

@section('contenido')
    <div class="nx-tarjeta p-3 p-md-4" style="max-width: 720px;">
        <form method="POST" action="{{ route('panel.porteria.mis-autorizaciones.store') }}" novalidate>
            @csrf

            <x-campo-select name="unidad_id" label="Unidad" :opciones="$unidades" :value="count($unidades) === 1 ? array_key_first($unidades) : null" requerido />

            <div class="row g-0 g-md-3">
                <div class="col-12 col-md-7">
                    <x-campo name="visitante_nombre" label="Nombre del visitante" maxlength="120" autocomplete="off" requerido />
                </div>
                <div class="col-12 col-md-5">
                    <x-campo name="visitante_documento" label="Documento" maxlength="20" inputmode="numeric" autocomplete="off" requerido />
                </div>
            </div>

            <x-campo name="placa" label="Placa del vehículo" maxlength="10" autocomplete="off" class="text-uppercase" ayuda="Opcional, solo si llega en vehículo." />

            <div class="row g-0 g-md-3">
                <div class="col-12 col-md-6">
                    <x-campo name="valida_desde" label="Válida desde" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" requerido />
                </div>
                <div class="col-12 col-md-6">
                    <x-campo name="valida_hasta" label="Válida hasta" type="datetime-local" :value="now()->addHours(4)->format('Y-m-d\TH:i')" ayuda="Máximo {{ $maxDias }} días después del inicio." requerido />
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row gap-2 mt-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Autorizar visita</button>
                <a href="{{ route('panel.porteria.mis-autorizaciones.index') }}" class="btn btn-link text-decoration-none">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
