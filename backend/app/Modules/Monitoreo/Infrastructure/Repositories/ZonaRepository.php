<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\Models\Zona;

class ZonaRepository
{
    public function conFranjas(int $id): Zona
    {
        return Zona::with('franjas')->findOrFail($id);
    }
}
