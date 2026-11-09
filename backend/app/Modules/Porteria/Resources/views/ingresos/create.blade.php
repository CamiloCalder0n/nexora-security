@extends('layouts.panel')

@section('titulo', 'Registrar ingreso')
@section('subtitulo', 'Anote a quien entra al conjunto. Si trae autorización de un residente, elíjala para precargar sus datos.')

@section('acciones')
    <a href="{{ route('panel.porteria.autorizaciones.vigentes') }}" class="btn btn-outline-secondary">
        <i class="bi bi-card-checklist me-1"></i>Autorizaciones vigentes
    </a>
    <a href="{{ route('panel.porteria.ingresos.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-journal-text me-1"></i>Ver ingresos
    </a>
@endsection

@push('estilos')
    <style>
        .nx-tipo-ingreso .btn { min-height: 4.5rem; font-size: 1.05rem; }
        .nx-tipo-ingreso .btn i { font-size: 1.5rem; }
        .nx-formulario-ingreso .form-control, .nx-formulario-ingreso .form-select { min-height: 3rem; font-size: 1.05rem; }
    </style>
@endpush

@section('contenido')
    @php
        $tipoActual = old('tipo_ingreso', 'visitante');
        $iconos = ['visitante' => 'person', 'domiciliario' => 'bicycle', 'vehiculo' => 'car-front', 'contratista' => 'tools'];
    @endphp

    <form method="POST" action="{{ route('panel.porteria.ingresos.store') }}" class="nx-tarjeta p-3 p-md-4 nx-formulario-ingreso" id="formIngreso" novalidate>
        @csrf

        <fieldset class="mb-4">
            <legend class="form-label fs-6 mb-2">Tipo de ingreso <span class="text-danger">*</span></legend>
            <div class="row g-2 nx-tipo-ingreso" role="radiogroup">
                @foreach ($tipos as $valor => $texto)
                    <div class="col-6 col-md-3">
                        <input type="radio" class="btn-check" name="tipo_ingreso" id="tipo_{{ $valor }}" value="{{ $valor }}" autocomplete="off" @checked($tipoActual === $valor) required>
                        <label class="btn btn-outline-primary w-100 d-flex flex-column align-items-center justify-content-center gap-1" for="tipo_{{ $valor }}">
                            <i class="bi bi-{{ $iconos[$valor] ?? 'person' }}" aria-hidden="true"></i>{{ $texto }}
                        </label>
                    </div>
                @endforeach
            </div>
            @error('tipo_ingreso')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </fieldset>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <x-campo-select name="unidad_id" label="Unidad que visita" :opciones="$unidades" :value="$unidadInicial" vacio="Seleccione la unidad…" requerido />
            </div>
            <div class="col-12 col-md-6">
                <div class="mb-3">
                    <label for="autorizacion_id" class="form-label">Autorización del residente</label>
                    <select id="autorizacion_id" name="autorizacion_id" @class(['form-select', 'is-invalid' => $errors->has('autorizacion_id')]) disabled
                            data-url="{{ route('panel.porteria.ingresos.autorizaciones') }}"
                            data-inicial="{{ old('autorizacion_id', $autorizacionInicial) }}">
                        <option value="">Primero elija la unidad</option>
                    </select>
                    <div class="form-text" id="ayudaAutorizacion" aria-live="polite">Opcional. Al elegirla queda marcada como usada.</div>
                    @error('autorizacion_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-12 col-md-6">
                <x-campo name="nombre_visitante" label="Nombre del visitante" maxlength="120" autocomplete="off" requerido />
            </div>
            <div class="col-12 col-md-6">
                <x-campo name="documento" label="Documento" maxlength="20" inputmode="numeric" autocomplete="off" requerido />
            </div>

            <div class="col-12 col-md-6" id="grupoPlaca" @if ($tipoActual !== 'vehiculo') hidden @endif>
                <x-campo name="placa" label="Placa" maxlength="10" placeholder="ABC123" autocomplete="off" class="text-uppercase" ayuda="Carro ABC123 o moto ABC12D." requerido />
            </div>
            <div class="col-6 col-md-3">
                <x-campo name="cantidad_personas" label="Personas" type="number" min="1" max="20" :value="1" requerido />
            </div>
            <div class="col-6 col-md-3">
                <x-campo-select name="zona_id" label="Zona de ingreso" :opciones="$zonas" :value="count($zonas) === 1 ? array_key_first($zonas) : null" requerido />
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-2">
            <a href="{{ route('panel.porteria.ingresos.index') }}" class="btn btn-lg btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-lg btn-primary px-4">
                <i class="bi bi-box-arrow-in-right me-1"></i>Registrar ingreso
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('formIngreso');
            const unidad = document.getElementById('unidad_id');
            const autorizacion = document.getElementById('autorizacion_id');
            const ayuda = document.getElementById('ayudaAutorizacion');
            const grupoPlaca = document.getElementById('grupoPlaca');
            const placa = document.getElementById('placa');
            const nombre = document.getElementById('nombre_visitante');
            const documento = document.getElementById('documento');
            const token = document.querySelector('meta[name="csrf-token"]').content;
            let porId = {};

            const tipoActual = () => form.querySelector('input[name="tipo_ingreso"]:checked')?.value;

            const actualizarPlaca = () => {
                const esVehiculo = tipoActual() === 'vehiculo';
                grupoPlaca.hidden = !esVehiculo;
                placa.required = esVehiculo;
                if (!esVehiculo) placa.classList.remove('is-invalid');
            };

            const opcion = (valor, texto) => {
                const o = document.createElement('option');
                o.value = valor;
                o.textContent = texto;
                return o;
            };

            const cargarAutorizaciones = async (seleccionar = '') => {
                porId = {};
                autorizacion.replaceChildren(opcion('', unidad.value ? 'Cargando…' : 'Primero elija la unidad'));
                autorizacion.disabled = true;
                if (!unidad.value) return;

                try {
                    const url = new URL(autorizacion.dataset.url, window.location.origin);
                    url.searchParams.set('unidad_id', unidad.value);
                    const respuesta = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
                    if (!respuesta.ok) throw new Error(respuesta.status);
                    const { data } = await respuesta.json();

                    autorizacion.replaceChildren(opcion('', data.length ? 'Sin autorización (ingreso manual)' : 'La unidad no tiene autorizaciones vigentes'));
                    data.forEach((a) => {
                        porId[a.id] = a;
                        const detalle = [a.visitante_nombre, a.placa, 'hasta ' + a.valida_hasta_texto].filter(Boolean).join(' · ');
                        autorizacion.append(opcion(a.id, detalle));
                    });
                    autorizacion.disabled = data.length === 0;
                    ayuda.textContent = data.length
                        ? `${data.length} autorización(es) vigente(s). Al elegirla queda marcada como usada.`
                        : 'Registre los datos del visitante a mano.';

                    if (seleccionar && porId[seleccionar]) {
                        autorizacion.value = String(seleccionar);
                        precargar(false);
                    }
                } catch (e) {
                    autorizacion.replaceChildren(opcion('', 'No se pudieron cargar las autorizaciones'));
                    ayuda.textContent = 'Revise la conexión y vuelva a elegir la unidad.';
                }
            };

            const precargar = (sobrescribir = true) => {
                const a = porId[autorizacion.value];
                if (!a) return;
                const poner = (campo, valor) => {
                    if (valor && (sobrescribir || !campo.value)) campo.value = valor;
                };
                poner(nombre, a.visitante_nombre);
                poner(documento, a.visitante_documento);
                poner(placa, a.placa);
            };

            form.querySelectorAll('input[name="tipo_ingreso"]').forEach((r) => r.addEventListener('change', actualizarPlaca));
            unidad.addEventListener('change', () => cargarAutorizaciones());
            autorizacion.addEventListener('change', () => precargar());

            actualizarPlaca();
            if (unidad.value) cargarAutorizaciones(autorizacion.dataset.inicial);
        })();
    </script>
@endpush
