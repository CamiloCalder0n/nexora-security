@extends('layouts.panel')

@section('titulo', 'Mis visitas')
@section('subtitulo', 'Ingresos que portería registró hacia tus unidades.')

@section('contenido')
    @if (! $residente)
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-info-circle me-1"></i>Tu cuenta no está vinculada a un residente. Pide a la administración que la asocie a tu unidad.
        </div>
    @else
        <x-tabla :paginador="$visitas" buscar="Buscar por visitante, documento o placa" vacio="No hay ingresos registrados con estos criterios.">
            <x-slot:filtros>
                <div class="col-6 col-md-auto">
                    <label for="desde" class="form-label small mb-1">Desde</label>
                    <input type="date" id="desde" name="desde" value="{{ request('desde') }}" class="form-control">
                </div>
                <div class="col-6 col-md-auto">
                    <label for="hasta" class="form-label small mb-1">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="{{ request('hasta') }}" class="form-control">
                </div>
            </x-slot:filtros>
            <x-slot:cabecera>
                <th>Fecha</th>
                <th>Visitante</th>
                <th class="d-none d-md-table-cell">Tipo</th>
                <th class="d-none d-sm-table-cell">Unidad</th>
                <th>Salida</th>
            </x-slot:cabecera>

            @foreach ($visitas as $visita)
                <tr>
                    <td class="small text-nowrap">{{ $visita->fecha_ingreso->format('d/m/Y') }}<br><span class="nx-texto-suave">{{ $visita->fecha_ingreso->format('H:i') }}</span></td>
                    <td>
                        <div class="fw-medium">{{ $visita->nombre_visitante }}</div>
                        <small class="nx-texto-suave">
                            <span class="d-md-none">{{ ucfirst($visita->tipo_ingreso->value) }}</span>
                            <span class="d-sm-none">· {{ $visita->unidad?->etiqueta }}</span>
                            @if ($visita->placa)<span class="text-nowrap"><i class="bi bi-car-front ms-md-0 ms-1"></i> {{ $visita->placa }}</span>@endif
                        </small>
                    </td>
                    <td class="d-none d-md-table-cell">{{ ucfirst($visita->tipo_ingreso->value) }}</td>
                    <td class="d-none d-sm-table-cell">{{ $visita->unidad?->etiqueta }}</td>
                    <td class="small text-nowrap">
                        @if ($visita->fecha_salida)
                            {{ $visita->fecha_salida->format('H:i') }}
                            @unless ($visita->fecha_salida->isSameDay($visita->fecha_ingreso))
                                <span class="nx-texto-suave">({{ $visita->fecha_salida->format('d/m') }})</span>
                            @endunless
                        @else
                            <span class="badge rounded-pill text-bg-light border fw-medium">En el conjunto</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>
    @endif
@endsection
