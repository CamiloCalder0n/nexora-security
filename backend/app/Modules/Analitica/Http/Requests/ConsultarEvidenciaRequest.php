<?php

namespace App\Modules\Analitica\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarEvidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:10', 'max:500']];
    }

    public function attributes(): array
    {
        return ['motivo' => 'motivo'];
    }
}
