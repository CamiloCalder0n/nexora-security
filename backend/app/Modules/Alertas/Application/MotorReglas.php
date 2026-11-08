<?php

namespace App\Modules\Alertas\Application;

use App\Modules\Alertas\Domain\Reglas\ReglaDeteccion;
use App\Modules\Alertas\Domain\Reglas\ResultadoRegla;
use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Zona;

/** Recorre las reglas de `alertas.reglas` en orden; decide la primera que tenga algo que decir. */
class MotorReglas
{
    /** @param iterable<ReglaDeteccion> $reglas */
    public function __construct(private readonly iterable $reglas) {}

    /** @return array{0: ReglaDeteccion, 1: ResultadoRegla}|null */
    public function evaluar(Evento $evento, Zona $zona): ?array
    {
        foreach ($this->reglas as $regla) {
            if (! $regla->aplica($evento)) {
                continue;
            }
            $resultado = $regla->evaluar($evento, $zona);
            if ($resultado->decide) {
                return [$regla, $resultado];
            }
        }

        return null;
    }
}
