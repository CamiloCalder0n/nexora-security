<?php

namespace Tests\Feature\Support;

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
            ->expectsOutputToContain('eventos: validador e índices al día')
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
        $this->assertSame(['estado_fecha', 'zona_estado', 'eventos_ids', 'cola'], array_values(array_diff(array_keys($this->indices('alertas')), ['_id_'])));
        $this->assertTrue($this->indices('bitacora')['secuencia_unica']->isUnique());
        $this->assertArrayHasKey('alerta_tipo', $this->indices('bitacora'));
    }

    public function test_a_una_base_vieja_sin_validador_se_lo_agrega(): void
    {
        DB::connection('mongodb')->getDatabase()->drop();
        DB::connection('mongodb')->getDatabase()->createCollection('eventos');  // como la dejaba la versión anterior

        $this->artisan('nexora:mongo-instalar')->expectsOutputToContain('eventos: validador e índices al día')->assertSuccessful();

        $this->assertSame('strict', $this->info('eventos')['validationLevel']);
    }

    public function test_si_hay_documentos_viejos_que_no_cumplen_no_cambia_nada(): void
    {
        DB::connection('mongodb')->getDatabase()->drop();
        DB::connection('mongodb')->getDatabase()->createCollection('eventos');
        DB::connection('mongodb')->table('eventos')->insert(['uuid' => 'viejo', 'fecha_hora' => now()]);  // forma anterior

        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('eventos: 1 documentos NO cumplen el esquema')
            ->assertFailed();

        $this->assertArrayNotHasKey('validator', $this->info('eventos'));   // la app sigue pudiendo actualizarlos
        $this->assertArrayNotHasKey('uuid_unico', $this->indices('eventos'));

        $this->artisan('nexora:mongo-instalar', ['--forzar' => true])
            ->expectsOutputToContain('eventos: 1 documentos NO cumplen el esquema')  // avisa aunque lo fuerce
            ->assertSuccessful();
        $this->assertSame('strict', $this->info('eventos')['validationLevel']);
    }

    public function test_un_indice_que_cambio_no_se_borra_solo_y_el_comando_falla(): void
    {
        $eventos = DB::connection('mongodb')->getCollection('eventos');
        $eventos->dropIndex('uuid_unico');
        $eventos->createIndex(['uuid' => 1], ['name' => 'uuid_unico']);  // quedó sin unique
        $eventos->dropIndex('ttl_365_dias');  // otro índice de la misma colección que falta
        DB::connection('mongodb')->getCollection('alertas')->dropIndex('zona_estado');  // y uno de otra colección

        $this->artisan('nexora:mongo-instalar')
            ->expectsOutputToContain('eventos.uuid_unico: MongoDB rechazó el cambio')
            ->expectsOutputToContain('alertas: validador e índices al día')  // las demás se instalan igual
            ->assertFailed();

        $this->assertArrayHasKey('ttl_365_dias', $this->indices('eventos'));  // el resto de los índices de eventos sí se crean
        $this->assertArrayHasKey('zona_estado', $this->indices('alertas'));   // y los de las demás colecciones
        $this->assertFalse($this->indices('eventos')['uuid_unico']->isUnique());  // el que cambió no se tocó: se arregla a mano
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
