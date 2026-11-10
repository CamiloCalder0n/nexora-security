<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use App\Modules\Monitoreo\Infrastructure\Repositories\EventoRepository;
use App\Modules\Monitoreo\Infrastructure\Repositories\ZonaRepository;
use Illuminate\Support\Collection;

/**
 * Lo que otros módulos (Alertas) pueden hacer con los eventos y sus zonas. Alertas lee el evento
 * como modelo de lectura y solo lo cambia con estos métodos, cada uno con su resultado.
 */
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

    /** @return Collection<int, Evento> */
    public function recientes(int $limite = 50): Collection
    {
        return $this->eventos->recientes($limite);
    }

    public function zonaDe(Evento $evento): Zona
    {
        return $this->zonas->conFranjas($evento->zona['zona_id']);
    }

    /** Relee el evento: otro job pudo resolverlo mientras este esperaba. */
    public function siguePendiente(Evento $evento): bool
    {
        return $evento->refresh()->resultado === ResultadoEvento::Pendiente;
    }

    public function marcarFueraFranja(Evento $evento): void
    {
        $this->eventos->marcar($evento, ResultadoEvento::FueraFranja);
    }

    public function marcarExplicado(Evento $evento, int $registroId): void
    {
        $this->eventos->marcar($evento, ResultadoEvento::Explicado, ['explicado' => true, 'registro_id' => $registroId]);
    }

    /** El evento abrió la alerta. */
    public function marcarAlerta(Evento $evento, string $alertaId): void
    {
        $this->eventos->marcar($evento, ResultadoEvento::Alerta, ['alerta_id' => $alertaId]);
    }

    /** El evento se agregó a una alerta que ya estaba abierta, sin volver a avisar (HU04). */
    public function marcarSuprimido(Evento $evento, string $alertaId): void
    {
        $this->eventos->marcar($evento, ResultadoEvento::Suprimido, ['alerta_id' => $alertaId]);
    }
}
