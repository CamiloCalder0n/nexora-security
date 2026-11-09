@extends('layouts.panel')

@section('titulo', 'Autorizaciones vigentes')
@section('subtitulo', 'Visitas que los residentes dejaron autorizadas y que se pueden usar ahora mismo.')

@section('acciones')
    <a href="{{ route('panel.porteria.ingresos.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Registrar ingreso
    </a>
@endsection

@section('contenido')
    <x-tabla :paginador="$autorizaciones" buscar="Buscar por visitante, documento o placa" vacio="No hay autorizaciones vigentes en este momento.">
        <x-slot:cabecera>
            <th>Visitante</th>
            <th>Placa</th>
            <th>Unidad</th>
            <th>Autorizó</th>
            <th>Válida hasta</th>
            <th class="text-end">Acciones</th>
        </x-slot:cabecera>

        @foreach ($autorizaciones as $autorizacion)
            <tr>
                <td>
                    <div class="fw-medium">{{ $autorizacion->visitante_nombre }}</div>
                    <small class="nx-texto-suave">Doc. {{ $autorizacion->visitante_documento }}</small>
                </td>
                <td class="text-uppercase">{{ $autorizacion->placa ?? '—' }}</td>
                <td class="text-nowrap">{{ $autorizacion->unidad?->etiqueta }}</td>
                <td>{{ $autorizacion->residente?->nombre }}</td>
                <td class="text-nowrap">{{ $autorizacion->valida_hasta->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                <td class="text-end">
                    <a href="{{ route('panel.porteria.ingresos.create', ['unidad_id' => $autorizacion->unidad_id, 'autorizacion_id' => $autorizacion->id]) }}"
                       class="btn btn-sm btn-outline-primary text-nowrap"
                       aria-label="Registrar ingreso de {{ $autorizacion->visitante_nombre }}">
                        <i class="bi bi-box-arrow-in-right"></i><span class="d-none d-md-inline ms-1">Registrar ingreso</span>
                    </a>
                </td>
            </tr>
        @endforeach
    </x-tabla>
@endsection
