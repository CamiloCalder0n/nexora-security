<?php

namespace App\Modules\Porteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutorizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitante_nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'visitante_documento' => ['sometimes', 'required', 'string', 'max:20'],
            'placa' => ['sometimes', 'nullable', 'string', 'max:10'],
            'valida_desde' => ['sometimes', 'required', 'date'],
            'valida_hasta' => ['sometimes', 'required', 'date', 'after:valida_desde'],
        ];
    }

    public function attributes(): array
    {
        return (new StoreAutorizacionRequest)->attributes();
    }

    public function datos(): array
    {
        $datos = $this->validated();

        if (array_key_exists('placa', $datos)) {
            $datos['placa'] = $datos['placa'] !== null ? mb_strtoupper($datos['placa']) : null;
        }

        return $datos;
    }
}
