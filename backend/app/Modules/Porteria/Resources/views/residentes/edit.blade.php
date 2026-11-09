@extends('layouts.panel')

@section('titulo', 'Editar residente')
@section('subtitulo', $residente->nombre)

@section('contenido')
    <form method="POST" action="{{ route('panel.porteria.residentes.update', $residente) }}" class="nx-tarjeta p-3 p-md-4" novalidate>
        @method('PUT')
        @include('porteria::residentes._form')
    </form>
@endsection
