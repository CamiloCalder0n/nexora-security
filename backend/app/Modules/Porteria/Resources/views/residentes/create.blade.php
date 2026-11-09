@extends('layouts.panel')

@section('titulo', 'Nuevo residente')
@section('subtitulo', 'Registre a la persona y asígnele al menos una unidad.')

@section('contenido')
    <form method="POST" action="{{ route('panel.porteria.residentes.store') }}" class="nx-tarjeta p-3 p-md-4" novalidate>
        @include('porteria::residentes._form')
    </form>
@endsection
