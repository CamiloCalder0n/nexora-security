<?php

namespace App\Modules\Analitica\Infrastructure\Repositories;

use Carbon\CarbonImmutable;
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

    /**
     * Alertas sin cerrar (HU11): primero las abiertas, luego las de mayor prioridad y las más antiguas.
     *
     * @return list<array{id: string, prioridad: int, zona: string, camara: ?string, motivo: string, fecha_hora: CarbonImmutable, num_eventos: int, estado: string, atendida_por: ?string}>
     */
    public function cola(int $limite = 100): array
    {
        // Por orden alfabético "abierta" va antes que "atendida".
        $documentos = $this->coleccion()->find(
            ['estado' => ['$in' => ['abierta', 'atendida']]],
            [...self::TIPOS, 'sort' => ['estado' => 1, 'prioridad' => -1, 'fecha_hora' => 1], 'limit' => $limite],
        );

        $cola = [];
        foreach ($documentos as $doc) {
            $cola[] = [
                'id' => (string) $doc['_id'],
                'prioridad' => (int) $doc['prioridad'],
                'zona' => $doc['zona']['nombre'] ?? '—',
                'camara' => $doc['camara']['nombre'] ?? null,
                'motivo' => $doc['motivo'],
                'fecha_hora' => CarbonImmutable::instance($doc['fecha_hora']->toDateTime()),
                'num_eventos' => (int) ($doc['num_eventos'] ?? 0),
                'estado' => $doc['estado'],
                'atendida_por' => $doc['atendida_por']['nombre'] ?? null,
            ];
        }

        return $cola;
    }

    private function coleccion(): Collection
    {
        return DB::connection('mongodb')->getDatabase()->selectCollection('alertas');
    }
}
