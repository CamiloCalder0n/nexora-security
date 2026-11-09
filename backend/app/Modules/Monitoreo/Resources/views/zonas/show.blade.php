@extends('layouts.panel')

@section('titulo', $zona->nombre)
@section('subtitulo', $tipos[$zona->tipo->value].' · '.($zona->activa ? 'Zona activa' : 'Zona inactiva'))

@section('acciones')
    <a href="{{ route('panel.monitoreo.zonas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zonas</a>
    <a href="{{ route('panel.monitoreo.zonas.edit', $zona) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <x-boton-eliminar :action="route('panel.monitoreo.zonas.destroy', $zona)"
        confirmar="¿Eliminar la zona «{{ $zona->nombre }}»? Si tiene cámaras o historial, desactívela en su lugar." />
@endsection

@section('contenido')
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="nx-tarjeta p-3 p-md-4 h-100">
                <h2 class="h6 mb-3">Configuración</h2>
                <dl class="row mb-0 small">
                    <dt class="col-6 nx-texto-suave fw-normal">Tipo</dt>
                    <dd class="col-6">{{ $tipos[$zona->tipo->value] }}</dd>
                    <dt class="col-6 nx-texto-suave fw-normal">Prioridad base</dt>
                    <dd class="col-6">@include('monitoreo::zonas.partials.prioridad', ['nivel' => $zona->prioridad_base])</dd>
                    <dt class="col-6 nx-texto-suave fw-normal">Ventana de correlación</dt>
                    <dd class="col-6">{{ $zona->ventana_correlacion_min }} min</dd>
                    <dt class="col-6 nx-texto-suave fw-normal">Ventana de silencio</dt>
                    <dd class="col-6">{{ $zona->ventana_silencio_min }} min</dd>
                    <dt class="col-6 nx-texto-suave fw-normal">Estado</dt>
                    <dd class="col-6"><x-estado :valor="$zona->activa ? 'activa' : 'inactiva'" /></dd>
                    <dt class="col-6 nx-texto-suave fw-normal">Actualizada</dt>
                    <dd class="col-6 mb-0">{{ $zona->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="nx-tarjeta h-100">
                <div class="p-3 border-bottom"><h2 class="h6 mb-0">Franjas de vigilancia</h2></div>
                @if ($zona->franjas->isEmpty())
                    <p class="nx-texto-suave small p-3 mb-0">Sin franjas: la zona no se vigila en ningún horario.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th class="ps-3">Día</th><th>Inicio</th><th>Fin</th><th>Nota</th></tr></thead>
                            <tbody>
                                @foreach ($zona->franjas->sortBy(fn ($f) => [$f->dia_semana ?? -1, $f->hora_inicio]) as $franja)
                                    @php
                                        $inicio = substr($franja->hora_inicio, 0, 5);
                                        $fin = substr($franja->hora_fin, 0, 5);
                                    @endphp
                                    <tr>
                                        <td class="ps-3">{{ $franja->dia_semana === null ? 'Todos los días' : $dias[$franja->dia_semana] }}</td>
                                        <td>{{ $inicio }}</td>
                                        <td>{{ $fin }}</td>
                                        <td class="small nx-texto-suave">
                                            @if ($inicio === $fin) Día completo @elseif ($fin < $inicio) Cruza la medianoche @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12">
            <div class="nx-tarjeta">
                <div class="p-3 border-bottom"><h2 class="h6 mb-0">Cámaras ({{ $zona->camaras->count() }})</h2></div>
                @if ($zona->camaras->isEmpty())
                    <p class="nx-texto-suave small p-3 mb-0">Esta zona no tiene cámaras asignadas.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th class="ps-3">Nombre</th><th>Estado</th><th>Última conexión</th></tr></thead>
                            <tbody>
                                @foreach ($zona->camaras->sortBy('nombre') as $camara)
                                    <tr>
                                        <td class="ps-3 fw-medium">{{ $camara->nombre }}</td>
                                        <td><x-estado :valor="$camara->estado" /></td>
                                        <td class="small">{{ $camara->ultima_conexion?->diffForHumans() ?? 'Nunca' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
