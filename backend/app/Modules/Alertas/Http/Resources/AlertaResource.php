<?php

namespace App\Modules\Alertas\Http\Resources;

use App\Modules\Alertas\Domain\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una alerta para el panel. Sin rutas de evidencia: la imagen vive en el PC del conjunto y
 * no sale de ahí (HU27).
 *
 * @mixin Alerta
 */
class AlertaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'estado' => $this->estado->value,
            'prioridad' => $this->prioridad,
            'nivel_escalamiento' => $this->nivel_escalamiento,
            'motivo' => $this->motivo,
            'zona' => $this->zona['nombre'] ?? null,
            'camara' => $this->camara['nombre'] ?? null,
            'num_eventos' => $this->num_eventos,
            'fecha_hora' => $this->fecha_hora?->toIso8601String(),
            'fecha_ultimo_evento' => $this->fecha_ultimo_evento?->toIso8601String(),
            'atendida_por' => $this->atendida_por['nombre'] ?? null,
            'fecha_atencion' => $this->fecha_atencion?->toIso8601String(),
            'clasificacion' => $this->clasificacion?->value,
            'fecha_cierre' => $this->fecha_cierre?->toIso8601String(),
        ];
    }
}
