<?php

namespace App\Modules\Alertas\Application\Reglas;

use App\Modules\Alertas\Domain\Reglas\ResultadoRegla;
use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Zona;

/**
 * Regla enchufable del motor de correlación (HU31). Para agregar una regla nueva basta con
 * crear la clase y ponerla en `alertas.reglas` (Modules/Alertas/config.php). Si al evento no le
 * aplica, devuelve `ResultadoRegla::sinDecision()` y el motor sigue con la siguiente.
 */
interface ReglaDeteccion
{
    public function nombre(): string;

    public function evaluar(Evento $evento, Zona $zona): ResultadoRegla;
}
