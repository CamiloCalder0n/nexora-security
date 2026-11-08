<?php

namespace App\Modules\Alertas\Domain\Reglas;

use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Zona;

/**
 * Regla enchufable del motor de correlación (HU31). Para agregar una regla nueva basta con
 * crear la clase y ponerla en `alertas.reglas` (Modules/Alertas/config.php).
 */
interface ReglaDeteccion
{
    public function nombre(): string;

    public function aplica(Evento $evento): bool;

    public function evaluar(Evento $evento, Zona $zona): ResultadoRegla;
}
