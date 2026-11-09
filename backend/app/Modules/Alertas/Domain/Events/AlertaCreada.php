<?php

namespace App\Modules\Alertas\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Hay una alerta nueva que notificar (la Guía 03 la conecta a Telegram). */
class AlertaCreada
{
    use Dispatchable;

    public function __construct(public readonly string $alertaId) {}
}
