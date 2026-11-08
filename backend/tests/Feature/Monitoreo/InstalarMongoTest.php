<?php

namespace Tests\Feature\Monitoreo;

use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\BulkWriteException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class InstalarMongoTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo;

    protected function tearDown(): void
    {
        $this->reinstalarMongoDespues();  // estos tests tocan validadores e índices
        parent::tearDown();
    }

    private function info(string $coleccion): array
    {
        $info = iterator_to_array(DB::connection('mongodb')->getDatabase()->listCollections(['filter' => ['name' => $coleccion]]));

        return json_decode(json_encode($info[0]->getOptions()), true);
    }

    private function indices(string $coleccion): array
    {
        $indices = [];
        foreach (DB::connection('mongodb')->getCollection($coleccion)->listIndexes() as $indice) {
            $indices[$indice->getName()] = $indice;
        }

        return $indices;
    }

    public function test_crea_las_tres_colecciones_con_validador_estricto_e_indices(): void
    {
        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('eventos: validador actualizado')  // LimpiaMongo ya la había creado
            ->assertSuccessful();
        $this->artisan('nexora:mongo-indices')->assertSuccessful();     // alias de las guías

        foreach (['eventos', 'alertas', 'bitacora'] as $coleccion) {
            $opciones = $this->info($coleccion);
            $this->assertSame('strict', $opciones['validationLevel'], $coleccion);
            $this->assertSame('error', $opciones['validationAction'], $coleccion);
            $this->assertArrayHasKey('$jsonSchema', $opciones['validator'], $coleccion);
        }

        $eventos = $this->indices('eventos');
        $this->assertTrue($eventos['uuid_unico']->isUnique());
        $this->assertSame(365 * 24 * 60 * 60, $eventos['ttl_365_dias']['expireAfterSeconds']);
        $this->assertSame(['resultado' => 'pendiente'], (array) $eventos['pendientes']['partialFilterExpression']);
        $this->assertArrayHasKey('zona_fecha', $eventos);
        $this->assertSame(['estado_fecha', 'zona_estado', 'eventos_ids'], array_values(array_diff(array_keys($this->indices('alertas')), ['_id_'])));
        $this->assertTrue($this->indices('bitacora')['secuencia_unica']->isUnique());
    }

    public function test_a_una_base_vieja_sin_validador_se_lo_agrega(): void
    {
        DB::connection('mongodb')->getDatabase()->drop();
        DB::connection('mongodb')->getDatabase()->createCollection('eventos');  // como la dejaba la versión anterior

        $this->artisan('nexora:mongo-instalar')->expectsOutputToContain('eventos: validador actualizado')->assertSuccessful();

        $this->assertSame('strict', $this->info('eventos')['validationLevel']);
    }

    public function test_si_hay_documentos_viejos_que_no_cumplen_no_cambia_nada(): void
    {
        DB::connection('mongodb')->getDatabase()->drop();
        DB::connection('mongodb')->getDatabase()->createCollection('eventos');
        DB::connection('mongodb')->table('eventos')->insert(['uuid' => 'viejo', 'fecha_hora' => now()]);  // forma anterior

        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('eventos: 1 documentos NO cumplen el esquema nuevo')
            ->assertFailed();

        $this->assertArrayNotHasKey('validator', $this->info('eventos'));   // la app sigue pudiendo actualizarlos
        $this->assertArrayNotHasKey('uuid_unico', $this->indices('eventos'));

        $this->artisan('nexora:mongo-instalar', ['--forzar' => true])->assertSuccessful();
        $this->assertSame('strict', $this->info('eventos')['validationLevel']);
    }

    public function test_ajusta_el_ttl_sin_borrar_el_indice(): void
    {
        DB::connection('mongodb')->getDatabase()->command(['collMod' => 'eventos', 'index' => ['name' => 'ttl_365_dias', 'expireAfterSeconds' => 60]]);

        $this->artisan('nexora:mongo-instalar')->expectsOutputToContain('ttl_365_dias (ttl)')->assertSuccessful();

        $this->assertSame(365 * 24 * 60 * 60, $this->indices('eventos')['ttl_365_dias']['expireAfterSeconds']);
    }

    public function test_nunca_borra_solo_un_indice_unico_que_cambio(): void
    {
        $eventos = DB::connection('mongodb')->getCollection('eventos');
        $eventos->dropIndex('uuid_unico');
        $eventos->createIndex(['uuid' => 1], ['name' => 'uuid_unico']);  // quedó sin unique

        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('el índice único uuid_unico cambió (unique)')
            ->assertFailed();

        $this->assertArrayHasKey('uuid_unico', $this->indices('eventos'));  // sigue ahí, no se tocó
    }

    public function test_avisa_si_las_mismas_claves_tienen_otro_nombre(): void
    {
        $eventos = DB::connection('mongodb')->getCollection('eventos');
        $eventos->dropIndex('uuid_unico');
        $eventos->createIndex(['uuid' => 1], ['name' => 'uuid_1', 'unique' => true]);

        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('ya existe el índice uuid_1 con las claves de uuid_unico')
            ->assertFailed();

        $this->assertTrue($this->indices('eventos')['uuid_1']->isUnique());
    }

    public function test_el_orden_de_las_claves_cuenta(): void
    {
        $eventos = DB::connection('mongodb')->getCollection('eventos');
        // Otro índice legítimo con las mismas claves en otro orden (p. ej. uno de reportes): no es conflicto.
        $eventos->createIndex(['fecha_hora' => -1, 'zona.zona_id' => 1], ['name' => 'fecha_zona_reportes']);
        $this->artisan('nexora:mongo-instalar')->assertSuccessful();

        // zona_fecha con las claves al revés sí se detecta y se rehace bien.
        $eventos->dropIndex('fecha_zona_reportes');
        $eventos->dropIndex('zona_fecha');
        $eventos->createIndex(['fecha_hora' => -1, 'zona.zona_id' => 1], ['name' => 'zona_fecha']);
        $this->artisan('nexora:mongo-instalar')->expectsOutputToContain('zona_fecha (rehacer)')->assertSuccessful();
        $this->assertSame(['zona.zona_id', 'fecha_hora'], array_keys($this->indices('eventos')['zona_fecha']->getKey()));
    }

    public function test_no_rehace_si_otro_indice_ya_tiene_las_claves_correctas(): void
    {
        $eventos = DB::connection('mongodb')->getCollection('eventos');
        $eventos->dropIndex('zona_fecha');
        $eventos->createIndex(['zona.zona_id' => 1, 'fecha_hora' => -1], ['name' => 'zf_manual']);
        $eventos->createIndex(['fecha_hora' => -1], ['name' => 'zona_fecha']);  // nombre bien, claves mal

        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('ya existe el índice zf_manual con las claves de zona_fecha')
            ->doesntExpectOutputToContain('find({$nor')
            ->assertFailed();

        $this->assertArrayHasKey('zona_fecha', $this->indices('eventos'));  // no se borró nada
        $this->assertArrayHasKey('zf_manual', $this->indices('eventos'));
    }

    public function test_un_filtro_parcial_equivalente_no_se_rehace(): void
    {
        $eventos = DB::connection('mongodb')->getCollection('eventos');
        $eventos->dropIndex('pendientes');
        $eventos->createIndex(['created_at' => 1], ['name' => 'pendientes', 'partialFilterExpression' => ['resultado' => ['$eq' => 'pendiente']]]);

        $this->artisan('nexora:mongo-instalar')->doesntExpectOutputToContain('pendientes (rehacer)')->assertSuccessful();
    }

    public function test_con_un_conflicto_no_toca_ninguna_coleccion(): void
    {
        DB::connection('mongodb')->getDatabase()->drop();
        $base = DB::connection('mongodb')->getDatabase();
        $base->createCollection('eventos');
        $base->createCollection('bitacora');
        // Dos entradas con la misma secuencia: el índice único de bitácora no se puede crear.
        $entrada = ['secuencia' => 1, 'fecha_hora' => now(), 'tipo' => 'alerta_creada', 'descripcion' => 'x', 'estado_nuevo' => 'abierta',
            'usuario' => ['usuario_id' => null, 'nombre' => 'Sistema', 'rol' => 'sistema'], 'hash_anterior' => str_repeat('0', 64), 'hash' => str_repeat('a', 64), 'schema_version' => 1];
        DB::connection('mongodb')->table('bitacora')->insert([$entrada, $entrada]);

        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('hay documentos repetidos en secuencia')
            ->assertFailed();

        $this->assertArrayNotHasKey('validator', $this->info('eventos'));          // ni la primera colección cambió
        $this->assertNotContains('alertas', iterator_to_array($base->listCollectionNames()));
    }

    public function test_rehace_un_indice_no_unico_que_cambio(): void
    {
        $eventos = DB::connection('mongodb')->getCollection('eventos');
        $eventos->dropIndex('pendientes');
        $eventos->createIndex(['created_at' => 1], ['name' => 'pendientes']);  // sin el filtro parcial

        $this->artisan('nexora:mongo-instalar')->expectsOutputToContain('pendientes (rehacer)')->assertSuccessful();

        $this->assertSame(['resultado' => 'pendiente'], (array) $this->indices('eventos')['pendientes']['partialFilterExpression']);
    }

    public function test_el_uuid_del_evento_no_se_repite(): void
    {
        $evento = $this->crearEvento(['uuid' => '6f1c2b9e-3a4d-4c55-9e0a-1b2c3d4e5f60']);
        $this->assertSame(ResultadoEvento::Pendiente, $evento->fresh()->resultado);
        $this->assertSame(Evento::SCHEMA_VERSION, $evento->fresh()->schema_version);

        $this->expectException(BulkWriteException::class);
        $this->crearEvento(['uuid' => '6f1c2b9e-3a4d-4c55-9e0a-1b2c3d4e5f60']);
    }

    /** La base se defiende sola aunque un bug del código intente guardar algo mal formado. */
    public static function invalidos(): array
    {
        return [
            'evento sin cámara' => ['evento', ['camara' => null]],
            'evento con cantidad 0' => ['evento', ['cantidad' => 0]],
            'evento con confianza 1.5' => ['evento', ['confianza' => 1.5]],
            'evento con tipo raro' => ['evento', ['tipo_objeto' => 'perro']],
            'evento con uuid que no es v4' => ['evento', ['uuid' => 'abc']],
            'alerta cerrada sin clasificación' => ['alerta', ['estado' => 'cerrada']],
            'alerta con prioridad 7' => ['alerta', ['prioridad' => 7]],
            'alerta con 4 evidencias' => ['alerta', ['evidencias_recientes' => ['a', 'b', 'c', 'd']]],
            'alerta con evento repetido' => ['alerta', ['eventos_ids' => ['x', 'x']]],
            'alerta sin regla' => ['alerta', ['regla' => '']],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_documentos_mal_formados(string $tipo, array $datos): void
    {
        $this->expectException(BulkWriteException::class);
        $this->expectExceptionMessage('Document failed validation');

        $tipo === 'evento' ? $this->crearEvento($datos) : $this->crearAlerta($datos);
    }

    public function test_rechaza_un_resultado_inventado_aunque_se_salte_el_modelo(): void
    {
        $this->expectException(BulkWriteException::class);

        DB::connection('mongodb')->table('eventos')->insert([
            'schema_version' => 1, 'uuid' => '6f1c2b9e-3a4d-4c55-9e0a-1b2c3d4e5f61', 'fecha_hora' => now(),
            'tipo_objeto' => 'persona', 'cantidad' => 1, 'confianza' => 0.9, 'explicado' => false, 'resultado' => 'borrado',
            'camara' => ['camara_id' => 1, 'nombre' => 'CAM-01'], 'zona' => ['zona_id' => 1, 'nombre' => 'Parqueadero'],
        ]);
    }

    public function test_acepta_una_alerta_cerrada_con_clasificacion(): void
    {
        $alerta = $this->crearAlerta(['estado' => 'cerrada', 'clasificacion' => 'falsa_alarma', 'fecha_cierre' => now()]);

        $this->assertSame('cerrada', $alerta->fresh()->estado->value);
    }

    public function test_rechaza_una_entrada_de_bitacora_con_hash_malo(): void
    {
        $this->expectException(BulkWriteException::class);

        EntradaBitacora::create([
            'secuencia' => 1, 'fecha_hora' => now(), 'tipo' => 'alerta_creada', 'descripcion' => 'x', 'estado_nuevo' => 'abierta',
            'usuario' => ['usuario_id' => null, 'nombre' => 'Sistema', 'rol' => 'sistema'],
            'hash_anterior' => str_repeat('0', 64), 'hash' => 'no-es-un-hash',
        ]);
    }
}
