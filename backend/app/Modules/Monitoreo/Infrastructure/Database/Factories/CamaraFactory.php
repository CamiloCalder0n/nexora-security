<?php

namespace App\Modules\Monitoreo\Infrastructure\Database\Factories;

use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Camara> */
class CamaraFactory extends Factory
{
    protected $model = Camara::class;

    public function definition(): array
    {
        return [
            'zona_id' => Zona::factory(),
            'nombre' => 'CAM-'.fake()->unique()->numerify('###'),
            'url_stream' => null,
            'estado' => EstadoCamara::Activa,
            'ultima_conexion' => null,
        ];
    }
}
