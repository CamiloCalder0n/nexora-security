<?php

namespace App\Modules\Monitoreo\Http\Resources;

use App\Modules\Monitoreo\Domain\Models\Camara;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Camara */
class CamaraAgenteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'estado' => $this->estado->value,
            'zona' => [
                'id' => $this->zona->id,
                'nombre' => $this->zona->nombre,
                'prioridad_base' => $this->zona->prioridad_base,
            ],
            'vigilada_ahora' => (bool) $this->vigilada_ahora,
        ];
    }
}
