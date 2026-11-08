<?php

namespace App\Support\Mongo;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Database;
use MongoDB\Model\IndexInfo;

/**
 * Deja MongoDB listo para el backend: colecciones con su validador (`strict` y `error`: un
 * documento mal formado no entra) e índices. Se puede correr las veces que sea.
 *
 * Primero arma el plan completo sin tocar nada y solo lo aplica si está limpio, así nunca
 * queda a medio instalar. Es conservador con lo que ya existe:
 * - Si hay documentos que no cumplen el esquema nuevo, no cambia nada (salvo `--forzar`):
 *   después cualquier actualización sobre ellos fallaría.
 * - Un TTL que cambió se ajusta con collMod, sin borrar el índice.
 * - Un índice único que cambió, o uno nuevo con datos duplicados, nunca se toca solo: sin él
 *   se pierde la deduplicación de eventos o la cadena de la bitácora. Se avisa.
 * - Solo los índices no únicos que cambiaron se rehacen.
 *
 * Se corre en el despliegue con la ingesta detenida (sin worker ni agente enviando): si algo
 * se escribe entre planear y aplicar, el comando falla con un mensaje y la siguiente corrida
 * lo detecta en el plan.
 */
class InstalarMongo extends Command
{
    protected $signature = 'nexora:mongo-instalar {--forzar : Aplicar el validador aunque haya documentos que no lo cumplen}';

    protected $aliases = ['nexora:mongo-indices'];

    protected $description = 'Crea o actualiza las colecciones de MongoDB con su validación e índices (idempotente)';

    public function handle(): int
    {
        $base = DB::connection('mongodb')->getDatabase();
        $existentes = iterator_to_array($base->listCollectionNames());
        $planes = [];
        $conflictos = [];
        $invalidos = [];

        // 1. Planear sin tocar nada.
        foreach (app()->tagged('nexora.colecciones_mongo') as $coleccion) {
            /** @var ColeccionMongo $coleccion */
            $plan = $this->planear($base, $coleccion, in_array($coleccion->nombre(), $existentes, true));
            $planes[] = $plan;
            $conflictos = [...$conflictos, ...$plan['conflictos']];
            if ($plan['invalidos'] > 0) {
                $invalidos[] = "{$plan['nombre']}: {$plan['invalidos']} documentos NO cumplen el esquema nuevo.";
            }
        }

        $invalidosBloquean = $invalidos !== [] && ! $this->option('forzar');
        if ($conflictos !== [] || $invalidosBloquean) {
            array_map(fn ($m) => $this->error($m), [...$conflictos, ...($invalidosBloquean ? $invalidos : [])]);
            $this->error('No se cambió nada. Corrige lo anterior y vuelve a correr el comando.');
            if ($invalidosBloquean) {
                $this->error('Los documentos se ven en mongosh con db.<coleccion>.find({$nor: [<validador>]})'
                    .($conflictos === [] ? '; o usa --forzar para aplicar el validador igual.' : '.'));
            }

            return self::FAILURE;
        }

        // 2. Aplicar el plan.
        try {
            $this->aplicar($base, $planes);
        } catch (\Throwable $e) {
            $this->error("Falló al aplicar ({$e->getMessage()}). Algo cambió en la base mientras corría: "
                .'detén la ingesta y vuelve a correr el comando, que mostrará qué falta.');

            return self::FAILURE;
        }

        $this->info('MongoDB listo.');

        return self::SUCCESS;
    }

    private function aplicar(Database $base, array $planes): void
    {
        foreach ($planes as $plan) {
            $validacion = ['validator' => $plan['coleccion']->validador(), 'validationLevel' => 'strict', 'validationAction' => 'error'];
            if ($plan['existe']) {
                $base->command(['collMod' => $plan['nombre'], ...$validacion]);
            } else {
                $base->createCollection($plan['nombre'], $validacion);
            }

            $mongo = $base->selectCollection($plan['nombre']);
            foreach ($plan['indices'] as ['accion' => $accion, 'claves' => $claves, 'opciones' => $opciones]) {
                match ($accion) {
                    'crear' => $mongo->createIndex($claves, $opciones),
                    'ttl' => $base->command(['collMod' => $plan['nombre'], 'index' => ['name' => $opciones['name'], 'expireAfterSeconds' => $opciones['expireAfterSeconds']]]),
                    'rehacer' => [$mongo->dropIndex($opciones['name']), $mongo->createIndex($claves, $opciones)],
                    'igual' => null,
                };
            }

            $detalle = implode(', ', array_map(fn ($i) => $i['opciones']['name'].($i['accion'] === 'igual' ? '' : " ({$i['accion']})"), $plan['indices']));
            $this->line("{$plan['nombre']}: ".($plan['existe'] ? 'validador actualizado' : 'creada')." · índices: {$detalle}");
        }
    }

