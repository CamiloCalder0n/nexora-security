@extends('layouts.panel')

@section('titulo', 'Editar autorización')
@section('subtitulo', 'Solo se pueden modificar las autorizaciones vigentes.')

@section('acciones')
    <a href="{{ route('panel.porteria.autorizaciones.show', $autorizacion) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
@endsection

@section('contenido')
    <div class="nx-tarjeta p-3 p-md-4">
        <dl class="row small mb-3">
            <dt class="col-sm-3 nx-texto-suave fw-normal">Residente</dt>
            <dd class="col-sm-9">{{ $autorizacion->residente?->nombre }}</dd>
            <dt class="col-sm-3 nx-texto-suave fw-normal">Unidad</dt>
            <dd class="col-sm-9">{{ $autorizacion->unidad?->etiqueta }}</dd>
            <dt class="col-sm-3 nx-texto-suave fw-normal">Estado</dt>
            <dd class="col-sm-9 mb-0"><x-estado :valor="$autorizacion->estado" /></dd>
        </dl>

        <form method="POST" action="{{ route('panel.porteria.autorizaciones.update', $autorizacion) }}" novalidate>
            @csrf
            @method('PUT')

            @include('porteria::autorizaciones._formulario')

            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ route('panel.porteria.autorizaciones.show', $autorizacion) }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
