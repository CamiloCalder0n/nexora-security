@extends('layouts.panel')

@section('titulo', 'Informe mensual')
@section('subtitulo', 'Indicadores del mes para el consejo: alertas, falsas alarmas, zonas, horarios y tiempos de atención.')

@php
    $a = $informe['alertas'];
    $clases = ['incidente' => 'Incidente', 'sin_novedad' => 'Sin novedad', 'falsa_alarma' => 'Falsa alarma'];
    $nombresResultado = \App\Modules\Analitica\Http\Requests\FiltroHistorialRequest::RESULTADOS;
@endphp

@section('acciones')
    <a href="{{ route('panel.analitica.informe.csv', ['mes' => $informe['mes']]) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-filetype-csv" aria-hidden="true"></i> Descargar CSV
    </a>
@endsection

@section('contenido')
    <form method="GET" action="{{ route('panel.analitica.informe') }}" class="nx-tarjeta p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-8 col-md-auto">
                <label for="mes" class="form-label small mb-1">Mes</label>
                <input type="month" id="mes" name="mes" value="{{ $informe['mes'] }}" max="{{ now()->format('Y-m') }}" class="form-control">
            </div>
            <div class="col-4 col-md-auto"><button class="btn btn-outline-primary" type="submit">Ver</button></div>
            <div class="col-12 col-md text-md-end small nx-texto-suave">
                Del {{ $informe['desde']->format('d/m/Y') }} al {{ $informe['hasta']->format('d/m/Y') }}
            </div>
        </div>
    </form>

    @unless ($informe['mongo_disponible'])
        <div class="alert alert-warning small" role="alert">No se pudo leer MongoDB: las cifras de eventos y alertas no están disponibles.</div>
    @endunless

    @php
        $kpis = [
            ['Ingresos en portería', $informe['ingresos'], 'box-arrow-in-right'],
            ['Eventos detectados', $informe['eventos_total'], 'camera-video'],
            ['Alertas', $a['total'] ?? null, 'bell'],
            ['Falsas alarmas', $informe['porcentaje_falsas'] !== null ? $informe['porcentaje_falsas'].' %' : null, 'funnel'],
            ['Atención promedio', isset($a['atencion']['promedio_min']) ? $a['atencion']['promedio_min'].' min' : null, 'stopwatch'],
            ['Escaladas', $a['escaladas'] ?? null, 'arrow-up-circle'],
        ];
    @endphp
    <div class="row g-3 mb-3">
        @foreach ($kpis as [$titulo, $valor, $icono])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="nx-tarjeta p-3 h-100">
                    <div class="d-flex justify-content-between nx-texto-suave small mb-2"><span>{{ $titulo }}</span><i class="bi bi-{{ $icono }}" aria-hidden="true"></i></div>
                    <div class="fs-3 fw-semibold lh-1">{{ $valor ?? '—' }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($a)
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="nx-tarjeta h-100">
                    <h2 class="h6 p-3 mb-0 border-bottom">Alertas por zona</h2>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Zona</th><th class="text-end">Alertas</th><th class="text-end">Incidentes</th><th class="text-end">Falsas alarmas</th></tr></thead>
                            <tbody>
                                @forelse ($a['por_zona'] as $z)
                                    <tr><td>{{ $z['zona'] }}</td><td class="text-end">{{ $z['total'] }}</td><td class="text-end">{{ $z['incidentes'] }}</td><td class="text-end">{{ $z['falsas'] }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="text-center nx-texto-suave py-4">No hubo alertas este mes.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-flex flex-column gap-3">
                <div class="nx-tarjeta p-3">
                    <h2 class="h6 mb-3">Alertas por franja horaria</h2>
                    @php($maxFranja = max(1, ...array_values($informe['por_franja'])))
                    @foreach ($informe['por_franja'] as $franja => $total)
                        <div class="d-flex justify-content-between small"><span>{{ $franja }}</span><span class="fw-semibold">{{ $total }}</span></div>
                        <div class="progress mb-2" style="height: 6px" role="img" aria-label="{{ $franja }}: {{ $total }} alertas">
                            <div class="progress-bar" style="width: {{ $total * 100 / $maxFranja }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="nx-tarjeta p-3">
                    <h2 class="h6 mb-3">Cierre de alertas</h2>
                    <dl class="row small mb-0">
                        @foreach ($clases as $valor => $texto)
                            <dt class="col-7 fw-normal">{{ $texto }}</dt><dd class="col-5 text-end fw-semibold">{{ $a['por_clasificacion'][$valor] ?? 0 }}</dd>
                        @endforeach
                        <dt class="col-7 fw-normal">Sin cerrar</dt><dd class="col-5 text-end fw-semibold">{{ ($a['por_estado']['abierta'] ?? 0) + ($a['por_estado']['atendida'] ?? 0) }}</dd>
                        <dt class="col-7 fw-normal">Atención más lenta</dt><dd class="col-5 text-end fw-semibold">{{ $a['atencion']['maximo_min'] !== null ? $a['atencion']['maximo_min'].' min' : '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    @endif

    @if ($informe['eventos'])
        <div class="nx-tarjeta p-3 mt-3">
            <h2 class="h6 mb-3">Eventos por resultado</h2>
            <div class="d-flex flex-wrap gap-3 small">
                @foreach ($nombresResultado as $valor => $texto)
                    <span>{{ $texto }}: <strong>{{ $informe['eventos'][$valor] ?? 0 }}</strong></span>
                @endforeach
            </div>
        </div>
    @endif
@endsection
