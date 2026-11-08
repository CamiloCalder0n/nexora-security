<?php

namespace App\Modules\Alertas\Application;

use App\Modules\Monitoreo\Application\EventoService;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use Illuminate\Support\Facades\Log;

/**
 * Decide qué pasa con un evento del agente: fuera de franja, explicado por portería,
 * agrupado en una alerta abierta o una alerta nueva.
 */
class CorrelacionService
{
    public function __construct(
        private readonly EventoService $eventos,
        private readonly MotorReglas $motor,
        private readonly AlertaService $alertas,
    ) {}

    public function correlacionar(string $eventoId): void
    {
        $evento = $this->eventos->buscar($eventoId);

        // Si el job se reintenta, un evento ya resuelto no se vuelve a procesar.
        if ($evento === null || $evento->resultado !== ResultadoEvento::Pendiente) {
            return;
        }

        $zona = $this->eventos->zonaDe($evento);

        if (! $zona->estaVigilada($evento->fecha_hora)) {
            $this->eventos->marcar($evento, ResultadoEvento::FueraFranja);

            return;
        }

        $decision = $this->motor->evaluar($evento, $zona);

        if ($decision === null) {
            // Ninguna regla aplica a este tipo de objeto: es un problema de configuración, no algo explicado.
            Log::warning('Ninguna regla decidió sobre el evento; queda pendiente', ['evento' => $evento->id]);

            return;
        }

        [$regla, $resultado] = $decision;

        if ($resultado->explicado) {
            $this->eventos->marcar($evento, ResultadoEvento::Explicado, [
                'explicado' => true,
                'registro_id' => $resultado->registroId,
            ]);

            return;
        }

        $this->alertas->crearOAgrupar($evento, $zona, $regla->nombre(), $resultado);
    }
}
