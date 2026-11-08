<?php

namespace App\Modules\Porteria\Infrastructure\Repositories;

use App\Modules\Porteria\Application\Contracts\ConsultaRegistrosPorteria;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RegistroPorteriaRepository implements ConsultaRegistrosPorteria
{
    /**
     * Filtros: search (visitante, documento o placa), desde y hasta (fechas Y-m-d),
     * unidad_id, tipo_ingreso y unidad_ids (restringe a esas unidades).
     */
    public function paginar(array $filtros = [], int $porPagina = 10): LengthAwarePaginator
    {
        return RegistroPorteria::query()
            ->with(['unidad', 'zona', 'vigilante'])
            ->when($filtros['search'] ?? null, function (Builder $q, string $texto) {
                $like = '%'.mb_strtolower($texto).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(nombre_visitante) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(documento) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(placa) LIKE ?', [$like]));
            })
            ->when($filtros['desde'] ?? null, fn (Builder $q, $d) => $q->where('fecha_ingreso', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $h) => $q->where('fecha_ingreso', '<=', Carbon::parse($h)->endOfDay()))
            ->when($filtros['unidad_id'] ?? null, fn (Builder $q, $id) => $q->where('unidad_id', $id))
            ->when($filtros['tipo_ingreso'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo_ingreso', $tipo))
            ->when(array_key_exists('unidad_ids', $filtros), fn (Builder $q) => $q->whereIn('unidad_id', $filtros['unidad_ids']))
            ->orderByDesc('fecha_ingreso')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function crear(array $datos): RegistroPorteria
    {
        return RegistroPorteria::create($datos);
    }

    public function marcarSalida(RegistroPorteria $registro, CarbonInterface $momento): RegistroPorteria
    {
        $registro->update(['fecha_salida' => $momento]);

        return $registro;
    }

    /** Ingresos por día desde $desde, como [Y-m-d => total]. */
    public function ingresosPorDia(CarbonInterface $desde): array
    {
        return RegistroPorteria::query()
            ->where('fecha_ingreso', '>=', $desde)
            ->get(['fecha_ingreso'])
            ->countBy(fn (RegistroPorteria $r) => $r->fecha_ingreso->timezone(config('app.timezone'))->toDateString())
            ->all();
    }

    public function buscarParaCorrelacion(int $zonaId, CarbonInterface $momento, int $ventanaMin, int $cantidad): ?RegistroPorteria
    {
        return RegistroPorteria::query()
            ->where('zona_id', $zonaId)
            ->whereBetween('fecha_ingreso', [
                $momento->copy()->subMinutes($ventanaMin),
                $momento->copy()->addMinutes($ventanaMin),
            ])
            ->where('cantidad_personas', '>=', $cantidad)
            ->get()
            ->sortBy(fn (RegistroPorteria $r) => abs($r->fecha_ingreso->diffInSeconds($momento, false)))
            ->first();
    }
}
