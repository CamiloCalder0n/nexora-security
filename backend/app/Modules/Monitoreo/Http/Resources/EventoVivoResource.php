<?php

namespace App\Modules\Monitoreo\Http\Resources;

use App\Modules\Monitoreo\Domain\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vista del evento para el panel en vivo: sin evidencia, sin vínculos a registros ni alertas, sin personas.
 *
 * @mixin Evento
 */
class EventoVivoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'hora' => $this->fecha_hora?->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
            'camara' => $this->camara['nombre'] ?? null,
            'zona' => $this->zona['nombre'] ?? null,
            'tipo' => $this->tipo_objeto,
            'cantidad' => $this->cantidad,
            'confianza' => $this->confianza === null ? null : (int) round($this->confianza * 100),
            'resultado' => $this->resultado?->value,
        ];
    }
}
