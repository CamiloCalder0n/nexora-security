<?php

namespace Tests\Feature\Monitoreo;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZonaApiTest extends TestCase
{
    use RefreshDatabase;

    private function comoAdmin(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Admin)->create());
    }

    private function datos(array $extra = []): array
    {
        return [
            'nombre' => 'Parqueadero visitantes',
            'tipo' => 'parqueadero',
            'prioridad_base' => 2,
            'ventana_correlacion_min' => 10,
            'ventana_silencio_min' => 5,
            'activa' => true,
            'franjas' => [['dia_semana' => null, 'hora_inicio' => '18:00', 'hora_fin' => '06:00']],
            ...$extra,
        ];
    }

    public function test_sin_token_es_401(): void
    {
        $this->getJson(route('api.v1.monitoreo.zonas.index'))->assertUnauthorized();
    }

    public function test_vigilante_recibe_403(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Vigilante)->create());

        $this->getJson(route('api.v1.monitoreo.zonas.index'))->assertForbidden();
    }

    public function test_index_pagina_de_10_y_busca(): void
    {
        $this->comoAdmin();
        Zona::factory()->count(12)->create();
        Zona::factory()->create(['nombre' => 'Cuarto de basuras']);

        $this->getJson(route('api.v1.monitoreo.zonas.index'))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.per_page', 10);

        $this->getJson(route('api.v1.monitoreo.zonas.index', ['search' => 'basuras']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Cuarto de basuras');
    }

    public function test_store_crea_con_franjas_201(): void
    {
        $this->comoAdmin();

        $this->postJson(route('api.v1.monitoreo.zonas.store'), $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Parqueadero visitantes')
            ->assertJsonPath('data.franjas.0.hora_inicio', '18:00');

        $this->assertDatabaseHas('franjas', ['hora_fin' => '06:00']);
    }

    public function test_store_invalido_es_422(): void
    {
        $this->comoAdmin();

        $this->postJson(route('api.v1.monitoreo.zonas.store'), $this->datos(['tipo' => 'cocina', 'franjas' => [['hora_inicio' => '25:00', 'hora_fin' => '06:00']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo', 'franjas.0.hora_inicio']);
    }

    public function test_show_200_y_404(): void
    {
        $this->comoAdmin();
        $zona = Zona::factory()->create();

        $this->getJson(route('api.v1.monitoreo.zonas.show', $zona))
            ->assertOk()
            ->assertJsonPath('data.id', $zona->id)
            ->assertJsonStructure(['data' => ['franjas', 'camaras']]);

        $this->getJson(route('api.v1.monitoreo.zonas.show', 9999))->assertNotFound();
    }

    public function test_update_200_y_422(): void
    {
        $this->comoAdmin();
        $zona = Zona::factory()->create();
        $otra = Zona::factory()->create();

        $this->putJson(route('api.v1.monitoreo.zonas.update', $zona), ['prioridad_base' => 3, 'activa' => false])
            ->assertOk()
            ->assertJsonPath('data.prioridad_base', 3)
            ->assertJsonPath('data.activa', false);

        $this->putJson(route('api.v1.monitoreo.zonas.update', $zona), ['nombre' => $otra->nombre])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombre');
    }

    public function test_update_reemplaza_franjas(): void
    {
        $this->comoAdmin();
        $zona = $this->postJson(route('api.v1.monitoreo.zonas.store'), $this->datos())->json('data');

        $this->putJson(route('api.v1.monitoreo.zonas.update', $zona['id']), [
            'franjas' => [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '12:00']],
        ])->assertOk()->assertJsonCount(1, 'data.franjas')->assertJsonPath('data.franjas.0.dia_semana', 1);
    }

    public function test_destroy_204_y_404(): void
    {
        $this->comoAdmin();
        $zona = Zona::factory()->create();

        $this->deleteJson(route('api.v1.monitoreo.zonas.destroy', $zona))->assertNoContent();
        $this->assertDatabaseMissing('zonas', ['id' => $zona->id]);

        $this->deleteJson(route('api.v1.monitoreo.zonas.destroy', $zona))->assertNotFound();
    }

    public function test_destroy_con_camaras_es_409(): void
    {
        $this->comoAdmin();
        $zona = Zona::factory()->create();
        Camara::factory()->create(['zona_id' => $zona->id]);

        $this->deleteJson(route('api.v1.monitoreo.zonas.destroy', $zona))->assertStatus(409);
    }
}
