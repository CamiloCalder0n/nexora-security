<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\EstadoAutorizacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filtros del listado de autorizaciones (API y panel). */
class FiltroAutorizacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::enum(EstadoAutorizacion::class)],
            'unidad_id' => ['nullable', 'integer', 'exists:unidades,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'search' => 'búsqueda',
            'unidad_id' => 'unidad',
            'page' => 'página',
        ];
    }

    /** En el panel vuelve al listado sin filtros, así un filtro inválido no se repite. */
    protected function getRedirectUrl(): string
    {
        return $this->url();
    }

    public function filtros(): array
    {
        return $this->safe()->only(['search', 'estado', 'unidad_id']);
    }
}