    private function planear(Database $base, ColeccionMongo $coleccion, bool $existe): array
    {
        $nombre = $coleccion->nombre();
        $mongo = $base->selectCollection($nombre);
        $actuales = $existe ? iterator_to_array($mongo->listIndexes()) : [];
        $plan = ['coleccion' => $coleccion, 'nombre' => $nombre, 'existe' => $existe, 'indices' => [], 'conflictos' => [],
            'invalidos' => $existe ? $mongo->countDocuments(['$nor' => [$coleccion->validador()]]) : 0];

        foreach ($coleccion->indices() as [$claves, $opciones]) {
            [$accion, $conflicto] = $this->accionIndice($base, $nombre, $actuales, $claves, $opciones);
            $plan['indices'][] = ['accion' => $accion, 'claves' => $claves, 'opciones' => $opciones];
            if ($conflicto !== null) {
                $plan['conflictos'][] = "{$nombre}: {$conflicto}";
            }
        }

        return $plan;
    }

    /** @return array{0: string, 1: ?string} acción y, si la hay, el conflicto que impide aplicarla */
    private function accionIndice(Database $base, string $coleccion, array $actuales, array $claves, array $opciones): array
    {
        $nombre = $opciones['name'];
        $unico = (bool) ($opciones['unique'] ?? false);
        $mismoNombre = current(array_filter($actuales, fn (IndexInfo $i) => $i->getName() === $nombre)) ?: null;
        $otro = current(array_filter($actuales, fn (IndexInfo $i) => $i->getName() !== $nombre
            && self::claves($i->getKey()) === self::claves($claves))) ?: null;
        $conflictoOtro = $otro ? "ya existe el índice {$otro->getName()} con las claves de {$nombre}; bórralo o renómbralo a mano." : null;

        if ($mismoNombre === null) {
            if ($conflictoOtro !== null) {
                return ['crear', $conflictoOtro];
            }
            if ($unico && $this->hayDuplicados($base, $coleccion, $claves, $opciones['partialFilterExpression'] ?? null)) {
                return ['crear', 'hay documentos repetidos en '.implode(', ', array_keys($claves))."; el índice único {$nombre} no se puede crear hasta quitarlos."];
            }

            return ['crear', null];
        }

        $cambios = $this->diferencias($mismoNombre, $claves, $opciones);
        if ($cambios === []) {
            return ['igual', null];
        }
        if ($cambios === ['expireAfterSeconds'] && isset($opciones['expireAfterSeconds']) && $mismoNombre->isTtl()) {
            return ['ttl', null];
        }
        if ($unico || $mismoNombre->isUnique()) {
            return ['rehacer', "el índice único {$nombre} cambió (".implode(', ', $cambios).'); no se borra solo porque sin él se pierde la unicidad. Cámbialo a mano sin escrituras.'];
        }

        return ['rehacer', $conflictoOtro];
    }

    /** @return list<string> qué cambió entre el índice actual y el deseado */
    private function diferencias(IndexInfo $actual, array $claves, array $opciones): array
    {
        $info = json_decode(json_encode($actual->__debugInfo()), true);
        $cambios = self::claves($info['key']) === self::claves($claves) ? [] : ['claves'];

        if ((bool) ($info['unique'] ?? false) !== (bool) ($opciones['unique'] ?? false)) {
            $cambios[] = 'unique';
        }
        if (self::numero($info['expireAfterSeconds'] ?? null) !== self::numero($opciones['expireAfterSeconds'] ?? null)) {
            $cambios[] = 'expireAfterSeconds';
        }
        if (self::filtro($info['partialFilterExpression'] ?? null) != self::filtro($opciones['partialFilterExpression'] ?? null)) {
            $cambios[] = 'partialFilterExpression';
        }

        return $cambios;
    }

    private function hayDuplicados(Database $base, string $coleccion, array $claves, ?array $filtroParcial): bool
    {
        $grupo = array_combine(array_map(fn ($c) => str_replace('.', '_', $c), array_keys($claves)), array_map(fn ($c) => '$'.$c, array_keys($claves)));
        $repetidos = $base->selectCollection($coleccion)->aggregate([
            ['$match' => $filtroParcial ?? (object) []],  // un índice parcial solo exige unicidad dentro del filtro
            ['$group' => ['_id' => $grupo, 'n' => ['$sum' => 1]]],
            ['$match' => ['n' => ['$gt' => 1]]],
            ['$limit' => 1],
        ]);

        return iterator_to_array($repetidos) !== [];
    }

    /** Claves en orden y con dirección entera: en Mongo el orden de las claves define el índice. */
    private static function claves(array $claves): array
    {
        return array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $claves);
    }

    private static function numero(mixed $valor): ?int
    {
        return $valor === null ? null : (int) $valor;
    }

    /** `{campo: {$eq: v}}` y `{campo: v}` filtran lo mismo. */
    private static function filtro(mixed $filtro): mixed
    {
        if (! is_array($filtro)) {
            return $filtro;
        }
        if (array_keys($filtro) === ['$eq']) {
            return self::filtro($filtro['$eq']);
        }

        return array_map(fn ($v) => self::filtro($v), $filtro);
    }
}
