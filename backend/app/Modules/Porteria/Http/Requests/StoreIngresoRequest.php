<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\Placa;
use App\Modules\Porteria\Domain\TipoIngreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('placa')) {
            $this->merge(['placa' => Placa::normalizar($this->input('placa'))]);
        }
    }

    public function rules(): array
    {
        return [
            'tipo_ingreso' => ['required', Rule::enum(TipoIngreso::class)],
            'autorizacion_id' => ['nullable', 'integer', 'exists:autorizaciones,id'],
            'nombre_visitante' => ['required_without:autorizacion_id', 'nullable', 'string', 'max:120'],
            'documento' => ['required_without:autorizacion_id', 'nullable', 'string', 'max:20'],
            'placa' => ['required_if:tipo_ingreso,'.TipoIngreso::Vehiculo->value, 'nullable', 'string', 'max:10', 'regex:'.Placa::FORMATO],
            'cantidad_personas' => ['required', 'integer', 'between:1,20'],
            'unidad_id' => ['required', 'integer', 'exists:unidades,id'],
            'zona_id' => ['required', 'integer', Rule::exists('zonas', 'id')->where('activa', true)],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_ingreso' => 'tipo de ingreso',
            'autorizacion_id' => 'autorización',
            'nombre_visitante' => 'nombre del visitante',
            'cantidad_personas' => 'cantidad de personas',
            'unidad_id' => 'unidad',
            'zona_id' => 'zona de ingreso',
        ];
    }

    public function messages(): array
    {
        return [
            'placa.required_if' => 'La placa es obligatoria para un ingreso vehicular.',
            'placa.regex' => Placa::MENSAJE_FORMATO,
            'nombre_visitante.required_without' => 'Escriba el nombre del visitante o elija una autorización.',
            'documento.required_without' => 'Escriba el documento del visitante o elija una autorización.',
            'zona_id.exists' => 'Elija una zona de ingreso activa.',
        ];
    }
}
