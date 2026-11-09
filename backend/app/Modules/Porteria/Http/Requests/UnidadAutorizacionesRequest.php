<?php

namespace App\Modules\Porteria\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Unidad de la que se piden las autorizaciones vigentes (lo consulta el formulario de ingreso por JSON). */
class UnidadAutorizacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unidad_id' => ['required', 'integer', 'exists:unidades,id'],
        ];
    }

    public function attributes(): array
    {
        return ['unidad_id' => 'unidad'];
    }

    public function unidadId(): int
    {
        return (int) $this->validated('unidad_id');
    }
}
