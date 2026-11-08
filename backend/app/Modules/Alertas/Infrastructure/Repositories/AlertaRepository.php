<?php

namespace App\Modules\Alertas\Infrastructure\Repositories;

use App\Modules\Alertas\Domain\EstadoAlerta;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\TipoAlerta;
use Carbon\CarbonInterface;

class AlertaRepository
{
    /**
     * Alerta sin_registro abierta de la zona cuyo periodo [primer evento, último evento], ampliado
     * `$silencioMin` a cada lado, contiene `$momento`. Acotar por los dos lados importa porque el
     * agente puede mandar eventos viejos al vaciar su cola tras un corte.
     */
    public function abiertaQueCubre(int $zonaId, CarbonInterface $momento, int $silencioMin): ?Alerta
    {
        return Alerta::where('zona.zona_id', $zonaId)
            ->where('tipo', TipoAlerta::SinRegistro)
            ->where('estado', EstadoAlerta::Abierta)
            ->where('fecha_hora', '<=', $momento->copy()->addMinutes($silencioMin))
            ->where('fecha_ultimo_evento', '>=', $momento->copy()->subMinutes($silencioMin))
            ->orderBy('fecha_ultimo_evento', 'desc')
            ->first();
    }

    /** La alerta que ya contiene este evento, en cualquier estado (reintentos del job). */
    public function conEvento(string $eventoId): ?Alerta
    {
        return Alerta::where('eventos_ids', $eventoId)->first();
    }

    public function crear(array $datos): Alerta
    {
        return Alerta::create($datos);
    }

    public function guardar(Alerta $alerta): void
    {
        $alerta->save();
    }
}
