<?php

namespace App\Support\Mongo;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\Exception as ErrorMongo;

/**
 * Deja MongoDB listo para el backend: colecciones con su validador (`strict` y `error`: un
 * documento mal formado no entra) e índices. Se puede correr las veces que sea.
 *
 * Si ya hay documentos que no cumplen el esquema, no cambia nada (salvo `--forzar`): después
 * cualquier actualización sobre ellos fallaría. Lo demás lo cuida Mongo: crear un índice que ya
 * existe igual no hace nada, y uno que cambió, o uno único sobre datos repetidos, falla con su
 * error en vez de borrarse solo. Se corre en el despliegue con la ingesta detenida.
 */
class InstalarMongo extends Command
{
    protected $signature = 'nexora:mongo-instalar {--forzar : Aplicar el validador aunque haya documentos que no lo cumplen}';

    protected $aliases = ['nexora:mongo-indices'];  // nombre que usa la Guía 02

    protected $description = 'Crea o actualiza las colecciones de MongoDB con su validación e índices (idempotente)';

    public function handle(): int
    {
        $base = DB::connection('mongodb')->getDatabase();
        $existentes = iterator_to_array($base->listCollectionNames());
        /** @var list<ColeccionMongo> $colecciones */
        $colecciones = iterator_to_array(app()->tagged('nexora.colecciones_mongo'));

        $invalidos = [];
        foreach ($colecciones as $c) {
            $n = in_array($c->nombre(), $existentes, true)
                ? $base->selectCollection($c->nombre())->countDocuments(['$nor' => [$c->validador()]])
                : 0;
            if ($n > 0) {
                $invalidos[] = "{$c->nombre()}: {$n} documentos NO cumplen el esquema (verlos con db.{$c->nombre()}.find({\$nor: [<validador>]})).";
            }
        }
        array_map(fn ($m) => $this->warn($m), $invalidos);
        if ($invalidos !== [] && ! $this->option('forzar')) {
            $this->error('No se cambió nada. Corrígelos, o usa --forzar para aplicar el validador igual.');

            return self::FAILURE;
        }

        $fallas = 0;
        // Cada paso por su cuenta: si uno falla, los demás se aplican igual y el comando termina con error.
        foreach ($colecciones as $c) {
            $validacion = ['validator' => $c->validador(), 'validationLevel' => 'strict', 'validationAction' => 'error'];
            $pasos = [$c->nombre() => fn () => in_array($c->nombre(), $existentes, true)
                ? $base->command(['collMod' => $c->nombre(), ...$validacion])
                : $base->createCollection($c->nombre(), $validacion)];
            foreach ($c->indices() as [$claves, $opciones]) {
                $pasos["{$c->nombre()}.{$opciones['name']}"] = fn () => $base->selectCollection($c->nombre())->createIndex($claves, $opciones);
            }

            $antes = $fallas;
            foreach ($pasos as $nombre => $paso) {
                try {
                    $paso();
                } catch (ErrorMongo $e) {
                    $fallas++;
                    $this->error("{$nombre}: MongoDB rechazó el cambio: {$e->getMessage()}");
                }
            }
            if ($fallas === $antes) {
                $this->line("{$c->nombre()}: validador e índices al día");
            }
        }

        if ($fallas > 0) {
            $this->error('Revisa los pasos de arriba y arréglalos a mano en mongosh, sin escrituras. Un índice que cambió no se '
                .'borra solo; un TTL se cambia sin borrarlo: db.runCommand({collMod: "<coleccion>", index: {name: "<indice>", expireAfterSeconds: N}}).');

            return self::FAILURE;
        }

        $this->info('MongoDB listo.');

        return self::SUCCESS;
    }
}
