<?php

namespace App\Modules\Identidad\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** El formulario envía el estado deseado: reenviarlo no invierte el cambio. */
class CambiarActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['activo' => 'estado'];
    }

    public function activo(): bool
    {
        return $this->boolean('activo');
    }
}
