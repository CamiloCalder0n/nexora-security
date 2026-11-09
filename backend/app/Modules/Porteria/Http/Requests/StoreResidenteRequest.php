<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\TipoResidente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreResidenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'documento' => ['required', 'string', 'max:20', $this->documentoUnico()],
            'telefono' => ['nullable', 'string', 'max:20'],
            'tipo_residente' => ['required', Rule::enum(TipoResidente::class)],
            'unidades' => ['required', 'array', 'min:1'],
            'unidades.*' => ['integer', 'distinct', 'exists:unidades,id'],
            'titular_de' => ['nullable', 'array'],
            'titular_de.*' => ['integer', Rule::in(array_map('intval', (array) $this->input('unidades', [])))],
        ];
    }

    public function attributes(): array
    {
        return [
            'documento' => 'documento',
            'telefono' => 'teléfono',
            'tipo_residente' => 'tipo de residente',
            'unidades' => 'unidades',
            'unidades.*' => 'unidad',
            'titular_de.*' => 'unidad de la que es titular',
        ];
    }

    public function messages(): array
    {
        return [
            'unidades.required' => 'Seleccione al menos una unidad.',
            'unidades.min' => 'Seleccione al menos una unidad.',
            'titular_de.*.in' => 'Solo puede ser titular de una unidad que tenga asignada.',
        ];
    }

    protected function documentoUnico(): Unique
    {
        return Rule::unique('residentes', 'documento');
    }
}
