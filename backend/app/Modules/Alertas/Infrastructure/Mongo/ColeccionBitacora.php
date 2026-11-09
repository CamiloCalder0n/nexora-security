<?php

namespace App\Modules\Alertas\Infrastructure\Mongo;

use App\Support\Mongo\ColeccionMongo;

class ColeccionBitacora extends ColeccionMongo
{
    public function nombre(): string
    {
        return 'bitacora';
    }

    public function validador(): array
    {
        $hash = ['bsonType' => 'string', 'pattern' => '^[0-9a-f]{64}$'];

        return ['$jsonSchema' => [
            'bsonType' => 'object',
            'required' => ['schema_version', 'secuencia', 'fecha_hora', 'tipo', 'descripcion', 'usuario', 'hash_anterior', 'hash'],
            'properties' => [
                'schema_version' => ['bsonType' => self::ENTERO, 'minimum' => 1],
                'secuencia' => ['bsonType' => self::ENTERO, 'minimum' => 1],
                'fecha_hora' => ['bsonType' => 'date'],
                'tipo' => ['bsonType' => 'string', 'pattern' => '^[a-z_]+$'],
                'descripcion' => ['bsonType' => 'string', 'minLength' => 1],
                'estado_anterior' => ['bsonType' => ['string', 'null']],
                'estado_nuevo' => ['bsonType' => ['string', 'null']],
                'usuario' => [
                    'bsonType' => 'object',
                    'required' => ['usuario_id', 'nombre', 'rol'],
                    'properties' => [
                        'usuario_id' => ['bsonType' => [...self::ENTERO, 'null']],
                        'nombre' => ['bsonType' => 'string'],
                        'rol' => ['bsonType' => 'string'],
                    ],
                ],
                'alerta_id' => ['bsonType' => ['string', 'null']],
                'hash_anterior' => $hash,
                'hash' => $hash,
                'created_at' => ['bsonType' => 'date'],
                'updated_at' => ['bsonType' => 'date'],
            ],
        ]];
    }

    public function indices(): array
    {
        return [
            [['fecha_hora' => 1], ['name' => 'fecha']],
            [['secuencia' => 1], ['name' => 'secuencia_unica', 'unique' => true]],
            // Cada alerta nueva pregunta si ya tiene su entrada (BitacoraService::existe) y el historial filtra por alerta.
            [['alerta_id' => 1, 'tipo' => 1], ['name' => 'alerta_tipo']],
        ];
    }
}
