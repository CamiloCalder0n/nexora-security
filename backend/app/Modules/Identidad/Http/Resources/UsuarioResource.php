<?php

namespace App\Modules\Identidad\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'email' => $this->email,
            'rol' => $this->rol->value,
            'activo' => $this->activo,
        ];
    }
}
