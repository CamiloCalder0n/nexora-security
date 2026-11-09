<?php

namespace App\Modules\Porteria\Http\Requests;

use App\Modules\Porteria\Domain\TipoIngreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngresoRequest extends FormRequest
{
    /** Carros (ABC123) y motos (ABC12D o ABC12). */
    public const FORMATO_PLACA = '/^[A-Z]{3}\d{2}[A-Z0-9]?$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('placa'))) {
            $placa = mb_strtoupper(preg_replace('/[\s-]+/', '', $this->input('placa')));
            $this->merge(['placa' => $placa === '' ? null : $placa]);
        }
    }

    public function rules(): array
    {
        return [
            'tipo_ingreso' => ['required', Rule::enum(TipoIngreso::class)],
            'autorizacion_id' => ['nullable', 'integer', 'exists:autorizaciones,id'],
            'nombre_visitante' => ['required_without:autorizacion_id', 'nullable', 'string', 'max:120'],
            'documento' => ['required_without:autorizacion_id', 'nullable', 'string', 'max:20'],
            'placa' => ['required_if:tipo_ingreso,'.TipoIngreso::Vehiculo->value, 'nullable', 'string', 'max:10', 'regex:'.self::FORMATO_PLACA],
            'cantidad_personas' => ['required', 'integer', 'between:1,20'],
            'unidad_id' => ['required', 'integer', 'exists:unidades,id'],
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
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
            'placa.regex' => 'La placa debe tener el formato ABC123 (carro) o ABC12D (moto).',
        ];
    }
}
