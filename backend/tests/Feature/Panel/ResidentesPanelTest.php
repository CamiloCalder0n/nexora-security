<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use App\Modules\Porteria\Domain\TipoResidente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentesPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->conRol(Rol::Admin)->create();
    }

    private function residenteEn(Unidad $unidad, bool $titular = true, array $atributos = []): Residente
    {
        $residente = Residente::factory()->create($atributos);
        $residente->unidades()->attach($unidad->id, ['es_titular' => $titular]);

        return $residente;
    }

    public function test_admin_ve_las_pantallas_de_residentes(): void
    {
        $residente = $this->residenteEn(Unidad::factory()->create());

        $this->actingAs($this->admin)->get(route('panel.porteria.residentes.index'))
            ->assertOk()->assertSee($residente->nombre);
        $this->actingAs($this->admin)->get(route('panel.porteria.residentes.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('panel.porteria.residentes.show', $residente))
            ->assertOk()->assertSee($residente->documento)->assertSee('Titular');
        $this->actingAs($this->admin)->get(route('panel.porteria.residentes.edit', $residente))
            ->assertOk()->assertSee($residente->nombre);
    }

    public function test_vigilante_y_residente_reciben_403(): void
    {
        $residente = $this->residenteEn(Unidad::factory()->create());

        foreach ([Rol::Vigilante, Rol::Residente] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get(route('panel.porteria.residentes.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('panel.porteria.residentes.show', $residente))->assertForbidden();
            $this->actingAs($usuario)->delete(route('panel.porteria.residentes.destroy', $residente))->assertForbidden();
        }

        $this->assertModelExists($residente);
    }

    public function test_filtra_por_busqueda_tipo_y_unidad(): void
    {
        $a = Unidad::factory()->create();
        $b = Unidad::factory()->create();
        $this->residenteEn($a, atributos: ['nombre' => 'Marta Prueba', 'tipo_residente' => TipoResidente::Propietario]);
        $this->residenteEn($b, atributos: ['nombre' => 'Jorge Otro', 'tipo_residente' => TipoResidente::Arrendatario]);

        $this->actingAs($this->admin)->get(route('panel.porteria.residentes.index', ['search' => 'marta']))
            ->assertOk()->assertSee('Marta Prueba')->assertDontSee('Jorge Otro');
        $this->actingAs($this->admin)->get(route('panel.porteria.residentes.index', ['tipo_residente' => 'arrendatario']))
            ->assertOk()->assertSee('Jorge Otro')->assertDontSee('Marta Prueba');
        $this->actingAs($this->admin)->get(route('panel.porteria.residentes.index', ['unidad_id' => $a->id]))
            ->assertOk()->assertSee('Marta Prueba')->assertDontSee('Jorge Otro');
    }

    public function test_filtro_invalido_redirige_al_listado_limpio(): void
    {
        $this->actingAs($this->admin)
            ->get(route('panel.porteria.residentes.index', ['tipo_residente' => 'inventado', 'unidad_id' => 9999]))
            ->assertRedirect(route('panel.porteria.residentes.index'))
            ->assertSessionHasErrors(['tipo_residente', 'unidad_id']);

        $this->actingAs($this->admin)->followingRedirects()
            ->get(route('panel.porteria.residentes.index', ['unidad_id' => 'abc']))
            ->assertOk()
            ->assertSee('Algún filtro no era válido; se muestran todos los registros.');
    }

    public function test_crea_residente_con_unidades_y_titularidad(): void
    {
        [$a, $b] = Unidad::factory()->count(2)->create();

        $respuesta = $this->actingAs($this->admin)->post(route('panel.porteria.residentes.store'), [
            'nombre' => 'Lucía Gómez',
            'documento' => '1020304050',
            'telefono' => '3001112233',
            'tipo_residente' => 'arrendatario',
            'unidades' => [$a->id, $b->id],
            'titular_de' => [$b->id],
        ]);

        $residente = Residente::where('documento', '1020304050')->firstOrFail();
        $respuesta->assertRedirect(route('panel.porteria.residentes.show', $residente))->assertSessionHas('exito');
        $this->assertSame(TipoResidente::Arrendatario, $residente->tipo_residente);
        $this->assertNull($residente->user_id);
        $this->assertDatabaseHas('unidad_residente', ['residente_id' => $residente->id, 'unidad_id' => $a->id, 'es_titular' => false]);
        $this->assertDatabaseHas('unidad_residente', ['residente_id' => $residente->id, 'unidad_id' => $b->id, 'es_titular' => true]);
    }

    public function test_validacion_al_crear(): void
    {
        $unidad = Unidad::factory()->create();
        $existente = $this->residenteEn($unidad);

        $this->actingAs($this->admin)
            ->from(route('panel.porteria.residentes.create'))
            ->post(route('panel.porteria.residentes.store'), [
                'nombre' => '',
                'documento' => $existente->documento,
                'tipo_residente' => 'otro',
                'unidades' => [],
            ])
            ->assertRedirect(route('panel.porteria.residentes.create'))
            ->assertSessionHasErrors(['nombre', 'documento', 'tipo_residente', 'unidades']);

        $this->actingAs($this->admin)->post(route('panel.porteria.residentes.store'), [
            'nombre' => 'Sin Titularidad Válida',
            'documento' => '999888777',
            'tipo_residente' => 'propietario',
            'unidades' => [$unidad->id],
            'titular_de' => [$unidad->id + 100],
        ])->assertSessionHasErrors('titular_de.0');

        $this->assertSame(1, Residente::count());
    }

    public function test_actualiza_datos_y_unidades(): void
    {
        [$a, $b] = Unidad::factory()->count(2)->create();
        $residente = $this->residenteEn($a);

        $this->actingAs($this->admin)->put(route('panel.porteria.residentes.update', $residente), [
            'nombre' => 'Nombre Nuevo',
            'documento' => $residente->documento,
            'telefono' => '',
            'tipo_residente' => 'propietario',
            'unidades' => [$b->id],
        ])->assertRedirect(route('panel.porteria.residentes.show', $residente))->assertSessionHas('exito');

        $residente->refresh();
        $this->assertSame('Nombre Nuevo', $residente->nombre);
        $this->assertNull($residente->telefono);
        $this->assertSame([$b->id], $residente->unidades->pluck('id')->all());
        $this->assertFalse((bool) $residente->unidades->first()->pivot->es_titular);
    }

    public function test_actualizar_con_documento_de_otro_falla(): void
    {
        $unidad = Unidad::factory()->create();
        $residente = $this->residenteEn($unidad);
        $otro = $this->residenteEn($unidad, false);

        $this->actingAs($this->admin)->put(route('panel.porteria.residentes.update', $residente), [
            'nombre' => $residente->nombre,
            'documento' => $otro->documento,
            'tipo_residente' => 'propietario',
            'unidades' => [$unidad->id],
        ])->assertSessionHasErrors('documento');
    }

    public function test_elimina_residente_sin_historial(): void
    {
        $residente = $this->residenteEn(Unidad::factory()->create());

        $this->actingAs($this->admin)->delete(route('panel.porteria.residentes.destroy', $residente))
            ->assertRedirect(route('panel.porteria.residentes.index'))
            ->assertSessionHas('exito');

        $this->assertModelMissing($residente);
    }

    public function test_no_elimina_residente_con_historial_y_muestra_el_error(): void
    {
        $unidad = Unidad::factory()->create();
        $residente = $this->residenteEn($unidad);
        Autorizacion::factory()->paraResidente($residente, $unidad)->create();
        $indice = route('panel.porteria.residentes.index');

        $this->actingAs($this->admin)->from($indice)
            ->delete(route('panel.porteria.residentes.destroy', $residente))
            ->assertRedirect($indice)
            ->assertSessionHasErrors(['regla' => 'El residente tiene autorizaciones registradas y no se puede eliminar.'])
            ->assertSessionMissing('exito');

        $this->assertModelExists($residente);

        $this->actingAs($this->admin)->from($indice)->followingRedirects()
            ->delete(route('panel.porteria.residentes.destroy', $residente))
            ->assertOk()
            ->assertSee('El residente tiene autorizaciones registradas y no se puede eliminar.')
            ->assertDontSee('eliminado.');
    }
}
