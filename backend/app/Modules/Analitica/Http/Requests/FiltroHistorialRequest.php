<?php

namespace App\Modules\Analitica\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroHistorialRequest extends FormRequest
{
    public const RESULTADOS = [
        'pendiente' => 'Pendiente', 'explicado' => 'Explicado', 'alerta' => 'Generó alerta',
        'suprimido' => 'Suprimido', 'fuera_franja' => 'Fuera de franja',
    ];

    protected $redirectRoute = 'panel.analitica.historial';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'zona_id' => ['nullable', 'integer', 'min:1'],
            'tipo_objeto' => ['nullable', Rule::in(['persona', 'vehiculo'])],
            'resultado' => ['nullable', Rule::in(array_keys(self::RESULTADOS))],
            'orden' => ['nullable', Rule::in(['recientes', 'antiguos'])],
        ];
    }

    public function attributes(): array
    {
        return ['desde' => 'desde', 'hasta' => 'hasta', 'zona_id' => 'zona', 'tipo_objeto' => 'tipo', 'resultado' => 'resultado', 'orden' => 'orden'];
    }
}
