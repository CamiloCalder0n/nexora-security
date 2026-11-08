<?php

namespace App\Modules\Monitoreo\Http\Resources;

use App\Modules\Monitoreo\Domain\Models\Franja;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Franja */
class FranjaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dia_semana' => $this->dia_semana,
            'hora_inicio' => substr($this->hora_inicio, 0, 5),
            'hora_fin' => substr($this->hora_fin, 0, 5),
        ];
    }
}
