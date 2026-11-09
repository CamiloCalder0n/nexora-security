@extends('layouts.panel')

@section('titulo', 'Residentes')
@section('subtitulo', 'Personas que viven en el conjunto y sus unidades.')

@section('acciones')
    <a href="{{ route('panel.porteria.residentes.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Nuevo residente
    </a>
@endsection

@section('contenido')
    <x-aviso-filtro :campos="['search', 'tipo_residente', 'unidad_id']" />

    <x-tabla :paginador="$residentes" buscar="Buscar por nombre, documento o teléfono" vacio="No hay residentes que coincidan con la búsqueda.">
        <x-slot:filtros>
            <div class="col-6 col-md-auto">
                <label for="tipo_residente" class="visually-hidden">Tipo de residente</label>
                <select id="tipo_residente" name="tipo_residente" class="form-select">
                    <option value="">Todos los tipos</option>
                    @foreach ($tipos as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('tipo_residente') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label for="unidad_id" class="visually-hidden">Unidad</label>
                <select id="unidad_id" name="unidad_id" class="form-select">
                    <option value="">Todas las unidades</option>
                    @foreach ($unidades as $unidad)
                        <option value="{{ $unidad->id }}" @selected((string) request('unidad_id') === (string) $unidad->id)>{{ $unidad->etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filtros>

        <x-slot:cabecera>
            <th>Nombre</th>
            <th>Documento</th>
            <th class="d-none d-md-table-cell">Teléfono</th>
            <th>Tipo</th>
            <th>Unidades</th>
            <th class="text-end">Acciones</th>
        </x-slot:cabecera>

        @foreach ($residentes as $residente)
            <tr>
                <td>
                    <a href="{{ route('panel.porteria.residentes.show', $residente) }}" class="fw-medium text-decoration-none">{{ $residente->nombre }}</a>
                    @if ($residente->user)
                        <i class="bi bi-person-check text-success ms-1" title="Tiene cuenta en el sistema" aria-label="Tiene cuenta en el sistema"></i>
                    @endif
                </td>
                <td>{{ $residente->documento }}</td>
                <td class="d-none d-md-table-cell">{{ $residente->telefono ?? '—' }}</td>
                <td><x-estado :valor="$residente->tipo_residente" /></td>
                <td>
                    @foreach ($residente->unidades as $unidad)
                        <span class="badge text-bg-light border fw-normal">{{ $unidad->etiqueta }}@if ($unidad->pivot->es_titular) <i class="bi bi-star-fill text-warning" title="Titular" aria-label="Titular"></i>@endif</span>
                    @endforeach
                </td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('panel.porteria.residentes.show', $residente) }}" class="btn btn-sm btn-outline-secondary" title="Ver" aria-label="Ver {{ $residente->nombre }}"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('panel.porteria.residentes.edit', $residente) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar {{ $residente->nombre }}"><i class="bi bi-pencil"></i></a>
                    <x-boton-eliminar :action="route('panel.porteria.residentes.destroy', $residente)" :confirmar="'¿Eliminar al residente '.$residente->nombre.'?'" aria-label="Eliminar {{ $residente->nombre }}" />
                </td>
            </tr>
        @endforeach
    </x-tabla>
@endsection
