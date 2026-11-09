@extends('layouts.panel')

@section('titulo', 'Zonas')
@section('subtitulo', 'Áreas vigiladas del conjunto, su prioridad y sus horarios de vigilancia.')

@section('acciones')
    <a href="{{ route('panel.monitoreo.zonas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva zona
    </a>
@endsection

@section('contenido')
    @if ($errors->hasAny(['search', 'tipo', 'activa', 'page']))
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-funnel me-1"></i>Se limpiaron los filtros porque no eran válidos:
            <ul class="mb-0 mt-1">
                @foreach (['search', 'tipo', 'activa', 'page'] as $campo)
                    @error($campo)<li>{{ $message }}</li>@enderror
                @endforeach
            </ul>
        </div>
    @endif

    <x-tabla :paginador="$zonas" buscar="Buscar por nombre" vacio="No hay zonas que coincidan con la búsqueda.">
        <x-slot:filtros>
            <div class="col-6 col-md-auto">
                <label for="filtro-tipo" class="visually-hidden">Tipo</label>
                <select id="filtro-tipo" name="tipo" class="form-select">
                    <option value="">Todos los tipos</option>
                    @foreach ($tipos as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label for="filtro-activa" class="visually-hidden">Estado</label>
                <select id="filtro-activa" name="activa" class="form-select">
                    <option value="">Todas</option>
                    <option value="1" @selected(request('activa') === '1')>Activas</option>
                    <option value="0" @selected(request('activa') === '0')>Inactivas</option>
                </select>
            </div>
        </x-slot:filtros>

        <x-slot:cabecera>
            <th>Nombre</th>
            <th>Tipo</th>
            <th class="text-center">Prioridad</th>
            <th class="text-center">Correlación</th>
            <th class="text-center">Cámaras</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
        </x-slot:cabecera>

        @foreach ($zonas as $zona)
            <tr>
                <td>
                    <a href="{{ route('panel.monitoreo.zonas.show', $zona) }}" class="fw-medium text-decoration-none">{{ $zona->nombre }}</a>
                    <div class="small nx-texto-suave">{{ trans_choice('{0} Sin franjas|{1} 1 franja|[2,*] :count franjas', $zona->franjas->count()) }}</div>
                </td>
                <td>{{ $tipos[$zona->tipo->value] }}</td>
                <td class="text-center">@include('monitoreo::zonas.partials.prioridad', ['nivel' => $zona->prioridad_base])</td>
                <td class="text-center text-nowrap">{{ $zona->ventana_correlacion_min }} min</td>
                <td class="text-center">{{ $zona->camaras_count }}</td>
                <td><x-estado :valor="$zona->activa ? 'activa' : 'inactiva'" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('panel.monitoreo.zonas.show', $zona) }}" class="btn btn-sm btn-outline-secondary" title="Ver" aria-label="Ver {{ $zona->nombre }}"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('panel.monitoreo.zonas.edit', $zona) }}" class="btn btn-sm btn-outline-secondary" title="Editar" aria-label="Editar {{ $zona->nombre }}"><i class="bi bi-pencil"></i></a>
                    <x-boton-eliminar :action="route('panel.monitoreo.zonas.destroy', $zona)" aria-label="Eliminar {{ $zona->nombre }}"
                        confirmar="¿Eliminar la zona «{{ $zona->nombre }}»? Si tiene cámaras o historial, desactívela en su lugar." />
                </td>
            </tr>
        @endforeach
    </x-tabla>
@endsection
