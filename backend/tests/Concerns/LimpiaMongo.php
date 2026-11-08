<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Deja vacía la base de MongoDB de pruebas (MONGODB_DATABASE en phpunit.xml) antes de cada test. */
trait LimpiaMongo
{
    protected function setUpLimpiaMongo(): void
    {
        $base = DB::connection('mongodb')->getDatabase();

        // Nunca borrar la base de desarrollo o producción por una variable de entorno mal puesta.
        if (! str_ends_with($base->getDatabaseName(), '_test')) {
            throw new RuntimeException("LimpiaMongo solo borra bases *_test y la configurada es {$base->getDatabaseName()}.");
        }

        $base->drop();
    }
}
