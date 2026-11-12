<?php

namespace App\Modules\Identidad\Infrastructure\Repositories;

use App\Modules\Identidad\Domain\Models\AccesoAuditado;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AuditoriaRepository
{
    public function crear(array $datos): AccesoAuditado
    {
        return AccesoAuditado::create($datos);
    }

    /** Filtros: search (nombre del usuario o motivo), recurso, desde y hasta (Y-m-d). */
    public function paginar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return $this->consulta($filtros)->paginate($porPagina)->withQueryString();
    }

    public function buscar(array $filtros, int $limite): Collection
    {
        return $this->consulta($filtros)->limit($limite)->get();
    }

    private function consulta(array $filtros): Builder
    {
        return AccesoAuditado::query()
            ->with('usuario:id,name,email,rol')
            ->when($filtros['search'] ?? null, function (Builder $q, string $texto) {
                $like = '%'.mb_strtolower($texto).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(motivo) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(recurso_id) LIKE ?', [$like])
                    ->orWhereHas('usuario', fn (Builder $u) => $u->whereRaw('LOWER(name) LIKE ?', [$like])));
            })
            ->when($filtros['recurso'] ?? null, fn (Builder $q, $recurso) => $q->where('recurso', $recurso))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q->where('created_at', '>=', Carbon::parse($desde)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q->where('created_at', '<=', Carbon::parse($hasta)->endOfDay()))
            ->latest('created_at')
            ->latest('id');
    }

    public function contarDesde(DateTimeInterface $desde): int
    {
        return AccesoAuditado::query()->where('created_at', '>=', $desde)->count();
    }
}
