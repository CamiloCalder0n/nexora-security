<?php

namespace App\Modules\Porteria\Application;

use App\Modules\Porteria\Domain\Exceptions\ResidenteConHistorial;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Infrastructure\Repositories\ResidenteRepository;
use App\Modules\Porteria\Infrastructure\Repositories\UnidadRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ResidenteService
{
    public function __construct(
        private readonly ResidenteRepository $residentes,
        private readonly UnidadRepository $unidades,
    ) {}

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        return $this->residentes->paginar($filtros);
    }

    public function buscar(int $id): Residente
    {
        return $this->residentes->buscar($id);
    }

    public function unidades(): Collection
    {
        return $this->unidades->todas();
    }

    /**
     * $datos['unidades'] = [unidad_id, ...] y opcional $datos['titular_de'] = [unidad_id, ...].
     * Un residente siempre queda con al menos una unidad.
     */
    public function crear(array $datos): Residente
    {
        return $this->residentes->crear(Arr::except($datos, ['unidades', 'titular_de']), $this->unidadesDesde($datos));
    }

    public function actualizar(Residente $residente, array $datos): Residente
    {
        $unidades = array_key_exists('unidades', $datos) ? $this->unidadesDesde($datos) : null;

        return $this->residentes->actualizar($residente, Arr::except($datos, ['unidades', 'titular_de']), $unidades);
    }

    public function eliminar(Residente $residente): void
    {
        if ($this->residentes->tieneAutorizaciones($residente)) {
            throw new ResidenteConHistorial;
        }

        $this->residentes->eliminar($residente);
    }

    public function totales(): array
    {
        return ['residentes' => $this->residentes->contar(), 'unidades' => $this->unidades->contar()];
    }

    private function unidadesDesde(array $datos): array
    {
        $ids = array_map('intval', $datos['unidades'] ?? []);

        if ($ids === []) {
            throw ValidationException::withMessages(['unidades' => 'El residente debe tener al menos una unidad.']);
        }

        $titular = array_map('intval', $datos['titular_de'] ?? []);

        return collect($ids)->mapWithKeys(fn (int $id) => [$id => in_array($id, $titular, true)])->all();
    }
}
