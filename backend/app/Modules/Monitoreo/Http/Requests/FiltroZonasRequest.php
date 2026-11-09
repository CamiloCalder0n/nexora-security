<?php

namespace App\Modules\Monitoreo\Http\Requests;

use App\Modules\Monitoreo\Domain\TipoZona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroZonasRequest extends FormRequest
{
    /** En web un filtro inválido vuelve al listado sin filtros, así no se redirige en bucle. */
    protected $redirectRoute = 'panel.monitoreo.zonas.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::enum(TipoZona::class)],
            'activa' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'tipo' => 'tipo de zona',
            'activa' => 'estado',
            'page' => 'página',
        ];
    }
}
