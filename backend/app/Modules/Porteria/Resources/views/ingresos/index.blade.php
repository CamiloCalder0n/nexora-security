@extends('layouts.panel')

@php
    $puedeRegistrar = in_array(auth()->user()->rol, [\App\Modules\Identidad\Domain\Rol::Vigilante, \App\Modules\Identidad\Domain\Rol::Admin], true);
@endphp

@section('titulo', 'Ingresos')
@section('subtitulo', 'Bitácora de portería: quién entró, a qué unidad y por qué zona.')

@section('acciones')
    @if ($puedeRegistrar)
        <a href="{{ route('panel.porteria.ingresos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Registrar ingreso
        </a>
    @endif
@endsection

@section('contenido')
    <x-aviso-filtro :campos="['search', 'tipo_ingreso', 'unidad_id', 'desde', 'hasta']" />

    <x-tabla :paginador="$registros" buscar="Buscar por visitante, documento o placa" vacio="No hay ingresos con esos filtros.">
        <x-slot:filtros>
            <div class="col-6 col-md-auto">
                <label for="tipo_ingreso" class="form-label small mb-1">Tipo</label>
                <select id="tipo_ingreso" name="tipo_ingreso" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($tipos as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('tipo_ingreso') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label for="unidad_id" class="form-label small mb-1">Unidad</label>
                <select id="unidad_id" name="unidad_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($unidades as $valor => $texto)
                        <option value="{{ $valor }}" @selected((string) request('unidad_id') === (string) $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
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
            <th>Ingreso</th>
            <th>Tipo</th>
            <th>Visitante</th>
            <th>Unidad</th>
            <th>Zona</th>
            <th>Registrado por</th>
            <th>Salida</th>
            @if ($puedeRegistrar)
                <th class="text-end">Acciones</th>
            @endif
        </x-slot:cabecera>

        @foreach ($registros as $registro)
            <tr>
                <td class="text-nowrap">{{ $registro->fecha_ingreso->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                <td><x-estado :valor="$registro->tipo_ingreso">{{ $tipos[$registro->tipo_ingreso->value] }}</x-estado></td>
                <td>
                    <div class="fw-medium">{{ $registro->nombre_visitante }}</div>
                    <small class="nx-texto-suave">
                        Doc. {{ $puedeRegistrar ? $registro->documento : $registro->documentoEnmascarado() }}
                        @if ($registro->placa) · <span class="text-uppercase">{{ $registro->placa }}</span>@endif
                        @if ($registro->cantidad_personas > 1) · {{ $registro->cantidad_personas }} personas @endif
                    </small>
                </td>
                <td class="text-nowrap">{{ $registro->unidad?->etiqueta }}</td>
                <td>{{ $registro->zona?->nombre }}</td>
                <td>{{ $registro->vigilante?->name }}</td>
                <td class="text-nowrap">
                    @if ($registro->fecha_salida)
                        {{ $registro->fecha_salida->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                    @else
                        <span class="badge rounded-pill text-bg-info fw-medium">Dentro</span>
                    @endif
                </td>
                @if ($puedeRegistrar)
                    <td class="text-end">
                        @unless ($registro->fecha_salida)
                            <form method="POST" action="{{ route('panel.porteria.ingresos.salida', $registro) }}" class="d-inline"
                                  onsubmit="return confirm(@js('¿Registrar la salida de '.$registro->nombre_visitante.' ahora?'));">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap" aria-label="Registrar salida de {{ $registro->nombre_visitante }}">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Registrar salida</span>
                                </button>
                            </form>
                        @endunless
                    </td>
                @endif
            </tr>
        @endforeach
    </x-tabla>
@endsection
