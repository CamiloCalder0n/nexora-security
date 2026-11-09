<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\Placa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutorizacionRequest extends FormRequest
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
            'residente_id' => [Rule::requiredIf($this->user()?->rol === Rol::Admin), 'integer', 'exists:residentes,id'],
            'unidad_id' => ['required', 'integer', 'exists:unidades,id'],
            'visitante_nombre' => ['required', 'string', 'max:120'],
            'visitante_documento' => ['required', 'string', 'max:20'],
            'placa' => ['nullable', 'string', 'max:10', 'regex:'.Placa::FORMATO],
            'valida_desde' => ['required', 'date'],
            'valida_hasta' => ['required', 'date', 'after:valida_desde'],
        ];
    }

    public function attributes(): array
    {
        return [
            'residente_id' => 'residente',
            'unidad_id' => 'unidad',
            'visitante_nombre' => 'nombre del visitante',
            'visitante_documento' => 'documento del visitante',
            'valida_desde' => 'fecha de inicio',
            'valida_hasta' => 'fecha de fin',
        ];
    }

    public function messages(): array
    {
        return ['placa.regex' => Placa::MENSAJE_FORMATO];
    }

    /** Los datos de la autorización sin el residente (que llega aparte). */
    public function datos(): array
    {
        return [...$this->safe()->except('residente_id'), 'placa' => $this->validated('placa')];
    }
}
