<?php

namespace Tests\Feature\Monitoreo;

use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\BulkWriteException;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class IndicesMongoTest extends TestCase
{
    use LimpiaMongo;

    private function indices(string $coleccion): array
    {
        $indices = [];
        foreach (DB::connection('mongodb')->getCollection($coleccion)->listIndexes() as $indice) {
            $indices[$indice->getName()] = $indice;
        }

        return $indices;
    }

    public function test_crea_los_indices_y_se_puede_correr_dos_veces(): void
    {
        $this->artisan('nexora:mongo-indices')->assertSuccessful();
        $this->artisan('nexora:mongo-indices')->assertSuccessful();

        $eventos = $this->indices('eventos');
        $this->assertTrue($eventos['uuid_unico']->isUnique());
        $this->assertSame(365 * 24 * 60 * 60, $eventos['ttl_365_dias']['expireAfterSeconds']);
        $this->assertArrayHasKey('zona_fecha', $eventos);
        $this->assertArrayHasKey('estado_fecha', $this->indices('alertas'));
        $this->assertArrayHasKey('zona_estado', $this->indices('alertas'));
        $this->assertArrayHasKey('fecha', $this->indices('bitacora'));
        $this->assertTrue($this->indices('bitacora')['secuencia_unica']->isUnique());
    }

    public function test_el_uuid_del_evento_no_se_repite(): void
    {
        $this->artisan('nexora:mongo-indices');
        $datos = ['uuid' => '6f1c2b9e-3a4d-4c55-9e0a-1b2c3d4e5f60', 'fecha_hora' => now(), 'tipo_objeto' => 'persona'];

        $evento = Evento::create($datos);
        $this->assertSame(ResultadoEvento::Pendiente, $evento->fresh()->resultado);
        $this->assertSame(Evento::SCHEMA_VERSION, $evento->fresh()->schema_version);

        $this->expectException(BulkWriteException::class);
        Evento::create($datos);
    }
}
