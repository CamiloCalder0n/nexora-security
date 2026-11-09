@php
    $err = fn ($campo) => is_int($i) && $errors->has("franjas.$i.$campo");
    $dia = $franja['dia_semana'] ?? null;
@endphp
<div class="row g-2 align-items-start border rounded p-2 mx-0" data-franja>
    <div class="col-12 col-sm-4">
        <label for="franja-{{ $i }}-dia" class="form-label small mb-1">Día</label>
        <select id="franja-{{ $i }}-dia" name="franjas[{{ $i }}][dia_semana]" @class(['form-select form-select-sm', 'is-invalid' => $err('dia_semana')])>
            <option value="">Todos los días</option>
            @foreach ($dias as $numero => $nombre)
                <option value="{{ $numero }}" @selected($dia !== null && $dia !== '' && (int) $dia === $numero)>{{ $nombre }}</option>
            @endforeach
        </select>
        @if ($err('dia_semana'))<div class="invalid-feedback">{{ $errors->first("franjas.$i.dia_semana") }}</div>@endif
    </div>
    <div class="col-5 col-sm-3">
        <label for="franja-{{ $i }}-inicio" class="form-label small mb-1">Inicio <span class="text-danger">*</span></label>
        <input type="time" id="franja-{{ $i }}-inicio" name="franjas[{{ $i }}][hora_inicio]" value="{{ $franja['hora_inicio'] ?? '' }}"
            @class(['form-control form-control-sm', 'is-invalid' => $err('hora_inicio')]) required>
        @if ($err('hora_inicio'))<div class="invalid-feedback">{{ $errors->first("franjas.$i.hora_inicio") }}</div>@endif
    </div>
    <div class="col-5 col-sm-3">
        <label for="franja-{{ $i }}-fin" class="form-label small mb-1">Fin <span class="text-danger">*</span></label>
        <input type="time" id="franja-{{ $i }}-fin" name="franjas[{{ $i }}][hora_fin]" value="{{ $franja['hora_fin'] ?? '' }}"
            @class(['form-control form-control-sm', 'is-invalid' => $err('hora_fin')]) required>
        @if ($err('hora_fin'))<div class="invalid-feedback">{{ $errors->first("franjas.$i.hora_fin") }}</div>@endif
    </div>
    <div class="col-2 col-sm-2 d-flex align-items-end justify-content-end" style="min-height: 3.6rem">
        <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-franja title="Quitar franja" aria-label="Quitar franja">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>
