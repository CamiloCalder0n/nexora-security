<?php

namespace App\Modules\Monitoreo\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Un evento nuevo del agente quedó guardado en MongoDB y espera correlación (lo escucha Alertas). */
class EventoRegistrado
{
    use Dispatchable;

    public function __construct(public readonly string $eventoId) {}
}
