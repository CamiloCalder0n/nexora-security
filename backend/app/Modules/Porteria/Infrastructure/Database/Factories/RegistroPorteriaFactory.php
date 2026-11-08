<?php

namespace App\Modules\Porteria\Infrastructure\Database\Factories;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Unidad;
use App\Modules\Porteria\Domain\TipoIngreso;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RegistroPorteria> */
class RegistroPorteriaFactory extends Factory
{
    protected $model = RegistroPorteria::class;

    public function definition(): array
    {
        $tipo = fake()->randomElement(TipoIngreso::cases());
        $ingreso = now()->subMinutes(fake()->numberBetween(30, 30 * 24 * 60));
        $salida = $ingreso->copy()->addMinutes(fake()->numberBetween(10, 240));

        return [
            'tipo_ingreso' => $tipo,
            'nombre_visitante' => fake('es_ES')->name(),
            'documento' => fake()->numerify('##########'),
            'placa' => $tipo === TipoIngreso::Vehiculo ? strtoupper(fake()->bothify('???###')) : null,
            'cantidad_personas' => $tipo === TipoIngreso::Domiciliario ? 1 : fake()->numberBetween(1, 4),
            'unidad_id' => Unidad::factory(),
            'zona_id' => Zona::factory(),
            'registrado_por' => User::factory()->conRol(Rol::Vigilante),
            'autorizacion_id' => null,
            'fecha_ingreso' => $ingreso,
            'fecha_salida' => $salida->isPast() ? $salida : null,
        ];
    }

    public function sinSalida(): static
    {
        return $this->state(fn () => ['fecha_salida' => null]);
    }
}
