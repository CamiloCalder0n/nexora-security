<?php

namespace App\Modules\Monitoreo\Http\Resources;

use App\Modules\Monitoreo\Domain\Models\Camara;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Camara */
class CamaraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'estado' => $this->estado->value,
            'ultima_conexion' => $this->ultima_conexion?->toIso8601String(),
        ];
    }
}
