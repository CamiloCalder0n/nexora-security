<?php

namespace App\Modules\Monitoreo\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateZonaRequest extends StoreZonaRequest
{
    public function rules(): array
    {
        $reglas = parent::rules();

        if ($this->expectsJson()) {
            foreach (['nombre', 'tipo', 'prioridad_base', 'ventana_correlacion_min', 'ventana_silencio_min'] as $campo) {
                $reglas[$campo] = ['sometimes', ...array_diff($reglas[$campo], ['required'])];
            }
        }

        return $reglas;
    }

    protected function reglaNombreUnico()
    {
        return Rule::unique('zonas', 'nombre')->ignore($this->route('zona'));
    }
}
