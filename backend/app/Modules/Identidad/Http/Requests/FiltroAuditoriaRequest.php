<?php

namespace App\Modules\Identidad\Http\Requests;

use App\Modules\Identidad\Application\AuditoriaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroAuditoriaRequest extends FormRequest
{
    protected $redirectRoute = 'panel.identidad.auditoria.index';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'recurso' => ['nullable', Rule::in(array_keys(AuditoriaService::RECURSOS))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    public function attributes(): array
    {
        return ['search' => 'búsqueda', 'recurso' => 'recurso', 'desde' => 'desde', 'hasta' => 'hasta'];
    }
}
