<?php

namespace App\Modules\Monitoreo\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un evento que ya existía sigue en pendiente porque su job se perdió y se vuelve a correlacionar.
 * Es distinto de EventoRegistrado para que nadie lo cuente como un evento nuevo.
 */
class EventoPendienteReencolado
{
    use Dispatchable;

    public function __construct(public readonly string $eventoId) {}
}
