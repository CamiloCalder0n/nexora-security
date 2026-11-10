@extends('layouts.panel')

@section('titulo', 'Eventos en vivo')
@section('subtitulo', 'Últimas detecciones de las cámaras, sin identificar personas. Se actualiza cada 3 s.')

@php
    $resultados = [
        'pendiente' => ['Pendiente', 'text-bg-info'],
        'explicado' => ['Explicado', 'text-bg-success'],
        'alerta' => ['Alerta', 'text-bg-danger'],
        'suprimido' => ['Suprimido', 'text-bg-secondary'],
        'fuera_franja' => ['Fuera de franja', 'text-bg-warning'],
    ];
@endphp

@section('contenido')
    <style>
        @keyframes nx-evento-nuevo { from { background-color: var(--bs-warning-bg-subtle); } to { background-color: transparent; } }
        .nx-evento-nuevo > td { animation: nx-evento-nuevo 2s ease-out; }
    </style>

    <div id="eventos-aviso" class="small text-warning mb-2 d-none" role="status">
        <i class="bi bi-wifi-off" aria-hidden="true"></i> Sin conexión con el servidor; se sigue intentando.
    </div>

    <div id="eventos-vacio" class="nx-tarjeta p-5 text-center nx-texto-suave {{ $eventos ? 'd-none' : '' }}">
        <i class="bi bi-broadcast fs-1 d-block mb-2" aria-hidden="true"></i>
        Aún no hay eventos registrados.
    </div>

    <div id="eventos-tabla" class="nx-tarjeta table-responsive {{ $eventos ? '' : 'd-none' }}">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Hora</th><th>Cámara</th><th>Zona</th><th>Tipo</th>
                    <th class="text-end">Cantidad</th><th class="text-end">Confianza</th><th>Resultado</th>
                </tr>
            </thead>
            <tbody id="eventos-cuerpo" data-url="{{ route('panel.monitoreo.eventos.feed') }}">
                @foreach ($eventos as $evento)
                    @php [$texto, $clase] = $resultados[$evento['resultado']] ?? [$evento['resultado'], 'text-bg-light']; @endphp
                    <tr data-id="{{ $evento['id'] }}" data-resultado="{{ $evento['resultado'] }}">
                        <td class="text-nowrap">{{ $evento['hora'] }}</td>
                        <td>{{ $evento['camara'] ?? '—' }}</td>
                        <td>{{ $evento['zona'] ?? '—' }}</td>
                        <td>{{ $evento['tipo'] }}</td>
                        <td class="text-end">{{ $evento['cantidad'] }}</td>
                        <td class="text-end">{{ $evento['confianza'] }}%</td>
                        <td><span class="badge {{ $clase }}">{{ $texto }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const INTERVALO = 3000;
            const resultados = @json($resultados);
            const cuerpo = document.getElementById('eventos-cuerpo');
            const tabla = document.getElementById('eventos-tabla');
            const vacio = document.getElementById('eventos-vacio');
            const aviso = document.getElementById('eventos-aviso');
            // id → resultado: se resalta la fila nueva y la que cambia de resultado (pendiente → explicado, alerta…).
            let conocidos = new Map([...cuerpo.querySelectorAll('tr')].map(tr => [tr.dataset.id, tr.dataset.resultado]));
            let ocupado = false;

            const celda = (texto, clase) => {
                const td = document.createElement('td');
                td.textContent = texto;
                if (clase) td.className = clase;
                return td;
            };

            const fila = (e, nuevo) => {
                const tr = document.createElement('tr');
                tr.dataset.id = e.id;
                if (nuevo) tr.className = 'nx-evento-nuevo';
                const [texto, clase] = resultados[e.resultado] || [e.resultado ?? '—', 'text-bg-light'];
                const badge = document.createElement('span');
                badge.className = `badge ${clase}`;
                badge.textContent = texto;
                const resultado = document.createElement('td');
                resultado.appendChild(badge);
                tr.append(
                    celda(e.hora ?? '—', 'text-nowrap'), celda(e.camara ?? '—'), celda(e.zona ?? '—'), celda(e.tipo ?? '—'),
                    celda(e.cantidad ?? '—', 'text-end'), celda(e.confianza == null ? '—' : `${e.confianza}%`, 'text-end'),
                    resultado,
                );
                return tr;
            };

            async function refrescar() {
                if (ocupado || document.visibilityState !== 'visible') return;
                ocupado = true;
                try {
                    const r = await fetch(cuerpo.dataset.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    if (! r.ok) throw new Error(r.status);
                    const { eventos } = await r.json();
                    const previos = conocidos;
                    cuerpo.replaceChildren(...eventos.map(e => fila(e, previos.get(e.id) !== e.resultado)));
                    conocidos = new Map(eventos.map(e => [e.id, e.resultado]));
                    tabla.classList.toggle('d-none', eventos.length === 0);
                    vacio.classList.toggle('d-none', eventos.length > 0);
                    aviso.classList.add('d-none');
                } catch {
                    aviso.classList.remove('d-none');
                } finally {
                    ocupado = false;
                }
            }

            setInterval(refrescar, INTERVALO);
            document.addEventListener('visibilitychange', refrescar);
        })();
    </script>
@endpush
