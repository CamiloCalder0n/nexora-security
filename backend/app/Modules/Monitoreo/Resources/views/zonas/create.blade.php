@extends('layouts.panel')

@section('titulo', 'Nueva zona')
@section('subtitulo', 'Defina el área, su prioridad y las franjas en que se vigila.')

@section('acciones')
    <a href="{{ route('panel.monitoreo.zonas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
@endsection

@section('contenido')
    <form method="POST" action="{{ route('panel.monitoreo.zonas.store') }}" novalidate>
        @csrf
        @include('monitoreo::zonas.partials.form')
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Crear zona</button>
            <a href="{{ route('panel.monitoreo.zonas.index') }}" class="btn btn-link text-decoration-none">Cancelar</a>
        </div>
    </form>
@endsection
