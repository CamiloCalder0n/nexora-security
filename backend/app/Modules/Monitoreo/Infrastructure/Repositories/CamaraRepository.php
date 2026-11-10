<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Models\Camara;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

    /** @return Collection<int, Camara> cámaras activas cuyo último heartbeat es anterior a `$limite` */
    public function sinHeartbeatDesde(CarbonInterface $limite): Collection
    {
        return Camara::with('zona')
            ->where('estado', EstadoCamara::Activa)
            ->whereNotNull('ultima_conexion')
            ->where('ultima_conexion', '<', $limite)
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

    /** @return list<int> los ids de la lista que no existen */
    public function idsInexistentes(array $ids): array
    {
        return array_values(array_diff($ids, Camara::whereIn('id', $ids)->pluck('id')->all()));
    }

    /**
     * Deja activas, con señal de ahora, solo las cámaras de `$ids` y las demás inactivas: así las que
     * no tienen agente no disparan "sin señal". Devuelve las activadas y cuántas quedaron inactivas.
     *
     * @return array{0: Collection<int, Camara>, 1: int}
     */
    public function activarSolo(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            Camara::whereIn('id', $ids)->update(['estado' => EstadoCamara::Activa, 'ultima_conexion' => now()]);
            $inactivas = Camara::whereNotIn('id', $ids)->update(['estado' => EstadoCamara::Inactiva]);

            return [Camara::whereIn('id', $ids)->orderBy('id')->get(), $inactivas];
        });
    }
}
