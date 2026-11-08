<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Models\Camara;
use Illuminate\Database\Eloquent\Collection;

class CamaraRepository
{
    /** @return Collection<int, Camara> */
    public function vigilables(): Collection
    {
        return Camara::with('zona.franjas')
            ->where('estado', '!=', EstadoCamara::Inactiva)
            ->orderBy('id')
            ->get();
    }

    public function conZona(int $id): Camara
    {
        return Camara::with('zona')->findOrFail($id);
    }

    public function guardar(Camara $camara): void
    {
        $camara->save();
    }
}
