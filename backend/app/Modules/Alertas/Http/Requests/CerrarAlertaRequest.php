<?php

namespace App\Modules\Alertas\Http\Requests;

use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Domain\Clasificacion;
use App\Modules\Alertas\Domain\Models\Alerta;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** HU03: no se cierra sin clasificar, y un incidente necesita observación. */
class CerrarAlertaRequest extends FormRequest
{
    /** Antes de validar: quien no puede atender recibe 403, no los mensajes de HU03. */
    public function authorize(AlertaService $alertas): Response
    {
        return $alertas->puedeAtender($this->user()) ? Response::allow() : Response::deny(AlertaService::SIN_PERMISO);
    }

    public function rules(): array
    {
        return [
            'clasificacion' => ['required', Rule::enum(Clasificacion::class)],
            'observacion' => [
                'nullable', 'string', 'max:'.Alerta::MAX_OBSERVACION,
                Rule::requiredIf($this->input('clasificacion') === Clasificacion::Incidente->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'clasificacion.required' => AlertaService::FALTA_CLASIFICACION,
            'observacion.required' => AlertaService::INCIDENTE_SIN_OBSERVACION,
        ];
    }
}
