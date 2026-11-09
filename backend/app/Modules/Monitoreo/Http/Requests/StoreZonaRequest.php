<?php

namespace App\Modules\Monitoreo\Http\Requests;

use App\Modules\Monitoreo\Domain\TipoZona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('activa') || ! $this->expectsJson()) {
            $this->merge(['activa' => $this->boolean('activa')]);
        }

        if (is_array($this->input('franjas'))) {
            $this->merge(['franjas' => array_values(array_filter(
                $this->input('franjas'),
                fn ($f) => is_array($f) && (($f['hora_inicio'] ?? '') !== '' || ($f['hora_fin'] ?? '') !== ''),
            ))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80', $this->reglaNombreUnico()],
            'tipo' => ['required', Rule::enum(TipoZona::class)],
            'prioridad_base' => ['required', 'integer', 'between:1,3'],
            'ventana_correlacion_min' => ['required', 'integer', 'between:1,120'],
            'ventana_silencio_min' => ['required', 'integer', 'between:1,120'],
            'activa' => ['boolean'],
            'franjas' => ['array', 'max:14'],
            'franjas.*.dia_semana' => ['nullable', 'integer', 'between:0,6'],
            'franjas.*.hora_inicio' => ['required', 'date_format:H:i'],
            'franjas.*.hora_fin' => ['required', 'date_format:H:i'],
        ];
    }

    public function attributes(): array
    {
        return [
            'prioridad_base' => 'prioridad',
            'ventana_correlacion_min' => 'ventana de correlación',
            'ventana_silencio_min' => 'ventana de silencio',
            'franjas.*.dia_semana' => 'día de la franja',
            'franjas.*.hora_inicio' => 'hora de inicio',
            'franjas.*.hora_fin' => 'hora de fin',
        ];
    }

    protected function reglaNombreUnico()
    {
        return Rule::unique('zonas', 'nombre');
    }
}
