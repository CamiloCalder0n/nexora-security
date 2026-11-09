<?php

namespace App\Modules\Porteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroMisVisitasRequest extends FormRequest
{
    /** Un filtro inválido vuelve al listado sin filtros (nunca a la misma URL, para no entrar en bucle); en JSON responde 422. */
    protected $redirectRoute = 'panel.porteria.mis-visitas.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', Rule::when($this->filled('desde'), 'after_or_equal:desde')],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'desde' => 'fecha desde',
            'hasta' => 'fecha hasta',
        ];
    }

    /** @return array{search?: string, desde?: string, hasta?: string} */
    public function filtros(): array
    {
        return array_filter($this->safe()->only('search', 'desde', 'hasta'), fn ($valor) => $valor !== null && $valor !== '');
    }
}
