<?php

namespace App\Modules\Monitoreo\Infrastructure\Database\Factories;

use App\Modules\Monitoreo\Domain\Models\Franja;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Franja> */
class FranjaFactory extends Factory
{
    protected $model = Franja::class;

    public function definition(): array
    {
        return [
            'zona_id' => Zona::factory(),
            'dia_semana' => null,
            'hora_inicio' => '18:00:00',
            'hora_fin' => '06:00:00',
        ];
    }

    public function todoElDia(): static
    {
        return $this->state(fn () => ['hora_inicio' => '00:00:00', 'hora_fin' => '00:00:00']);
    }

    public function entre(string $inicio, string $fin): static
    {
        return $this->state(fn () => ['hora_inicio' => $inicio, 'hora_fin' => $fin]);
    }
}
