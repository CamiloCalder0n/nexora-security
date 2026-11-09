<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\Placa;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAutorizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('placa')) {
            $this->merge(['placa' => Placa::normalizar($this->input('placa'))]);
        }
    }

    public function rules(): array
    {
        return [
            'visitante_nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'visitante_documento' => ['sometimes', 'required', 'string', 'max:20'],
            'placa' => ['sometimes', 'nullable', 'string', 'max:10', 'regex:'.Placa::FORMATO],
            'valida_desde' => ['sometimes', 'required', 'date'],
            'valida_hasta' => ['sometimes', 'required', 'date', 'after:valida_desde'],
        ];
    }

    public function attributes(): array
    {
        return (new StoreAutorizacionRequest)->attributes();
    }

    public function messages(): array
    {
        return ['placa.regex' => Placa::MENSAJE_FORMATO];
    }

    public function datos(): array
    {
        return $this->validated();
    }
}
