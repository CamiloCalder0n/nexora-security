<?php

namespace App\Modules\Monitoreo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Reglas de `POST /agente/eventos` según docs/CONTRATO_API_AGENTE.md. */
class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid:4'],
            'camara_id' => ['required', 'integer', 'exists:camaras,id'],
            'tipo_objeto' => ['required', 'in:persona,vehiculo'],
            'cantidad' => ['required', 'integer', 'between:1,50'],
            'confianza' => ['required', 'numeric', 'between:0,1'],
            'fecha_hora' => [
                'required',
                'date',
                'regex:/(Z|[+-]\d{2}:\d{2})$/',
                'after_or_equal:'.now()->subDay()->toIso8601String(),
                'before_or_equal:'.now()->addMinutes(5)->toIso8601String(),
            ],
            'ruta_evidencia' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_hora.regex' => 'La fecha debe incluir la zona horaria, ej. 2026-11-06T19:04:40-05:00.',
            'fecha_hora.after_or_equal' => 'La fecha no puede tener más de 24 horas.',
            'fecha_hora.before_or_equal' => 'La fecha no puede estar más de 5 minutos en el futuro.',
        ];
    }
}
