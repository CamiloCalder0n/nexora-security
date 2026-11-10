<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Models\Camara;
use Carbon\CarbonInterface;
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

    /** Deja todas las cámaras activas con señal de ahora. Devuelve cuántas son. */
    public function activarTodas(): int
    {
        return Camara::query()->update(['estado' => EstadoCamara::Activa, 'ultima_conexion' => now()]);
    }
}
