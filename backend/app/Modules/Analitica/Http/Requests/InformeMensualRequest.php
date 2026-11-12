<?php

namespace App\Modules\Analitica\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InformeMensualRequest extends FormRequest
{
    protected $redirectRoute = 'panel.analitica.informe';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['mes' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')]];
    }

    public function attributes(): array
    {
        return ['mes' => 'mes'];
    }

    public function mes(): string
    {
        return $this->validated('mes') ?? now()->format('Y-m');
    }
}
