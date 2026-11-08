<?php

namespace App\Modules\Identidad\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Forma del usuario en la respuesta de login (docs/CONTRATO_API_AGENTE.md). */
class SesionUsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'rol' => $this->rol->value,
        ];
    }
}
