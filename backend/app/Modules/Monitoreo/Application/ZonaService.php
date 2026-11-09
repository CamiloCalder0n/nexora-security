<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\Exceptions\ZonaEnUso;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Infrastructure\Repositories\ZonaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class ZonaService
{
    public function __construct(private readonly ZonaRepository $zonas) {}

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        return $this->zonas->paginar($filtros);
    }

    public function todas(): Collection
    {
        return $this->zonas->todas();
    }

    public function detalle(Zona $zona): Zona
    {
        return $this->zonas->cargarDetalle($zona);
    }

    public function crear(array $datos): Zona
    {
        $zona = $this->zonas->crear(Arr::except($datos, 'franjas'), $datos['franjas'] ?? []);

        return $this->zonas->cargarDetalle($zona);
    }

    public function actualizar(Zona $zona, array $datos): Zona
    {
        $zona = $this->zonas->actualizar($zona, Arr::except($datos, 'franjas'), $datos['franjas'] ?? null);

        return $this->zonas->cargarDetalle($zona);
    }

    /** Una zona con cámaras o historial no se borra: se desactiva. */
    public function eliminar(Zona $zona): void
    {
        if ($this->zonas->tieneDependencias($zona)) {
            throw new ZonaEnUso;
        }

        $this->zonas->eliminar($zona);
    }

    public function totales(): array
    {
        return $this->zonas->contar();
    }
}
