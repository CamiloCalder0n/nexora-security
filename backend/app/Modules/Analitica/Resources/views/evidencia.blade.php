@extends('layouts.panel')

@section('titulo', 'Evidencia del evento')
@section('subtitulo', 'La evidencia es un dato personal: cada consulta queda registrada con su motivo en la auditoría.')

@section('acciones')
    <a href="{{ route('panel.analitica.historial') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al historial
    </a>
@endsection

@section('contenido')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="nx-tarjeta p-3 h-100">
                <h2 class="h6 mb-3">Evento</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Fecha y hora</dt><dd class="col-7">{{ $evento['fecha_hora']->format('Y-m-d H:i:s') }}</dd>
                    <dt class="col-5">Zona</dt><dd class="col-7">{{ $evento['zona']['nombre'] ?? '—' }}</dd>
                    <dt class="col-5">Cámara</dt><dd class="col-7">{{ $evento['camara']['nombre'] ?? '—' }}</dd>
                    <dt class="col-5">Detección</dt><dd class="col-7">{{ $evento['cantidad'] }} {{ $evento['tipo_objeto'] }} ({{ number_format($evento['confianza'] * 100, 0) }} %)</dd>
                    <dt class="col-5">Resultado</dt><dd class="col-7">{{ $evento['resultado'] }}</dd>
                    <dt class="col-5">Id</dt><dd class="col-7 font-monospace text-break">{{ $evento['uuid'] }}</dd>
                </dl>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="nx-tarjeta p-3 h-100">
                @if ($consultada)
                    <h2 class="h6 mb-3">Evidencia</h2>
                    <div class="alert alert-success small" role="status">Consulta registrada en la auditoría.</div>
                    @if (! empty($evento['ruta_evidencia']))
                        <p class="small mb-1">Archivo en el equipo del conjunto:</p>
                        <p class="font-monospace text-break">{{ $evento['ruta_evidencia'] }}</p>
                        <p class="small nx-texto-suave mb-0">La imagen no se sube a la nube: se abre en el equipo de portería con esta ruta.</p>
                    @else
                        <p class="nx-texto-suave mb-0">Este evento no tiene evidencia guardada.</p>
                    @endif
                @else
                    <h2 class="h6 mb-3">Motivo de la consulta</h2>
                    <form method="POST" action="{{ route('panel.analitica.evidencia.ver', $evento['uuid']) }}">
                        @csrf
                        <x-campo name="motivo" label="¿Por qué necesita ver la evidencia?" type="textarea" rows="3" requerido
                                 ayuda="Mínimo 10 caracteres. Ejemplo: revisión del incidente reportado por el residente de la torre 2." />
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Registrar y ver evidencia
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
