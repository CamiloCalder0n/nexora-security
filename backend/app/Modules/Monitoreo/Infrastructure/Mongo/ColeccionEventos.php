<?php

namespace App\Modules\Monitoreo\Infrastructure\Mongo;

use App\Modules\Monitoreo\Domain\ResultadoEvento;
use App\Support\Mongo\ColeccionMongo;

/** Detecciones del agente (modelo de BD2 adaptado: referencias a PostgreSQL con ids enteros). */
class ColeccionEventos extends ColeccionMongo
{
    public const TTL_SEG = 365 * 24 * 60 * 60;

    public function nombre(): string
    {
        return 'eventos';
    }

    public function validador(): array
    {
        return ['$jsonSchema' => [
            'bsonType' => 'object',
            'required' => ['schema_version', 'uuid', 'fecha_hora', 'tipo_objeto', 'cantidad', 'confianza',
                'resultado', 'explicado', 'camara', 'zona'],
            'properties' => [
                'schema_version' => ['bsonType' => self::ENTERO, 'minimum' => 1],
                'uuid' => ['bsonType' => 'string', 'pattern' => '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'],
                'fecha_hora' => ['bsonType' => 'date'],
                'tipo_objeto' => ['enum' => ['persona', 'vehiculo']],
                'cantidad' => ['bsonType' => self::ENTERO, 'minimum' => 1, 'maximum' => 50],
                'confianza' => ['bsonType' => ['double', ...self::ENTERO], 'minimum' => 0, 'maximum' => 1],
                'ruta_evidencia' => ['bsonType' => ['string', 'null'], 'maxLength' => 255],
                'resultado' => ['enum' => array_column(ResultadoEvento::cases(), 'value')],
                'explicado' => ['bsonType' => 'bool'],
                'camara' => self::referencia('camara_id'),
                'zona' => self::referencia('zona_id'),
                'registro_id' => ['bsonType' => [...self::ENTERO, 'null']],
                'alerta_id' => ['bsonType' => ['string', 'null']],
                'reencolos' => ['bsonType' => self::ENTERO, 'minimum' => 0],
                'proximo_reencolo' => ['bsonType' => ['date', 'null']],
                'created_at' => ['bsonType' => 'date'],
                'updated_at' => ['bsonType' => 'date'],
            ],
        ]];
    }

    public function indices(): array
    {
        return [
            [['uuid' => 1], ['name' => 'uuid_unico', 'unique' => true]],
            [['zona.zona_id' => 1, 'fecha_hora' => -1], ['name' => 'zona_fecha']],
            [['fecha_hora' => 1], ['name' => 'ttl_365_dias', 'expireAfterSeconds' => self::TTL_SEG]],
            // Parcial: solo indexa los pendientes, que casi siempre son pocos (nexora:reencolar-pendientes).
            [['created_at' => 1], ['name' => 'pendientes', 'partialFilterExpression' => ['resultado' => 'pendiente']]],
        ];
    }
}
