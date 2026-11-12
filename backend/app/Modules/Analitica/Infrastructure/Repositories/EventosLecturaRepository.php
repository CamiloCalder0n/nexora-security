<?php

namespace App\Modules\Analitica\Infrastructure\Repositories;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

/**
 * Lectura de la colección "eventos" que escribe Monitoreo. Solo consulta, igual que AlertasLecturaRepository.
 * La ruta de la evidencia no sale en el historial: se ve solo con motivo y queda auditada (RF007).
 */
class EventosLecturaRepository
{
    private const TIPOS = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];

    private const CAMPOS_LISTADO = [
        'uuid' => 1, 'fecha_hora' => 1, 'tipo_objeto' => 1, 'cantidad' => 1, 'confianza' => 1,
        'resultado' => 1, 'camara' => 1, 'zona' => 1, 'alerta_id' => 1, 'registro_id' => 1,
        'tiene_evidencia' => ['$eq' => [['$type' => '$ruta_evidencia'], 'string']],
    ];

    /** Filtros: desde, hasta (Y-m-d en la zona horaria de la app), zona_id, tipo_objeto, resultado y orden. */
    public function historial(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        $coleccion = $this->coleccion();
        $filtro = $this->filtro($filtros);
        $pagina = Paginator::resolveCurrentPage();
        $orden = ($filtros['orden'] ?? 'recientes') === 'antiguos' ? 1 : -1;

        $total = $coleccion->countDocuments($filtro);
        $eventos = $coleccion->find($filtro, [
            ...self::TIPOS,
            'projection' => self::CAMPOS_LISTADO,
            'sort' => ['fecha_hora' => $orden, '_id' => $orden],
            'skip' => ($pagina - 1) * $porPagina,
            'limit' => $porPagina,
        ])->toArray();

        return (new LengthAwarePaginator(array_map($this->normalizar(...), $eventos), $total, $porPagina, $pagina, [
            'path' => Paginator::resolveCurrentPath(),
        ]))->withQueryString();
    }

    /** Mismos filtros y orden del historial, sin paginar, hasta $limite filas. */
    public function exportar(array $filtros, int $limite): array
    {
        $orden = ($filtros['orden'] ?? 'recientes') === 'antiguos' ? 1 : -1;
        $eventos = $this->coleccion()->find($this->filtro($filtros), [
            ...self::TIPOS,
            'projection' => self::CAMPOS_LISTADO,
            'sort' => ['fecha_hora' => $orden, '_id' => $orden],
            'limit' => $limite,
        ])->toArray();

        return array_map($this->normalizar(...), $eventos);
    }

    public function buscarPorUuid(string $uuid): ?array
    {
        $evento = $this->coleccion()->findOne(['uuid' => $uuid], self::TIPOS);

        return $evento ? $this->normalizar($evento) : null;
    }

    /** @return array<string, int> resultado => total */
    public function totalesPorResultado(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $grupos = $this->coleccion()->aggregate([
            ['$match' => ['fecha_hora' => ['$gte' => new UTCDateTime($desde), '$lte' => new UTCDateTime($hasta)]]],
            ['$group' => ['_id' => '$resultado', 'total' => ['$sum' => 1]]],
        ], self::TIPOS)->toArray();

        return array_column($grupos, 'total', '_id');
    }

    private function filtro(array $filtros): array
    {
        $tz = config('app.timezone');
        $filtro = [];

        if (! empty($filtros['desde'])) {
            $filtro['fecha_hora']['$gte'] = new UTCDateTime(Carbon::parse($filtros['desde'], $tz)->startOfDay());
        }
        if (! empty($filtros['hasta'])) {
            $filtro['fecha_hora']['$lte'] = new UTCDateTime(Carbon::parse($filtros['hasta'], $tz)->endOfDay());
        }
        if (! empty($filtros['zona_id'])) {
            $filtro['zona.zona_id'] = (int) $filtros['zona_id'];
        }
        foreach (['tipo_objeto', 'resultado'] as $campo) {
            if (! empty($filtros[$campo])) {
                $filtro[$campo] = $filtros[$campo];
            }
        }

        return $filtro;
    }

    private function normalizar(array $evento): array
    {
        $evento['id'] = (string) $evento['_id'];
        $evento['fecha_hora'] = Carbon::instance($evento['fecha_hora']->toDateTime())->timezone(config('app.timezone'));
        unset($evento['_id']);

        return $evento;
    }

    private function coleccion(): Collection
    {
        return DB::connection('mongodb')->getDatabase()->selectCollection('eventos');
    }
}
