<?php

namespace App\Modules\Porteria\Infrastructure\Database\Factories;

use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Autorizacion> */
class AutorizacionFactory extends Factory
{
    protected $model = Autorizacion::class;

    public function definition(): array
    {
        $desde = now()->subHours(fake()->numberBetween(0, 12))->startOfHour();

        return [
            'unidad_id' => Unidad::factory(),
            'residente_id' => Residente::factory(),
            'visitante_nombre' => fake('es_ES')->name(),
            'visitante_documento' => fake()->numerify('##########'),
            'placa' => fake()->optional(0.3)->bothify('???###'),
            'valida_desde' => $desde,
            'valida_hasta' => $desde->copy()->addHours(fake()->numberBetween(4, 48)),
            'estado' => EstadoAutorizacion::Vigente,
            'usada_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Autorizacion $autorizacion) {
            if ($autorizacion->placa) {
                $autorizacion->placa = strtoupper($autorizacion->placa);
            }
        });
    }

    public function paraResidente(Residente $residente, Unidad $unidad): static
    {
        return $this->state(fn () => ['residente_id' => $residente->id, 'unidad_id' => $unidad->id]);
    }

    public function usada(): static
    {
        return $this->state(function (array $atributos) {
            $desde = now()->subDays(fake()->numberBetween(1, 25))->setTime(fake()->numberBetween(8, 19), 0);

            return [
                'valida_desde' => $desde,
                'valida_hasta' => $desde->copy()->addHours(6),
                'estado' => EstadoAutorizacion::Usada,
                'usada_at' => $desde->copy()->addMinutes(fake()->numberBetween(10, 300)),
            ];
        });
    }

    public function expirada(): static
    {
        return $this->state(function () {
            $desde = now()->subDays(fake()->numberBetween(3, 10))->setTime(9, 0);

            return [
                'valida_desde' => $desde,
                'valida_hasta' => $desde->copy()->addHours(8),
                'estado' => EstadoAutorizacion::Expirada,
            ];
        });
    }

    public function anulada(): static
    {
        return $this->state(fn () => ['estado' => EstadoAutorizacion::Anulada]);
    }
}
