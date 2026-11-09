@extends('layouts.panel')

@section('titulo', 'Autorizaciones de visita')
@section('subtitulo', 'Visitantes autorizados por los residentes y su ventana de ingreso.')

@section('acciones')
    <a href="{{ route('panel.porteria.autorizaciones.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva autorización
    </a>
@endsection

@section('contenido')
    @if ($errors->hasAny(['search', 'estado', 'unidad_id', 'page']))
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-funnel me-1"></i>El filtro no es válido y se limpió: {{ $errors->first() }}
        </div>
    @endif

    <x-tabla :paginador="$autorizaciones" buscar="Buscar por visitante, documento o placa" vacio="No hay autorizaciones para mostrar.">
        <x-slot:filtros>
            <div class="col-6 col-md-auto">
                <label for="estado" class="visually-hidden">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos los estados</option>
                    @foreach ($estados as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label for="unidad_id" class="visually-hidden">Unidad</label>
                <select id="unidad_id" name="unidad_id" class="form-select">
                    <option value="">Todas las unidades</option>
                    @foreach ($unidades as $id => $etiqueta)
                        <option value="{{ $id }}" @selected((string) request('unidad_id') === (string) $id)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filtros>

        <x-slot:cabecera>
            <th>Visitante</th>
            <th>Documento</th>
            <th>Unidad</th>
            <th>Residente</th>
            <th>Ventana válida</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
        </x-slot:cabecera>

        @foreach ($autorizaciones as $autorizacion)
            <tr>
                <td>
                    <div class="fw-medium">{{ $autorizacion->visitante_nombre }}</div>
                    @if ($autorizacion->placa)
                        <small class="nx-texto-suave"><i class="bi bi-car-front me-1"></i>{{ $autorizacion->placa }}</small>
                    @endif
                </td>
                <td>{{ $autorizacion->visitante_documento }}</td>
                <td class="text-nowrap">{{ $autorizacion->unidad?->etiqueta }}</td>
                <td>{{ $autorizacion->residente?->nombre }}</td>
                <td class="small text-nowrap">
                    {{ $autorizacion->valida_desde->format('d/m/Y H:i') }}<br>
                    <span class="nx-texto-suave">hasta</span> {{ $autorizacion->valida_hasta->format('d/m/Y H:i') }}
                </td>
                <td><x-estado :valor="$autorizacion->estado" /></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('panel.porteria.autorizaciones.show', $autorizacion) }}" class="btn btn-sm btn-outline-secondary" title="Ver" aria-label="Ver autorización de {{ $autorizacion->visitante_nombre }}">
                        <i class="bi bi-eye"></i>
                    </a>
                    @if ($autorizacion->estado === \App\Modules\Porteria\Domain\EstadoAutorizacion::Vigente)
                        <a href="{{ route('panel.porteria.autorizaciones.edit', $autorizacion) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar autorización de {{ $autorizacion->visitante_nombre }}">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <x-boton-eliminar
                            :action="route('panel.porteria.autorizaciones.destroy', $autorizacion)"
                            texto="Anular"
                            icono="x-circle"
                            confirmar="¿Seguro que desea anular esta autorización? El visitante ya no podrá ingresar con ella."
                            aria-label="Anular autorización de {{ $autorizacion->visitante_nombre }}" />
                    @endif
                </td>
            </tr>
        @endforeach
    </x-tabla>
@endsection
