<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\TipoResidente;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class FiltroResidentesRequest extends FormRequest
{
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

    /** Un filtro inválido vuelve al listado sin filtros (nunca a la misma URL, para no entrar en bucle). */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->route('panel.porteria.residentes.index')
                ->with('error', 'El filtro no es válido; se muestran todos los residentes.')
        );
    }

    public function filtros(): array
    {
        return array_filter($this->validated(), fn ($valor) => $valor !== null && $valor !== '');
    }
}
