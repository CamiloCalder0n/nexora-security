<?php

namespace App\Modules\Porteria\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class FiltroMisVisitasRequest extends FormRequest
{
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

    /** En web vuelve al listado sin filtros (así no hay bucle de redirecciones). */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(
            redirect()->route('panel.porteria.mis-visitas.index')
                ->with('error', 'Filtro no válido: '.$validator->errors()->first())
        );
    }
}
