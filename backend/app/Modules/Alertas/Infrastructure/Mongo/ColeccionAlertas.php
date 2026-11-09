<?php

namespace App\Modules\Alertas\Infrastructure\Mongo;

use App\Modules\Alertas\Domain\Clasificacion;
use App\Modules\Alertas\Domain\EstadoAlerta;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\TipoAlerta;
use App\Support\Mongo\ColeccionMongo;

class ColeccionAlertas extends ColeccionMongo
{
    public function nombre(): string
    {
        return 'alertas';
    }

    public function validador(): array
    {
        return [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['schema_version', 'fecha_hora', 'tipo', 'prioridad', 'motivo', 'regla', 'estado',
                    'nivel_escalamiento', 'zona', 'eventos_ids', 'num_eventos', 'evidencias_recientes'],
                'properties' => [
                    'schema_version' => ['bsonType' => self::ENTERO, 'minimum' => 1],
                    'fecha_hora' => ['bsonType' => 'date'],
                    'fecha_ultimo_evento' => ['bsonType' => ['date', 'null']],
                    'tipo' => ['enum' => array_column(TipoAlerta::cases(), 'value')],
                    'prioridad' => ['bsonType' => self::ENTERO, 'minimum' => Alerta::PRIORIDAD_MIN, 'maximum' => Alerta::PRIORIDAD_MAX],
                    'motivo' => ['bsonType' => 'string', 'minLength' => 1],
                    'regla' => ['bsonType' => 'string', 'minLength' => 1],
                    'estado' => ['enum' => array_column(EstadoAlerta::cases(), 'value')],
                    'nivel_escalamiento' => ['bsonType' => self::ENTERO, 'minimum' => 0],
                    'fecha_escalamiento' => ['bsonType' => ['date', 'null']],
                    'clasificacion' => ['enum' => [...array_column(Clasificacion::cases(), 'value'), null]],
                    'observacion' => ['bsonType' => ['string', 'null'], 'maxLength' => Alerta::MAX_OBSERVACION],
                    'camara' => self::referencia('camara_id', nullable: true),
                    'zona' => self::referencia('zona_id'),
                    'eventos_ids' => ['bsonType' => 'array', 'uniqueItems' => true, 'items' => ['bsonType' => 'string']],
                    'num_eventos' => ['bsonType' => self::ENTERO, 'minimum' => 0],
                    // Patrón Subset de BD2: solo las últimas evidencias.
                    'evidencias_recientes' => ['bsonType' => 'array', 'maxItems' => Alerta::MAX_EVIDENCIAS, 'items' => ['bsonType' => 'string']],
                    'atendida_por' => self::referencia('usuario_id', nullable: true),
                    'fecha_atencion' => ['bsonType' => ['date', 'null']],
                    'fecha_cierre' => ['bsonType' => ['date', 'null']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ],
            // HU03 también en la base: una alerta cerrada siempre lleva clasificación.
            '$or' => [
                ['estado' => ['$ne' => EstadoAlerta::Cerrada->value]],
                ['clasificacion' => ['$type' => 'string']],
            ],
        ];
    }

    public function indices(): array
    {
        return [
            [['estado' => 1, 'fecha_hora' => -1], ['name' => 'estado_fecha']],
            [['zona.zona_id' => 1, 'estado' => 1], ['name' => 'zona_estado']],
            [['eventos_ids' => 1], ['name' => 'eventos_ids']],
        ];
    }
}
