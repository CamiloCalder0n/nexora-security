<?php

namespace App\Modules\Analitica\Infrastructure\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

/**
 * Lectura de la colección "alertas" que escribe el módulo Alertas. Solo consulta: nunca inserta,
 * actualiza ni borra, y no depende del modelo de ese módulo, solo del esquema del documento.
 */
class AlertasLecturaRepository
{
    private const TIPOS = ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];

    /**
     * @return array{abiertas: int, total: int, cerradas: int, falsas_alarmas: int, por_zona: array<string, int>}
     */
    public function resumen(CarbonInterface $desde): array
    {
        $coleccion = $this->coleccion();

        $resultado = $coleccion->aggregate([
            ['$match' => ['fecha_hora' => ['$gte' => new UTCDateTime($desde)]]],
            ['$facet' => [
                'por_zona' => [
                    ['$group' => ['_id' => '$zona.nombre', 'total' => ['$sum' => 1]]],
                    ['$sort' => ['total' => -1, '_id' => 1]],
                ],
                'cerradas' => [
                    ['$match' => ['estado' => 'cerrada']],
                    ['$group' => ['_id' => '$clasificacion', 'total' => ['$sum' => 1]]],
                ],
                'total' => [['$count' => 'total']],
            ]],
        ], self::TIPOS)->toArray()[0] ?? [];

        $cerradas = array_column($resultado['cerradas'] ?? [], 'total', '_id');

        return [
            'abiertas' => $coleccion->countDocuments(['estado' => 'abierta']),
            'total' => $resultado['total'][0]['total'] ?? 0,
            'cerradas' => array_sum($cerradas),
            'falsas_alarmas' => $cerradas['falsa_alarma'] ?? 0,
            'por_zona' => array_column($resultado['por_zona'] ?? [], 'total', '_id'),
        ];
    }

    private function coleccion(): Collection
    {
        return DB::connection('mongodb')->getDatabase()->selectCollection('alertas');
    }
}
