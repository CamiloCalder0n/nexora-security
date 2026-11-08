<?php

namespace App\Modules\Monitoreo\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrearIndicesMongo extends Command
{
    public const TTL_EVENTOS_SEG = 365 * 24 * 60 * 60;

    protected $signature = 'nexora:mongo-indices';

    protected $description = 'Crea los índices de MongoDB de eventos, alertas y bitácora (idempotente)';

    /** colección => [[claves, opciones], ...] */
    public static function indices(): array
    {
        return [
            'eventos' => [
                [['uuid' => 1], ['name' => 'uuid_unico', 'unique' => true]],
                [['zona.zona_id' => 1, 'fecha_hora' => -1], ['name' => 'zona_fecha']],
                [['fecha_hora' => 1], ['name' => 'ttl_365_dias', 'expireAfterSeconds' => self::TTL_EVENTOS_SEG]],
            ],
            'alertas' => [
                [['estado' => 1, 'fecha_hora' => -1], ['name' => 'estado_fecha']],
                [['zona.zona_id' => 1, 'estado' => 1], ['name' => 'zona_estado']],
                [['eventos_ids' => 1], ['name' => 'eventos_ids']],
            ],
            'bitacora' => [
                [['fecha_hora' => 1], ['name' => 'fecha']],
                [['secuencia' => 1], ['name' => 'secuencia_unica', 'unique' => true]],
            ],
        ];
    }

    public function handle(): int
    {
        $mongo = DB::connection('mongodb');

        foreach (self::indices() as $coleccion => $indices) {
            foreach ($indices as [$claves, $opciones]) {
                $mongo->getCollection($coleccion)->createIndex($claves, $opciones);
                $this->line("{$coleccion}: {$opciones['name']}");
            }
        }

        $this->info('Índices listos.');

        return self::SUCCESS;
    }
}
