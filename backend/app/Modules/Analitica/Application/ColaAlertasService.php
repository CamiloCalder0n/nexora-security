<?php

namespace App\Modules\Analitica\Application;

use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Domain\Models\Alerta;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * La cola del panel lee por el mismo caso de uso que GET /api/v1/alertas, y Atender y Cerrar
 * van a POST /api/v1/alertas/{id}/atender y /cerrar con la sesión del panel.
 */
class ColaAlertasService
{
    public const LIMITE = 100;  // el mismo tope que pone AlertaService a la cola

    public function __construct(private readonly AlertaService $alertas) {}

    /** @return ?list<array> null si MongoDB no responde */
    public function pendientes(): ?array
    {
        try {
            return $this->alertas->activas()->map(fn (Alerta $alerta) => [
                'id' => $alerta->id,
                'prioridad' => (int) $alerta->prioridad,
                'zona' => $alerta->zona['nombre'] ?? '—',
                'camara' => $alerta->camara['nombre'] ?? null,
                'motivo' => $alerta->motivo,
                'fecha_hora' => $alerta->fecha_hora->toImmutable(),
                'num_eventos' => (int) ($alerta->num_eventos ?? 0),
                'estado' => $alerta->estado->value,
                'atendida_por' => $alerta->atendida_por['nombre'] ?? null,
            ])->values()->all();
        } catch (Throwable $e) {
            Log::warning('Cola de alertas: no se pudo leer MongoDB', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
