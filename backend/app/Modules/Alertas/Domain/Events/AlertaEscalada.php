<?php

namespace App\Modules\Alertas\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Una alerta de prioridad alta lleva demasiado sin atender (la Guía 03 avisa a la administradora). */
class AlertaEscalada
{
    use Dispatchable;

    public function __construct(public readonly string $alertaId, public readonly int $nivel) {}
}
