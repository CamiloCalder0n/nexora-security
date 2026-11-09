<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\EstadoAutorizacion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class FiltroMisAutorizacionesRequest extends FormRequest
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

    /** En web vuelve al listado sin filtros (así no hay bucle de redirecciones). */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(
            redirect()->route('panel.porteria.mis-autorizaciones.index')
                ->with('error', 'Filtro no válido: '.$validator->errors()->first())
        );
    }
}
