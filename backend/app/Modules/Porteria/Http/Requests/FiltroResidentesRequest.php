<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\TipoResidente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroResidentesRequest extends FormRequest
{
    /** Un filtro inválido vuelve al listado sin filtros (nunca a la misma URL, para no entrar en bucle); en JSON responde 422. */
    protected $redirectRoute = 'panel.porteria.residentes.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'tipo_residente' => ['nullable', Rule::enum(TipoResidente::class)],
            'unidad_id' => ['nullable', 'integer', 'exists:unidades,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'tipo_residente' => 'tipo de residente',
            'unidad_id' => 'unidad',
        ];
    }

    public function filtros(): array
    {
        return array_filter($this->validated(), fn ($valor) => $valor !== null && $valor !== '');
    }
}
