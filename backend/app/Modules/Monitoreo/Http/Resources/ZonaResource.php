<?php

namespace App\Modules\Monitoreo\Http\Resources;

use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Zona */
class ZonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo->value,
            'prioridad_base' => $this->prioridad_base,
            'ventana_correlacion_min' => $this->ventana_correlacion_min,
            'ventana_silencio_min' => $this->ventana_silencio_min,
            'activa' => $this->activa,
            'camaras_count' => $this->whenCounted('camaras'),
            'franjas' => FranjaResource::collection($this->whenLoaded('franjas')),
            'camaras' => CamaraResource::collection($this->whenLoaded('camaras')),
        ];
    }
}
