<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\TipoIngreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroIngresosRequest extends FormRequest
{
    /** Ante un filtro inválido vuelve al listado sin filtros: volver "atrás" repetiría la misma URL. */
    protected $redirectRoute = 'panel.porteria.ingresos.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'tipo_ingreso' => ['nullable', Rule::enum(TipoIngreso::class)],
            'unidad_id' => ['nullable', 'integer', 'exists:unidades,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'tipo_ingreso' => 'tipo de ingreso',
            'unidad_id' => 'unidad',
            'desde' => 'fecha desde',
            'hasta' => 'fecha hasta',
        ];
    }

    public function filtros(): array
    {
        return array_filter($this->validated(), fn ($valor) => $valor !== null && $valor !== '');
    }
}
