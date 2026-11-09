<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Domain\TipoZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZonasPanelTest extends TestCase
{
    use RefreshDatabase;

    private function comoAdmin(): User
    {
        $admin = User::factory()->conRol(Rol::Admin)->create();
        $this->actingAs($admin);

        return $admin;
    }

    private function datos(array $extra = []): array
    {
        return [
            'nombre' => 'Parqueadero visitantes',
            'tipo' => 'parqueadero',
            'prioridad_base' => 2,
            'ventana_correlacion_min' => 10,
            'ventana_silencio_min' => 5,
            'activa' => '1',
            'franjas' => [
                ['dia_semana' => '', 'hora_inicio' => '18:00', 'hora_fin' => '06:00'],
                ['dia_semana' => '6', 'hora_inicio' => '08:00', 'hora_fin' => '12:00'],
            ],
            ...$extra,
        ];
    }

    public function test_invitado_va_al_login(): void
    {
        $this->get(route('panel.monitoreo.zonas.index'))->assertRedirect(route('login'));
    }

    public function test_vigilante_recibe_403_en_todas_las_pantallas(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Vigilante)->create());
        $zona = Zona::factory()->create();

        $this->get(route('panel.monitoreo.zonas.index'))->assertForbidden();
        $this->get(route('panel.monitoreo.zonas.create'))->assertForbidden();
        $this->get(route('panel.monitoreo.zonas.show', $zona))->assertForbidden();
        $this->get(route('panel.monitoreo.zonas.edit', $zona))->assertForbidden();
        $this->post(route('panel.monitoreo.zonas.store'), $this->datos())->assertForbidden();
        $this->delete(route('panel.monitoreo.zonas.destroy', $zona))->assertForbidden();
        $this->assertDatabaseHas('zonas', ['id' => $zona->id]);
    }

    public function test_index_lista_busca_y_filtra(): void
    {
        $this->comoAdmin();
        $porteria = Zona::factory()->create(['nombre' => 'Portería principal', 'tipo' => TipoZona::AccesoPeatonal]);
        Camara::factory()->count(2)->create(['zona_id' => $porteria->id]);
        Zona::factory()->inactiva()->create(['nombre' => 'Cuarto de basuras', 'tipo' => TipoZona::Servicio]);

        $this->get(route('panel.monitoreo.zonas.index'))
            ->assertOk()
            ->assertSee('Portería principal')
            ->assertSee('Cuarto de basuras')
            ->assertSee('Nueva zona');

        $this->get(route('panel.monitoreo.zonas.index', ['search' => 'basuras']))
            ->assertOk()->assertSee('Cuarto de basuras')->assertDontSee('Portería principal');

        $this->get(route('panel.monitoreo.zonas.index', ['tipo' => 'acceso_peatonal']))
            ->assertOk()->assertSee('Portería principal')->assertDontSee('Cuarto de basuras');

        $this->get(route('panel.monitoreo.zonas.index', ['activa' => '0']))
            ->assertOk()->assertSee('Cuarto de basuras')->assertDontSee('Portería principal');
    }

    public function test_index_pagina_de_10(): void
    {
        $this->comoAdmin();
        Zona::factory()->count(12)->create();

        $this->get(route('panel.monitoreo.zonas.index'))->assertOk()->assertSee('Mostrando 1–10 de 12');
        $this->get(route('panel.monitoreo.zonas.index', ['page' => 2]))->assertOk()->assertSee('Mostrando 11–12 de 12');
    }

    public function test_filtro_invalido_redirige_al_index_limpio_con_errores(): void
    {
        $this->comoAdmin();

        $this->get(route('panel.monitoreo.zonas.index', ['tipo' => 'inventado']))
            ->assertRedirect(route('panel.monitoreo.zonas.index'))
            ->assertSessionHasErrors('tipo');

        $this->get(route('panel.monitoreo.zonas.index', ['activa' => 'quizas', 'page' => 'x']))
            ->assertRedirect(route('panel.monitoreo.zonas.index'))
            ->assertSessionHasErrors(['activa', 'page']);

        $this->followingRedirects()
            ->get(route('panel.monitoreo.zonas.index', ['tipo' => 'inventado']))
            ->assertOk()
            ->assertSee('Se limpiaron los filtros');
    }

    public function test_create_y_show_y_edit_responden_200(): void
    {
        $this->comoAdmin();
        $zona = Zona::factory()->create(['nombre' => 'Perímetro norte']);
        $zona->franjas()->create(['dia_semana' => null, 'hora_inicio' => '22:00', 'hora_fin' => '05:00']);
        Camara::factory()->create(['zona_id' => $zona->id, 'nombre' => 'CAM-NORTE']);

        $this->get(route('panel.monitoreo.zonas.create'))->assertOk()->assertSee('Franjas de vigilancia');

        $this->get(route('panel.monitoreo.zonas.show', $zona))
            ->assertOk()
            ->assertSee('Perímetro norte')
            ->assertSee('Cruza la medianoche')
            ->assertSee('CAM-NORTE');

        $this->get(route('panel.monitoreo.zonas.edit', $zona))
            ->assertOk()
            ->assertSee('value="22:00"', false);
    }

    public function test_crear_editar_y_eliminar(): void
    {
        $this->comoAdmin();

        $this->post(route('panel.monitoreo.zonas.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('exito');

        $zona = Zona::where('nombre', 'Parqueadero visitantes')->firstOrFail();
        $this->assertCount(2, $zona->franjas);
        $this->assertDatabaseHas('franjas', ['zona_id' => $zona->id, 'dia_semana' => null, 'hora_inicio' => '18:00']);

        $this->put(route('panel.monitoreo.zonas.update', $zona), $this->datos([
            'nombre' => 'Parqueadero de visitantes',
            'prioridad_base' => 3,
            'activa' => null,
            'franjas' => [['dia_semana' => '1', 'hora_inicio' => '07:00', 'hora_fin' => '09:00']],
        ]))
            ->assertRedirect(route('panel.monitoreo.zonas.show', $zona))
            ->assertSessionHas('exito');

        $zona->refresh();
        $this->assertSame('Parqueadero de visitantes', $zona->nombre);
        $this->assertSame(3, $zona->prioridad_base);
        $this->assertFalse($zona->activa);
        $this->assertCount(1, $zona->franjas);
        $this->assertSame(1, $zona->franjas->first()->dia_semana);

        $this->put(route('panel.monitoreo.zonas.update', $zona), $this->datos(['nombre' => $zona->nombre, 'franjas' => []]))
            ->assertSessionHasNoErrors();
        $this->assertCount(0, $zona->fresh()->franjas);

        $this->from(route('panel.monitoreo.zonas.index'))
            ->delete(route('panel.monitoreo.zonas.destroy', $zona))
            ->assertRedirect(route('panel.monitoreo.zonas.index'))
            ->assertSessionHas('exito');
        $this->assertDatabaseMissing('zonas', ['id' => $zona->id]);
    }

    public function test_eliminar_zona_en_uso_pide_desactivarla(): void
    {
        $this->comoAdmin();
        $zona = Zona::factory()->create();
        Camara::factory()->create(['zona_id' => $zona->id]);

        $this->from(route('panel.monitoreo.zonas.index'))
            ->delete(route('panel.monitoreo.zonas.destroy', $zona))
            ->assertRedirect(route('panel.monitoreo.zonas.index'))
            ->assertSessionHasErrors('zona');

        $this->assertDatabaseHas('zonas', ['id' => $zona->id]);

        $this->followingRedirects()
            ->from(route('panel.monitoreo.zonas.index'))
            ->delete(route('panel.monitoreo.zonas.destroy', $zona))
            ->assertSee('desactívela en lugar de eliminarla');
    }

    public function test_validacion_al_crear(): void
    {
        $this->comoAdmin();
        Zona::factory()->create(['nombre' => 'Portería']);

        $this->from(route('panel.monitoreo.zonas.create'))
            ->post(route('panel.monitoreo.zonas.store'), $this->datos([
                'nombre' => 'Portería',
                'tipo' => 'cocina',
                'prioridad_base' => 9,
                'franjas' => [['dia_semana' => '9', 'hora_inicio' => '25:00', 'hora_fin' => '06:00']],
            ]))
            ->assertRedirect(route('panel.monitoreo.zonas.create'))
            ->assertSessionHasErrors(['nombre', 'tipo', 'prioridad_base', 'franjas.0.dia_semana', 'franjas.0.hora_inicio']);

        $this->assertDatabaseCount('zonas', 1);
    }

    public function test_filas_de_franja_vacias_se_ignoran(): void
    {
        $this->comoAdmin();

        $this->post(route('panel.monitoreo.zonas.store'), $this->datos([
            'franjas' => [['dia_semana' => '', 'hora_inicio' => '', 'hora_fin' => '']],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('franjas', 0);
    }
}
