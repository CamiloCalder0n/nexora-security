<?php

namespace App\Modules\Porteria\Infrastructure\Repositories;

use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AutorizacionRepository
{
    /**
     * Filtros: search (visitante, documento o placa), estado, unidad_id,
     * unidad_ids (restringe a esas unidades) y vigentes (bool).
     */
    public function paginar(array $filtros = [], int $porPagina = 10): LengthAwarePaginator
    {
        return $this->consulta($filtros)
            ->with(['unidad', 'residente'])
            ->orderByDesc('valida_desde')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function buscar(int $id): Autorizacion
    {
        return Autorizacion::query()->with('unidad')->findOrFail($id);
    }

    public function vigentesDeUnidad(int $unidadId, ?CarbonInterface $momento = null): Collection
    {
        $momento ??= now();

        return Autorizacion::query()
            ->vigentes($momento)
            ->where('unidad_id', $unidadId)
            ->where('valida_desde', '<=', $momento)
            ->orderBy('valida_hasta')
            ->get();
    }

    public function crear(array $datos): Autorizacion
    {
        return Autorizacion::create($datos);
    }

    public function actualizar(Autorizacion $autorizacion, array $datos): Autorizacion
    {
        $autorizacion->update($datos);

        return $autorizacion;
    }

    /** Escribe directo en la tabla: sirve aunque el modelo quedó sucio tras un rollback. */
    public function cambiarEstado(Autorizacion $autorizacion, EstadoAutorizacion $estado): void
    {
        Autorizacion::query()->whereKey($autorizacion->getKey())->update(['estado' => $estado, 'updated_at' => now()]);
        $autorizacion->setAttribute('estado', $estado)->syncOriginalAttribute('estado');
    }

    public function bloquear(Autorizacion $autorizacion): Autorizacion
    {
        return Autorizacion::query()->lockForUpdate()->findOrFail($autorizacion->getKey());
    }

    public function expirarVencidas(CarbonInterface $momento): int
    {
        return Autorizacion::query()
            ->where('estado', EstadoAutorizacion::Vigente)
            ->where('valida_hasta', '<', $momento)
            ->update(['estado' => EstadoAutorizacion::Expirada, 'updated_at' => $momento]);
    }

    private function consulta(array $filtros): Builder
    {
        return Autorizacion::query()
            ->when($filtros['search'] ?? null, function (Builder $q, string $texto) {
                $like = '%'.mb_strtolower($texto).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(visitante_nombre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(visitante_documento) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(placa) LIKE ?', [$like]));
            })
            ->when($filtros['estado'] ?? null, fn (Builder $q, $estado) => $q->where('estado', $estado))
            ->when($filtros['unidad_id'] ?? null, fn (Builder $q, $id) => $q->where('unidad_id', $id))
            ->when(array_key_exists('unidad_ids', $filtros), fn (Builder $q) => $q->whereIn('unidad_id', $filtros['unidad_ids']))
            ->when($filtros['vigentes'] ?? false, fn (Builder $q) => $q->vigentes()->where('valida_desde', '<=', now()));
    }
}
