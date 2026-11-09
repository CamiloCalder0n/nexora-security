@extends('layouts.panel')

@section('titulo', 'Editar zona')
@section('subtitulo', $zona->nombre)

@section('acciones')
    <a href="{{ route('panel.monitoreo.zonas.show', $zona) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('panel.monitoreo.zonas.update', $zona) }}" novalidate>
        @csrf
        @method('PUT')
        @include('monitoreo::zonas.partials.form')
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
            <a href="{{ route('panel.monitoreo.zonas.show', $zona) }}" class="btn btn-link text-decoration-none">Cancelar</a>
        </div>
    </form>
@endsection
