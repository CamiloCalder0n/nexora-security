<?php

namespace App\Modules\Porteria\Infrastructure\Database\Factories;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\TipoResidente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Residente> */
class ResidenteFactory extends Factory
{
    protected $model = Residente::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'nombre' => fake('es_ES')->name(),
            'documento' => fake()->unique()->numerify('##########'),
            'telefono' => fake()->optional(0.8)->numerify('3#########'),
            'tipo_residente' => fake()->randomElement(TipoResidente::cases()),
        ];
    }

    public function conCuenta(): static
    {
        return $this->state(fn () => ['user_id' => User::factory()->conRol(Rol::Residente)]);
    }
}
