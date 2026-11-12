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

    /**
     * RF008: indicadores de las alertas creadas entre $desde y $hasta.
     *
     * @return array{total: int, por_estado: array<string, int>, por_clasificacion: array<string, int>,
     *     por_zona: list<array{zona: string, total: int, incidentes: int, falsas: int}>, por_hora: array<int, int>,
     *     escaladas: int, atencion: array{atendidas: int, promedio_min: ?float, maximo_min: ?float}}
     */
    public function informe(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $r = $this->coleccion()->aggregate([
            ['$match' => ['fecha_hora' => ['$gte' => new UTCDateTime($desde), '$lte' => new UTCDateTime($hasta)]]],
            ['$facet' => [
                'total' => [['$count' => 'total']],
                'por_estado' => [['$group' => ['_id' => '$estado', 'total' => ['$sum' => 1]]]],
                'por_clasificacion' => [
                    ['$match' => ['clasificacion' => ['$type' => 'string']]],
                    ['$group' => ['_id' => '$clasificacion', 'total' => ['$sum' => 1]]],
                ],
                'por_zona' => [
                    ['$group' => [
                        '_id' => '$zona.nombre',
                        'total' => ['$sum' => 1],
                        'incidentes' => ['$sum' => ['$cond' => [['$eq' => ['$clasificacion', 'incidente']], 1, 0]]],
                        'falsas' => ['$sum' => ['$cond' => [['$eq' => ['$clasificacion', 'falsa_alarma']], 1, 0]]],
                    ]],
                    ['$sort' => ['total' => -1, '_id' => 1]],
                ],
                'por_hora' => [
                    ['$group' => ['_id' => ['$hour' => ['date' => '$fecha_hora', 'timezone' => config('app.timezone')]], 'total' => ['$sum' => 1]]],
                ],
                'escaladas' => [['$match' => ['nivel_escalamiento' => ['$gt' => 0]]], ['$count' => 'total']],
                // Minutos entre que se crea la alerta y que un vigilante la atiende.
                'atencion' => [
                    ['$match' => ['fecha_atencion' => ['$type' => 'date']]],
                    ['$project' => ['minutos' => ['$divide' => [['$subtract' => ['$fecha_atencion', '$fecha_hora']], 60000]]]],
                    ['$group' => ['_id' => null, 'atendidas' => ['$sum' => 1], 'promedio' => ['$avg' => '$minutos'], 'maximo' => ['$max' => '$minutos']]],
                ],
            ]],
        ], self::TIPOS)->toArray()[0] ?? [];

        $atencion = $r['atencion'][0] ?? null;

        return [
            'total' => $r['total'][0]['total'] ?? 0,
            'por_estado' => array_column($r['por_estado'] ?? [], 'total', '_id'),
            'por_clasificacion' => array_column($r['por_clasificacion'] ?? [], 'total', '_id'),
            'por_zona' => array_map(fn ($z) => [
                'zona' => $z['_id'] ?? 'Sin zona', 'total' => $z['total'], 'incidentes' => $z['incidentes'], 'falsas' => $z['falsas'],
            ], $r['por_zona'] ?? []),
            'por_hora' => array_column($r['por_hora'] ?? [], 'total', '_id'),
            'escaladas' => $r['escaladas'][0]['total'] ?? 0,
            'atencion' => [
                'atendidas' => $atencion['atendidas'] ?? 0,
                'promedio_min' => $atencion ? round($atencion['promedio'], 1) : null,
                'maximo_min' => $atencion ? round($atencion['maximo'], 1) : null,
            ],
        ];
    }

    private function coleccion(): Collection
    {
        return DB::connection('mongodb')->getDatabase()->selectCollection('alertas');
    }
}
