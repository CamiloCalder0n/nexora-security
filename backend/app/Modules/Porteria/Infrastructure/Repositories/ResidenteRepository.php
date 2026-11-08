<?php

namespace App\Modules\Porteria\Infrastructure\Repositories;

use App\Modules\Porteria\Domain\Models\Residente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ResidenteRepository
{
    /** Filtros: search (nombre, documento o teléfono), tipo_residente y unidad_id. */
    public function paginar(array $filtros = [], int $porPagina = 10): LengthAwarePaginator
    {
        return Residente::query()
            ->with(['unidades', 'user'])
            ->when($filtros['search'] ?? null, function (Builder $q, string $texto) {
                $like = '%'.mb_strtolower($texto).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(nombre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(documento) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(telefono) LIKE ?', [$like]));
            })
            ->when($filtros['tipo_residente'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo_residente', $tipo))
            ->when($filtros['unidad_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('unidades', fn (Builder $u) => $u->whereKey($id)))
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function buscar(int $id): Residente
    {
        return Residente::query()->with('unidades')->findOrFail($id);
    }

    /** @param array<int, bool> $unidades unidad_id => es_titular */
    public function crear(array $datos, array $unidades): Residente
    {
        return DB::transaction(function () use ($datos, $unidades) {
            $residente = Residente::create($datos);
            $residente->unidades()->sync($this->pivote($unidades));

            return $residente;
        });
    }

    /** @param array<int, bool>|null $unidades unidad_id => es_titular */
    public function actualizar(Residente $residente, array $datos, ?array $unidades): Residente
    {
        return DB::transaction(function () use ($residente, $datos, $unidades) {
            $residente->update($datos);

            if ($unidades !== null) {
                $residente->unidades()->sync($this->pivote($unidades));
            }

            return $residente;
        });
    }

    public function perteneceUnidad(Residente $residente, int $unidadId): bool
    {
        return $residente->unidades()->whereKey($unidadId)->exists();
    }

    /** @return list<int> */
    public function idsUnidades(Residente $residente): array
    {
        return $residente->unidades()->pluck('unidades.id')->all();
    }

    public function tieneAutorizaciones(Residente $residente): bool
    {
        return $residente->autorizaciones()->exists();
    }

    public function eliminar(Residente $residente): void
    {
        $residente->delete();
    }

    public function contar(): int
    {
        return Residente::count();
    }

    private function pivote(array $unidades): array
    {
        return collect($unidades)->mapWithKeys(fn ($titular, $id) => [(int) $id => ['es_titular' => (bool) $titular]])->all();
    }
}
