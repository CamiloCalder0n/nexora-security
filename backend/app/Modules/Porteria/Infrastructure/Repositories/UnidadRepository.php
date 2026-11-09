<?php

namespace App\Modules\Porteria\Infrastructure\Repositories;

use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Database\Eloquent\Collection;

class UnidadRepository
{
    public function todas(): Collection
    {
        return Unidad::query()->orderBy('torre')->orderBy('numero')->get();
    }

    public function deResidente(Residente $residente): Collection
    {
        return $residente->unidades()->orderBy('torre')->orderBy('numero')->get();
    }

    /** Todas las unidades con sus residentes cargados. */
    public function todasConResidentes(): Collection
    {
        return Unidad::query()->with('residentes')->orderBy('torre')->orderBy('numero')->get();
    }

    public function contar(): int
    {
        return Unidad::count();
    }
}
