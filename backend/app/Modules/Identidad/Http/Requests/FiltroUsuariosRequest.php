<?php

namespace App\Modules\Identidad\Http\Requests;

use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroUsuariosRequest extends FormRequest
{
    /** Con filtros inválidos se vuelve al listado limpio (sin query) para no entrar en bucle. */
    protected $redirectRoute = 'panel.identidad.usuarios.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::enum(Rol::class)],
            'activo' => ['nullable', Rule::in(['0', '1'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'rol' => 'rol',
            'activo' => 'estado',
        ];
    }
}
