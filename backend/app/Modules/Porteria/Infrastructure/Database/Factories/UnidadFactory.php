<?php

namespace App\Modules\Porteria\Infrastructure\Database\Factories;

use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unidad> */
class UnidadFactory extends Factory
{
    protected $model = Unidad::class;

    public function definition(): array
    {
        return [
            'torre' => (string) fake()->numberBetween(1, 9),
            'numero' => fake()->unique()->numerify('#0#'),
        ];
    }
}
