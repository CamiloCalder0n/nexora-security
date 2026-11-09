<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\EstadoAutorizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroMisAutorizacionesRequest extends FormRequest
{
    /** Un filtro inválido vuelve al listado sin filtros (nunca a la misma URL, para no entrar en bucle); en JSON responde 422. */
    protected $redirectRoute = 'panel.porteria.mis-autorizaciones.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(EstadoAutorizacion::class)],
        ];
    }

    public function attributes(): array
    {
        return ['search' => 'búsqueda'];
    }

    /** @return array{search?: string, estado?: string} */
    public function filtros(): array
    {
        return array_filter($this->safe()->only('search', 'estado'), fn ($valor) => $valor !== null && $valor !== '');
    }
}
