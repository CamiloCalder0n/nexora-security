<?php

namespace App\Modules\Alertas\Infrastructure\Listeners;

use App\Modules\Alertas\Application\CorrelacionService;
use App\Modules\Monitoreo\Domain\Events\EventoPendienteReencolado;
use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Escucha EventoRegistrado (y su reencolado) y correlaciona en el worker (la ingesta ya respondió 202). */
class CorrelacionJob implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(private readonly CorrelacionService $correlacion) {}

    public function handle(EventoRegistrado|EventoPendienteReencolado $evento): void
    {
        $this->correlacion->correlacionar($evento->eventoId);
    }
}
