<?php

namespace App\Modules\Monitoreo\Http\Requests;

use App\Modules\Monitoreo\Domain\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;

/** Reglas de `POST /agente/eventos` según docs/CONTRATO_API_AGENTE.md. */
class StoreEventoRequest extends FormRequest
{
    public const RUTA_SEGURA = '/^(?!\/)(?!.*\.\.)[A-Za-z0-9_\-\/.]+\z/';

    public function authorize(): bool
    {
        return true;
    }

    /** El uuid se guarda en minúsculas: el mismo evento en mayúsculas sigue siendo un duplicado. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('uuid'))) {
            $this->merge(['uuid' => strtolower($this->input('uuid'))]);
        }
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'regex:/^'.Evento::PATRON_UUID.'\z/'],
            // strict: el agente manda JSON con números; ni true ni "5" cuentan como entero.
            'camara_id' => ['required', 'integer:strict', 'exists:camaras,id'],
            'tipo_objeto' => ['required', 'in:persona,vehiculo'],
            'cantidad' => ['required', 'integer:strict', 'between:1,50'],
            'confianza' => ['required', 'numeric:strict', 'between:0,1'],
            'fecha_hora' => [
                'required',
                'date',
                'regex:/(Z|[+-]\d{2}:\d{2})\z/',
                'after_or_equal:'.now()->subDay()->toIso8601String(),
                'before_or_equal:'.now()->addMinutes(5)->toIso8601String(),
            ],
            // Ruta relativa en el PC del conjunto: se muestra en el panel y en Telegram, así que solo
            // caracteres seguros, sin ../, sin ruta absoluta ni caracteres de control.
            'ruta_evidencia' => ['nullable', 'string', 'max:255', 'regex:'.self::RUTA_SEGURA],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_hora.regex' => 'La fecha debe incluir la zona horaria, ej. 2026-11-06T19:04:40-05:00.',
            'fecha_hora.after_or_equal' => 'La fecha no puede tener más de 24 horas.',
            'fecha_hora.before_or_equal' => 'La fecha no puede estar más de 5 minutos en el futuro.',
            'ruta_evidencia.regex' => 'La ruta debe ser relativa (ej. evidencias/cam1/20261106-190440.jpg), sin .. ni caracteres especiales.',
        ];
    }
}
