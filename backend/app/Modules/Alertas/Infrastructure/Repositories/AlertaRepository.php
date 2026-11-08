<?php

namespace App\Modules\Alertas\Infrastructure\Repositories;

use App\Modules\Alertas\Domain\EstadoAlerta;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\TipoAlerta;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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

    /** Abiertas de la prioridad dada que llevan sin atender (ni escalar) desde antes de `$limite`. */
    public function paraEscalar(int $prioridad, CarbonInterface $limite): Collection
    {
        return Alerta::where('estado', EstadoAlerta::Abierta)
            ->where('prioridad', $prioridad)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('fecha_escalamiento')->where('fecha_hora', '<=', $limite))
                ->orWhere('fecha_escalamiento', '<=', $limite))
            ->get();
    }

    /**
     * ¿Ya hay una alerta sin cerrar por esta misma caída? Una alerta creada antes del último
     * heartbeat es de una caída anterior (la cámara volvió después), así que no cuenta.
     */
    public function camaraSinSenalDesde(int $camaraId, CarbonInterface $ultimaConexion): bool
    {
        return Alerta::where('tipo', TipoAlerta::CamaraSinSenal)
            ->where('camara.camara_id', $camaraId)
            ->where('estado', '!=', EstadoAlerta::Cerrada)
            ->where('fecha_hora', '>=', $ultimaConexion)
            ->exists();
    }

    public function buscar(string $id): ?Alerta
    {
        return Alerta::find($id);
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
