<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ZonaRepository
{
    /** Filtros: search (nombre), tipo y activa ('1' o '0'). */
    public function paginar(array $filtros = [], int $porPagina = 10): LengthAwarePaginator
    {
        return Zona::query()
            ->with('franjas')
            ->withCount('camaras')
            ->when($filtros['search'] ?? null, fn (Builder $q, string $texto) => $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%']))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo', $tipo))
            ->when(isset($filtros['activa']) && $filtros['activa'] !== '', fn (Builder $q) => $q->where('activa', (bool) $filtros['activa']))
            ->orderByDesc('prioridad_base')
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function todas(): Collection
    {
        return Zona::query()->orderBy('nombre')->get();
    }

    public function cargarDetalle(Zona $zona): Zona
    {
        return $zona->load(['franjas', 'camaras']);
    }

    public function crear(array $datos, array $franjas): Zona
    {
        return DB::transaction(function () use ($datos, $franjas) {
            $zona = Zona::create($datos);
            $zona->franjas()->createMany($franjas);

            return $zona;
        });
    }

    public function actualizar(Zona $zona, array $datos, ?array $franjas): Zona
    {
        return DB::transaction(function () use ($zona, $datos, $franjas) {
            $zona->update($datos);

            if ($franjas !== null) {
                $zona->franjas()->delete();
                $zona->franjas()->createMany($franjas);
            }

            return $zona;
        });
    }

    public function tieneDependencias(Zona $zona): bool
    {
        return $zona->camaras()->exists()
            || DB::table('registros_porteria')->where('zona_id', $zona->id)->exists();
    }

    public function eliminar(Zona $zona): void
    {
        $zona->delete();
    }

    public function contar(): array
    {
        return ['total' => Zona::count(), 'activas' => Zona::where('activa', true)->count()];
    }
}
