@extends('layouts.panel')

@section('titulo', 'Autorización de '.$autorizacion->visitante_nombre)
@section('subtitulo', 'Detalle de la autorización de visita.')

@section('acciones')
    <a href="{{ route('panel.porteria.autorizaciones.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
    @if ($autorizacion->estado === \App\Modules\Porteria\Domain\EstadoAutorizacion::Vigente)
        <a href="{{ route('panel.porteria.autorizaciones.edit', $autorizacion) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <x-boton-eliminar
            :action="route('panel.porteria.autorizaciones.destroy', $autorizacion)"
            texto="Anular"
            icono="x-circle"
            confirmar="¿Seguro que desea anular esta autorización? El visitante ya no podrá ingresar con ella."
            aria-label="Anular autorización" />
    @endif
@endsection

@section('contenido')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="nx-tarjeta p-3 p-md-4 h-100">
                <h2 class="h6 mb-3">Visitante</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Nombre</dt>
                    <dd class="col-sm-8">{{ $autorizacion->visitante_nombre }}</dd>
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Documento</dt>
                    <dd class="col-sm-8">{{ $autorizacion->visitante_documento }}</dd>
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Placa</dt>
                    <dd class="col-sm-8 mb-0">{{ $autorizacion->placa ?? 'Sin vehículo' }}</dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="nx-tarjeta p-3 p-md-4 h-100">
                <h2 class="h6 mb-3">Autorización</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Estado</dt>
                    <dd class="col-sm-8"><x-estado :valor="$autorizacion->estado" /></dd>
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Unidad</dt>
                    <dd class="col-sm-8">{{ $autorizacion->unidad?->etiqueta }}</dd>
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Residente</dt>
                    <dd class="col-sm-8">{{ $autorizacion->residente?->nombre }}</dd>
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Válida desde</dt>
                    <dd class="col-sm-8">{{ $autorizacion->valida_desde->format('d/m/Y H:i') }}</dd>
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Válida hasta</dt>
                    <dd class="col-sm-8">{{ $autorizacion->valida_hasta->format('d/m/Y H:i') }}</dd>
                    @if ($autorizacion->usada_at)
                        <dt class="col-sm-4 nx-texto-suave fw-normal">Usada el</dt>
                        <dd class="col-sm-8">{{ $autorizacion->usada_at->format('d/m/Y H:i') }}</dd>
                    @endif
                    <dt class="col-sm-4 nx-texto-suave fw-normal">Creada el</dt>
                    <dd class="col-sm-8 mb-0">{{ $autorizacion->created_at?->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>
@endsection
