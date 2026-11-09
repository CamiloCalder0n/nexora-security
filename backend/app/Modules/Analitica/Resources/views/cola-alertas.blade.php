@extends('layouts.panel')

@section('titulo', 'Cola de alertas')
@section('subtitulo', 'Alertas sin cerrar: primero las abiertas, luego por prioridad y antigüedad. Se actualiza cada 15 s.')

@php
    $prioridades = [3 => ['Alta', 'text-bg-danger'], 2 => ['Media', 'text-bg-warning'], 1 => ['Baja', 'text-bg-secondary']];
    $estados = ['abierta' => ['Abierta', 'text-bg-danger'], 'atendida' => ['Atendida', 'text-bg-primary']];
    $clasificaciones = ['falsa_alarma' => 'Falsa alarma', 'sin_novedad' => 'Sin novedad', 'incidente' => 'Incidente'];
@endphp

@section('acciones')
    <a href="{{ route('panel.analitica.cola-alertas') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Actualizar
    </a>
@endsection

@section('contenido')
    {{--
        Atender y Cerrar usan el API de Alertas (PR de Santiago, rutas api.v1.alertas.atender / cerrar) con la
        sesión del panel: además del PR hace falta activar statefulApi() en bootstrap/app.php. Sin esas
        rutas la cola es solo de lectura.
    --}}
    <div id="cola-mensaje" class="alert d-none" role="alert"></div>

    @if ($alertas === null)
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>No se pudo leer MongoDB: la cola de alertas no está disponible por ahora.</span>
        </div>
    @elseif (! $alertas)
        <div class="nx-tarjeta p-5 text-center nx-texto-suave">
            <i class="bi bi-check2-circle fs-1 d-block mb-2" aria-hidden="true"></i>
            No hay alertas pendientes.
        </div>
    @else
        <p class="small nx-texto-suave mb-3">
            {{ count($alertas) }} {{ count($alertas) === 1 ? 'alerta pendiente' : 'alertas pendientes' }}@if (count($alertas) >= $limite) (se muestran las primeras {{ $limite }})@endif.
        </p>

        <div class="row g-3">
            @foreach ($alertas as $alerta)
                @php
                    [$textoPrioridad, $clasePrioridad] = $prioridades[$alerta['prioridad']] ?? ['P'.$alerta['prioridad'], 'text-bg-secondary'];
                    [$textoEstado, $claseEstado] = $estados[$alerta['estado']] ?? [$alerta['estado'], 'text-bg-light'];
                @endphp
                <div class="col-md-6 col-xxl-4">
                    <article class="nx-tarjeta p-3 h-100 d-flex flex-column nx-alerta" data-alerta="{{ $alerta['id'] }}"
                             @if ($alerta['estado'] === 'abierta') style="border-left: 4px solid var(--bs-danger)" @endif>
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                            <span class="badge {{ $clasePrioridad }}">Prioridad {{ $textoPrioridad }}</span>
                            <span class="badge {{ $claseEstado }}">{{ $textoEstado }}</span>
                            <span class="ms-auto small nx-texto-suave" title="{{ $alerta['fecha_hora']->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                {{ $alerta['fecha_hora']->locale('es')->diffForHumans() }}
                            </span>
                        </div>

                        <h2 class="h6 mb-1">{{ $alerta['motivo'] }}</h2>
                        <dl class="row small mb-0 mt-2">
                            <dt class="col-5 nx-texto-suave fw-normal">Zona</dt>
                            <dd class="col-7 mb-1">{{ $alerta['zona'] }}</dd>
                            <dt class="col-5 nx-texto-suave fw-normal">Cámara</dt>
                            <dd class="col-7 mb-1">{{ $alerta['camara'] ?? '—' }}</dd>
                            <dt class="col-5 nx-texto-suave fw-normal">Eventos</dt>
                            <dd class="col-7 mb-1">{{ $alerta['num_eventos'] }}</dd>
                            <dt class="col-5 nx-texto-suave fw-normal">Atiende</dt>
                            <dd class="col-7 mb-1">{{ $alerta['atendida_por'] ?? 'Nadie todavía' }}</dd>
                        </dl>

                        @if ($acciones)
                            <div class="mt-auto pt-3">
                                <div class="d-flex gap-2">
                                    @if ($alerta['estado'] === 'abierta')
                                        <button type="button" class="btn btn-primary btn-sm flex-fill js-atender"
                                                data-url="{{ route($rutaAtender, $alerta['id']) }}">
                                            <i class="bi bi-person-check" aria-hidden="true"></i> Atender
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-bs-toggle="collapse"
                                            data-bs-target="#cerrar-{{ $alerta['id'] }}" aria-expanded="false" aria-controls="cerrar-{{ $alerta['id'] }}">
                                        <i class="bi bi-check2-square" aria-hidden="true"></i> Cerrar
                                    </button>
                                </div>
                                <form id="cerrar-{{ $alerta['id'] }}" class="collapse mt-3 js-cerrar" data-url="{{ route($rutaCerrar, $alerta['id']) }}" novalidate>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1" for="clasificacion-{{ $alerta['id'] }}">Clasificación <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="clasificacion-{{ $alerta['id'] }}" name="clasificacion" required>
                                            <option value="">Selecciona…</option>
                                            @foreach ($clasificaciones as $valor => $texto)
                                                <option value="{{ $valor }}">{{ $texto }}</option>
                                            @endforeach
                                        </select>
                                        <div class="invalid-feedback" data-error="clasificacion"></div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small mb-1" for="observacion-{{ $alerta['id'] }}">Observación</label>
                                        <textarea class="form-control form-control-sm" id="observacion-{{ $alerta['id'] }}" name="observacion" rows="2" maxlength="2000"></textarea>
                                        <div class="form-text">Obligatoria si es un incidente.</div>
                                        <div class="invalid-feedback" data-error="observacion"></div>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-sm w-100">Confirmar cierre</button>
                                </form>
                            </div>
                        @endif
                    </article>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const INTERVALO = 15000;
            let ocupado = false;

            // Recarga solo con la pestaña visible y sin un cierre a medio escribir.
            const editando = () => [...document.querySelectorAll('.js-cerrar')].some(f => f.classList.contains('show'));
            setInterval(() => {
                if (document.visibilityState === 'visible' && ! ocupado && ! editando()) location.reload();
            }, INTERVALO);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && ! ocupado && ! editando()) location.reload();
            });

            @if ($acciones)
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const mensaje = document.getElementById('cola-mensaje');
                const avisar = (texto, tipo = 'danger') => {
                    mensaje.className = `alert alert-${tipo}`;
                    mensaje.textContent = texto;
                    mensaje.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                };

                async function enviar(url, datos, form = null, intento = 1) {
                    ocupado = true;
                    try {
                        const r = await fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify(datos),
                        });
                        const json = await r.json().catch(() => ({}));
                        if (r.ok) return location.reload();
                        if (r.status === 409) {
                            avisar(json.message || 'Otra persona ya cambió esta alerta.', 'warning');
                            return setTimeout(() => location.reload(), 1500);
                        }
                        if (r.status === 503 && intento < 3) {
                            const espera = (parseInt(r.headers.get('Retry-After'), 10) || 2) * 1000;
                            return setTimeout(() => enviar(url, datos, form, intento + 1), espera);
                        }
                        if (r.status === 422 && form) {
                            for (const [campo, errores] of Object.entries(json.errors || {})) {
                                form.elements[campo]?.classList.add('is-invalid');
                                const caja = form.querySelector(`[data-error="${campo}"]`);
                                if (caja) caja.textContent = errores[0];
                            }
                            ocupado = false;
                            return;
                        }
                        avisar(json.message || 'No se pudo actualizar la alerta. Intenta de nuevo.');
                    } catch {
                        avisar('No hay conexión con el servidor. Intenta de nuevo.');
                    }
                    ocupado = false;
                }

                document.querySelectorAll('.js-atender').forEach(boton => boton.addEventListener('click', () => {
                    boton.disabled = true;
                    enviar(boton.dataset.url, {}).finally(() => { boton.disabled = false; });
                }));

                document.querySelectorAll('.js-cerrar').forEach(form => form.addEventListener('submit', e => {
                    e.preventDefault();
                    form.querySelectorAll('.is-invalid').forEach(c => c.classList.remove('is-invalid'));
                    enviar(form.dataset.url, {
                        clasificacion: form.elements.clasificacion.value,
                        observacion: form.elements.observacion.value || null,
                    }, form);
                }));
            @endif
        })();
    </script>
@endpush
