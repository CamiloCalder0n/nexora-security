<?php

namespace App\Modules\Porteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FiltroVigentesRequest extends FormRequest
{
    /** Un filtro inválido vuelve al listado sin filtros (nunca a la misma URL, para no entrar en bucle); en JSON responde 422. */
    protected $redirectRoute = 'panel.porteria.autorizaciones.vigentes';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'page' => 'página',
        ];
    }

    /** @return array{search?: string} */
    public function filtros(): array
    {
        return array_filter($this->safe()->only('search'), fn ($valor) => $valor !== null && $valor !== '');
    }
}
