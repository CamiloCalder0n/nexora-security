@extends('layouts.panel')

@section('titulo', $residente->nombre)
@section('subtitulo', 'Detalle del residente')

@section('acciones')
    <a href="{{ route('panel.porteria.residentes.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <a href="{{ route('panel.porteria.residentes.edit', $residente) }}" class="btn btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <x-boton-eliminar :action="route('panel.porteria.residentes.destroy', $residente)" :confirmar="'¿Eliminar al residente '.$residente->nombre.'?'" class="btn btn-outline-danger" />
@endsection

@section('contenido')
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="nx-tarjeta p-3 p-md-4 h-100">
                <h2 class="h6 mb-3">Datos</h2>
                <dl class="row mb-0">
                    <dt class="col-5 nx-texto-suave fw-normal">Documento</dt>
                    <dd class="col-7">{{ $residente->documento }}</dd>
                    <dt class="col-5 nx-texto-suave fw-normal">Teléfono</dt>
                    <dd class="col-7">{{ $residente->telefono ?? '—' }}</dd>
                    <dt class="col-5 nx-texto-suave fw-normal">Tipo</dt>
                    <dd class="col-7"><x-estado :valor="$residente->tipo_residente" /></dd>
                    <dt class="col-5 nx-texto-suave fw-normal">Cuenta</dt>
                    <dd class="col-7 mb-0 text-break">{{ $residente->user?->email ?? 'Sin cuenta en el sistema' }}</dd>
                </dl>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="nx-tarjeta h-100">
                <h2 class="h6 p-3 p-md-4 pb-0 mb-3">Unidades</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Torre</th><th>Número</th><th>Rol en la unidad</th></tr></thead>
                        <tbody>
                            @forelse ($residente->unidades as $unidad)
                                <tr>
                                    <td>{{ $unidad->torre }}</td>
                                    <td>{{ $unidad->numero }}</td>
                                    <td>
                                        @if ($unidad->pivot->es_titular)
                                            <span class="badge text-bg-primary fw-medium">Titular</span>
                                        @else
                                            <span class="badge text-bg-light border fw-medium">Habitante</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center nx-texto-suave py-4">Sin unidades asignadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
