<?php

namespace App\Modules\Porteria\Http\Resources;

use App\Modules\Porteria\Domain\Models\Autorizacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Autorizacion */
class AutorizacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visitante_nombre' => $this->visitante_nombre,
            'visitante_documento' => $this->visitante_documento,
            'placa' => $this->placa,
            'valida_desde' => $this->valida_desde->toIso8601String(),
            'valida_hasta' => $this->valida_hasta->toIso8601String(),
            'estado' => $this->estado->value,
            'usada_at' => $this->usada_at?->toIso8601String(),
            'unidad' => $this->whenLoaded('unidad', fn () => [
                'id' => $this->unidad->id,
                'etiqueta' => $this->unidad->etiqueta,
            ]),
            'residente' => $this->whenLoaded('residente', fn () => [
                'id' => $this->residente->id,
                'nombre' => $this->residente->nombre,
            ]),
        ];
    }
}
