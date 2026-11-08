<?php

namespace App\Modules\Monitoreo\Infrastructure\Database\Factories;

use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Domain\TipoZona;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Zona> */
class ZonaFactory extends Factory
{
    protected $model = Zona::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst(fake('es_ES')->unique()->words(3, true)),
            'tipo' => fake()->randomElement(TipoZona::cases()),
            'prioridad_base' => fake()->numberBetween(1, 3),
            'ventana_correlacion_min' => 10,
            'ventana_silencio_min' => 5,
            'activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn () => ['activa' => false]);
    }
}
