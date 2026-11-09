@extends('layouts.panel')

@section('titulo', 'Dashboard')
@section('subtitulo', 'Resumen del conjunto en los últimos '.$resumen['dias'].' días')

@php
    $mongo = $resumen['mongo_disponible'];
    $kpis = [
        ['Ingresos hoy', $resumen['ingresos_hoy'], 'box-arrow-in-right', $resumen['ingresos_periodo'].' en '.$resumen['dias'].' días'],
        ['Alertas abiertas', $resumen['alertas_abiertas'], 'bell', 'Sin atender ahora'],
        ['Alertas '.$resumen['dias'].' días', $resumen['alertas_periodo'], 'exclamation-triangle', $mongo ? $resumen['alertas_cerradas'].' cerradas' : null],
        ['Falsas alarmas', $resumen['porcentaje_falsas'] !== null ? $resumen['porcentaje_falsas'].' %' : null, 'shield-check',
            $mongo ? ($resumen['alertas_cerradas'] ? 'De las cerradas' : 'Aún no hay alertas cerradas') : null],
        ['Zonas activas', $resumen['zonas']['activas'].' / '.$resumen['zonas']['total'], 'geo-alt', 'Activas / total'],
        ['Residentes', $resumen['residentes']['residentes'], 'people', $resumen['residentes']['unidades'].' unidades'],
    ];
    $hayIngresos = $resumen['ingresos_periodo'] > 0;
    $porZona = $resumen['alertas_por_zona'] ?? [];
@endphp

@section('contenido')
    @unless ($mongo)
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>No se pudo leer MongoDB: las métricas de alertas no están disponibles por ahora.</span>
        </div>
    @endunless

    <div class="row g-3 mb-4">
        @foreach ($kpis as [$titulo, $valor, $icono, $detalle])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="nx-tarjeta p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start nx-texto-suave small mb-2">
                        <span>{{ $titulo }}</span>
                        <i class="bi bi-{{ $icono }}" aria-hidden="true"></i>
                    </div>
                    <div class="fs-3 fw-semibold lh-1">{{ $valor ?? '—' }}</div>
                    @if ($detalle)
                        <div class="small nx-texto-suave mt-2">{{ $detalle }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="nx-tarjeta p-3 h-100">
                <h2 class="h6 mb-3">Ingresos por día (últimos {{ $resumen['dias'] }} días)</h2>
                @if ($hayIngresos)
                    <div style="height: 280px">
                        <canvas id="grafico-ingresos" role="img" aria-label="Ingresos por día en los últimos {{ $resumen['dias'] }} días: {{ $resumen['ingresos_periodo'] }} en total"></canvas>
                    </div>
                @else
                    <p class="nx-texto-suave text-center py-5 mb-0">No hay ingresos registrados en este periodo.</p>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="nx-tarjeta p-3 h-100">
                <h2 class="h6 mb-3">Alertas por zona (últimos {{ $resumen['dias'] }} días)</h2>
                @if (! $mongo)
                    <p class="nx-texto-suave text-center py-5 mb-0">No disponible: no se pudo leer MongoDB.</p>
                @elseif ($porZona)
                    <div style="height: {{ max(160, count($porZona) * 36 + 40) }}px">
                        <canvas id="grafico-zonas" role="img" aria-label="Alertas por zona en los últimos {{ $resumen['dias'] }} días"></canvas>
                    </div>
                @else
                    <p class="nx-texto-suave text-center py-5 mb-0">No hay alertas en este periodo.</p>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            if (typeof Chart === 'undefined') return;

            const acento = getComputedStyle(document.documentElement).getPropertyValue('--nx-acento').trim() || '#1f3a5f';
            const rejilla = '#eef0f3';
            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
            Chart.defaults.color = '#6b7280';

            const ingresos = @json($resumen['ingresos_por_dia']);
            const lienzoIngresos = document.getElementById('grafico-ingresos');
            if (lienzoIngresos) {
                const fechas = Object.keys(ingresos);
                new Chart(lienzoIngresos, {
                    type: 'bar',
                    data: {
                        labels: fechas.map(f => { const [, m, d] = f.split('-'); return `${d}/${m}`; }),
                        datasets: [{ label: 'Ingresos', data: Object.values(ingresos), backgroundColor: acento,
                            borderRadius: 4, borderSkipped: 'start', maxBarThickness: 18 }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { callbacks: { title: i => fechas[i[0].dataIndex] } } },
                        scales: {
                            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: rejilla }, border: { display: false } },
                        },
                    },
                });
            }

            const zonas = @json($porZona);
            const lienzoZonas = document.getElementById('grafico-zonas');
            if (lienzoZonas) {
                new Chart(lienzoZonas, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(zonas),
                        datasets: [{ label: 'Alertas', data: Object.values(zonas), backgroundColor: acento,
                            borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 }],
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: rejilla }, border: { display: false } },
                            y: { grid: { display: false } },
                        },
                    },
                });
            }
        })();
    </script>
@endpush
