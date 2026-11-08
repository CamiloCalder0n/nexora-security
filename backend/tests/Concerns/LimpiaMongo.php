<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Deja vacía la base de MongoDB de pruebas (MONGODB_DATABASE en phpunit.xml) antes de cada test,
 * instalada igual que en producción (validadores e índices): un documento mal formado falla el test.
 * El esquema se instala una vez por clase de test (el estático de un trait es propio de cada clase);
 * si un test borró colecciones, se vuelve a instalar. Un test que cambia el esquema llama a
 * reinstalarMongoDespues() para no contaminar a los siguientes.
 */
trait LimpiaMongo
{
    private const COLECCIONES = ['eventos', 'alertas', 'bitacora'];

    private static bool $mongoInstalado = false;

    protected function reinstalarMongoDespues(): void
    {
        self::$mongoInstalado = false;
    }

    protected function setUpLimpiaMongo(): void
    {
        $base = DB::connection('mongodb')->getDatabase();

        // Nunca borrar la base de desarrollo o producción por una variable de entorno mal puesta.
        if (! str_ends_with($base->getDatabaseName(), '_test')) {
            throw new RuntimeException("LimpiaMongo solo borra bases *_test y la configurada es {$base->getDatabaseName()}.");
        }

        $existentes = iterator_to_array($base->listCollectionNames());
        if (! self::$mongoInstalado || array_diff(self::COLECCIONES, $existentes)) {
            $base->drop();
            Artisan::call('nexora:mongo-instalar');
            self::$mongoInstalado = true;

            return;
        }

        foreach ($existentes as $coleccion) {
            $base->selectCollection($coleccion)->deleteMany([]);
        }
    }
}
