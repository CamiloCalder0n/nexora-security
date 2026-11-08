<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use App\Modules\Monitoreo\Infrastructure\Repositories\EventoRepository;
use App\Modules\Monitoreo\Infrastructure\Repositories\ZonaRepository;

/** Lo que otros módulos (Alertas) pueden hacer con los eventos y sus zonas, sin tocar los modelos directo. */
class EventoService
{
    public function __construct(
        private readonly EventoRepository $eventos,
        private readonly ZonaRepository $zonas,
    ) {}

    public function buscar(string $id): ?Evento
    {
        return $this->eventos->buscar($id);
    }

    public function zonaDe(Evento $evento): Zona
    {
        return $this->zonas->conFranjas($evento->zona['zona_id']);
    }

    public function marcar(Evento $evento, ResultadoEvento $resultado, array $extra = []): void
    {
        $this->eventos->marcar($evento, $resultado, $extra);
    }
}
