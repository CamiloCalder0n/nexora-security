<?php

namespace App\Support\Mongo;

/**
 * Una colección de MongoDB del backend: su validador y sus índices. Cada módulo registra las
 * suyas con el tag `nexora.colecciones_mongo` y `nexora:mongo-instalar` las deja al día.
 */
abstract class ColeccionMongo
{
    public const ENTERO = ['int', 'long'];

    abstract public function nombre(): string;

    /** Validador de la colección: `$jsonSchema` y, si hace falta, reglas entre campos. */
    abstract public function validador(): array;

    /** @return list<array{0: array, 1: array}> [claves, opciones] de cada índice */
    abstract public function indices(): array;

    /** Referencia embebida a una fila de PostgreSQL: `{<id>: entero, nombre: texto}` (Extended Reference de BD2). */
    protected static function referencia(string $id, bool $nullable = false): array
    {
        return [
            'bsonType' => $nullable ? ['object', 'null'] : 'object',
            'required' => [$id, 'nombre'],
            'properties' => [$id => ['bsonType' => self::ENTERO], 'nombre' => ['bsonType' => 'string']],
        ];
    }
}
