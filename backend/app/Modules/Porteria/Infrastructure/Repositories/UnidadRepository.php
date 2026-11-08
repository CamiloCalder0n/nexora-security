<?php

namespace App\Modules\Porteria\Infrastructure\Repositories;

use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Database\Eloquent\Collection;

class UnidadRepository
{
    public function todas(): Collection
    {
        return Unidad::query()->orderBy('torre')->orderBy('numero')->get();
    }

    public function contar(): int
    {
        return Unidad::count();
    }
}
