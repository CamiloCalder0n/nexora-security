@extends('layouts.panel')

@section('titulo', 'Mis autorizaciones')
@section('subtitulo', 'Visitas que autorizaste para tus unidades. Portería solo puede usar las vigentes.')

@section('acciones')
    @if ($residente)
        <a href="{{ route('panel.porteria.mis-autorizaciones.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva autorización
        </a>
    @endif
@endsection

@section('contenido')
    <x-aviso-filtro :campos="['search', 'estado']" />

    @if (! $residente)
        <div class="alert alert-warning" role="alert"><i class="bi bi-info-circle me-1"></i>{{ $sinResidente }}</div>
    @else
        <x-tabla :paginador="$autorizaciones" buscar="Buscar por visitante, documento o placa" vacio="Aún no tienes autorizaciones con estos criterios.">
            <x-slot:filtros>
                <div class="col-12 col-md-auto">
                    <label for="estado" class="visually-hidden">Estado</label>
                    <select id="estado" name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $valor => $texto)
                            <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
            </x-slot:filtros>
            <x-slot:cabecera>
                <th>Visitante</th>
                <th class="d-none d-sm-table-cell">Unidad</th>
                <th>Válida</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </x-slot:cabecera>

            @foreach ($autorizaciones as $autorizacion)
                <tr>
                    <td>
                        <div class="fw-medium">{{ $autorizacion->visitante_nombre }}</div>
                        <small class="nx-texto-suave">
                            Doc. {{ $autorizacion->visitante_documento }}
                            @if ($autorizacion->placa) · <i class="bi bi-car-front"></i> {{ $autorizacion->placa }}@endif
                            <span class="d-sm-none"> · {{ $autorizacion->unidad?->etiqueta }}</span>
                        </small>
                    </td>
                    <td class="d-none d-sm-table-cell">{{ $autorizacion->unidad?->etiqueta }}</td>
                    <td class="small text-nowrap">
                        {{ $autorizacion->valida_desde->format('d/m/Y H:i') }}<br>
                        <span class="nx-texto-suave">hasta {{ $autorizacion->valida_hasta->format('d/m/Y H:i') }}</span>
                    </td>
                    <td><x-estado :valor="$autorizacion->estado" /></td>
                    <td class="text-end">
                        @if ($autorizacion->estado === \App\Modules\Porteria\Domain\EstadoAutorizacion::Vigente)
                            <x-boton-eliminar
                                :action="route('panel.porteria.mis-autorizaciones.destroy', $autorizacion)"
                                texto="Anular"
                                icono="x-circle"
                                aria-label="Anular autorización de {{ $autorizacion->visitante_nombre }}"
                                confirmar="¿Anular la autorización de {{ $autorizacion->visitante_nombre }}? Portería ya no podrá usarla." />
                        @else
                            <span class="nx-texto-suave small">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>
    @endif
@endsection
