@extends('layouts.panel')

@section('titulo', 'Historial de eventos')
@section('subtitulo', 'Detecciones de las cámaras con su resultado. Filtre por fechas, zona, tipo y resultado para reconstruir lo que pasó.')

@php
    $colores = ['pendiente' => 'text-bg-light border', 'explicado' => 'text-bg-success', 'alerta' => 'text-bg-danger',
        'suprimido' => 'text-bg-secondary', 'fuera_franja' => 'text-bg-light border'];
    $esAdmin = auth()->user()->rol === \App\Modules\Identidad\Domain\Rol::Admin;
@endphp

@if (in_array(auth()->user()->rol, [\App\Modules\Identidad\Domain\Rol::Admin, \App\Modules\Identidad\Domain\Rol::Consejo], true))
    @section('acciones')
        <a href="{{ route('panel.analitica.historial.csv', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar CSV
        </a>
    @endsection
@endif

@section('contenido')
    <x-aviso-filtro :campos="['desde', 'hasta', 'zona_id', 'tipo_objeto', 'resultado', 'orden']" />

    @if ($eventos === null)
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>No se pudo leer MongoDB: el historial no está disponible por ahora.</span>
        </div>
    @else
        <x-tabla :paginador="$eventos" :con-buscador="false" vacio="No hay eventos con esos filtros.">
            <x-slot:filtros>
                <div class="col-6 col-md-auto">
                    <label for="filtro-desde" class="form-label small mb-1">Desde</label>
                    <input type="date" id="filtro-desde" name="desde" value="{{ request('desde') }}" class="form-control">
                </div>
                <div class="col-6 col-md-auto">
                    <label for="filtro-hasta" class="form-label small mb-1">Hasta</label>
                    <input type="date" id="filtro-hasta" name="hasta" value="{{ request('hasta') }}" class="form-control">
                </div>
                <div class="col-6 col-md-auto">
                    <label for="filtro-zona" class="form-label small mb-1">Zona</label>
                    <select id="filtro-zona" name="zona_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($zonas as $zona)
                            <option value="{{ $zona->id }}" @selected((string) request('zona_id') === (string) $zona->id)>{{ $zona->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label for="filtro-tipo" class="form-label small mb-1">Tipo</label>
                    <select id="filtro-tipo" name="tipo_objeto" class="form-select">
                        <option value="">Todos</option>
                        <option value="persona" @selected(request('tipo_objeto') === 'persona')>Persona</option>
                        <option value="vehiculo" @selected(request('tipo_objeto') === 'vehiculo')>Vehículo</option>
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label for="filtro-resultado" class="form-label small mb-1">Resultado</label>
                    <select id="filtro-resultado" name="resultado" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($resultados as $valor => $texto)
                            <option value="{{ $valor }}" @selected(request('resultado') === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <label for="filtro-orden" class="form-label small mb-1">Orden</label>
                    <select id="filtro-orden" name="orden" class="form-select">
                        <option value="recientes">Más recientes</option>
                        <option value="antiguos" @selected(request('orden') === 'antiguos')>Más antiguos</option>
                    </select>
                </div>
            </x-slot:filtros>

            <x-slot:cabecera>
                <th>Fecha y hora</th>
                <th>Zona</th>
                <th>Cámara</th>
                <th>Detección</th>
                <th>Resultado</th>
                @if ($esAdmin)<th class="text-end">Evidencia</th>@endif
            </x-slot:cabecera>

            @foreach ($eventos as $evento)
                <tr>
                    <td class="text-nowrap">{{ $evento['fecha_hora']->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $evento['zona']['nombre'] ?? '—' }}</td>
                    <td>{{ $evento['camara']['nombre'] ?? '—' }}</td>
                    <td class="text-nowrap">
                        {{ $evento['cantidad'] }} {{ $evento['tipo_objeto'] === 'vehiculo' ? 'vehículo(s)' : 'persona(s)' }}
                        <span class="small nx-texto-suave">· {{ number_format($evento['confianza'] * 100, 0) }} %</span>
                    </td>
                    <td><span class="badge {{ $colores[$evento['resultado']] ?? 'text-bg-light' }}">{{ $resultados[$evento['resultado']] ?? $evento['resultado'] }}</span></td>
                    @if ($esAdmin)
                        <td class="text-end">
                            @if ($evento['tiene_evidencia'] ?? false)
                                <a href="{{ route('panel.analitica.evidencia', $evento['uuid']) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Ver
                                </a>
                            @else
                                <span class="small nx-texto-suave">Sin evidencia</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-tabla>
    @endif
@endsection
