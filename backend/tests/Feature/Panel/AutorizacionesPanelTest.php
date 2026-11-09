<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorizacionesPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Residente $residente;

    private Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->conRol(Rol::Admin)->create();
        $this->residente = Residente::factory()->create(['nombre' => 'Laura Gómez']);
        $this->unidad = Unidad::factory()->create();
        $this->residente->unidades()->attach($this->unidad, ['es_titular' => true]);
    }

    private function datos(array $extra = []): array
    {
        return [
            'residente_id' => $this->residente->id,
            'unidad_id' => $this->unidad->id,
            'visitante_nombre' => 'Carlos Ruiz',
            'visitante_documento' => '1020304050',
            'placa' => 'abc123',
            'valida_desde' => now()->format('Y-m-d\TH:i'),
            'valida_hasta' => now()->addDay()->format('Y-m-d\TH:i'),
            ...$extra,
        ];
    }

    private function autorizacion(array $atributos = []): Autorizacion
    {
        return Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create($atributos);
    }

    public function test_admin_ve_listado_creacion_detalle_y_edicion(): void
    {
        $autorizacion = $this->autorizacion(['visitante_nombre' => 'Pedro Pérez']);

        $this->actingAs($this->admin);

        $this->get(route('panel.porteria.autorizaciones.index'))
            ->assertOk()
            ->assertSee('Pedro Pérez')
            ->assertSee('Laura Gómez')
            ->assertSee('Anular');
        $this->get(route('panel.porteria.autorizaciones.create'))->assertOk()->assertSee('Laura Gómez');
        $this->get(route('panel.porteria.autorizaciones.show', $autorizacion))->assertOk()->assertSee('Pedro Pérez');
        $this->get(route('panel.porteria.autorizaciones.edit', $autorizacion))->assertOk();
    }

    public function test_listado_filtra_por_estado_unidad_y_busqueda(): void
    {
        $this->autorizacion(['visitante_nombre' => 'Visita Vigente']);
        $this->autorizacion(['visitante_nombre' => 'Visita Anulada', 'estado' => EstadoAutorizacion::Anulada]);
        $otraUnidad = Unidad::factory()->create();
        $this->residente->unidades()->attach($otraUnidad);
        Autorizacion::factory()->paraResidente($this->residente, $otraUnidad)->create(['visitante_nombre' => 'Visita Otra Unidad']);

        $this->actingAs($this->admin);

        $this->get(route('panel.porteria.autorizaciones.index', ['estado' => 'anulada']))
            ->assertOk()->assertSee('Visita Anulada')->assertDontSee('Visita Vigente');
        $this->get(route('panel.porteria.autorizaciones.index', ['unidad_id' => $otraUnidad->id]))
            ->assertOk()->assertSee('Visita Otra Unidad')->assertDontSee('Visita Anulada');
        $this->get(route('panel.porteria.autorizaciones.index', ['search' => 'vigente']))
            ->assertOk()->assertSee('Visita Vigente')->assertDontSee('Visita Otra Unidad');
    }

    public function test_no_vigente_no_muestra_editar_ni_anular(): void
    {
        $usada = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->usada()->create();

        $this->actingAs($this->admin)
            ->get(route('panel.porteria.autorizaciones.show', $usada))
            ->assertOk()
            ->assertDontSee(route('panel.porteria.autorizaciones.edit', $usada))
            ->assertDontSee('Anular');
    }

    public function test_vigilante_y_residente_reciben_403(): void
    {
        $autorizacion = $this->autorizacion();

        foreach ([Rol::Vigilante, Rol::Residente] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create());

            $this->get(route('panel.porteria.autorizaciones.index'))->assertForbidden();
            $this->get(route('panel.porteria.autorizaciones.create'))->assertForbidden();
            $this->post(route('panel.porteria.autorizaciones.store'), $this->datos())->assertForbidden();
            $this->delete(route('panel.porteria.autorizaciones.destroy', $autorizacion))->assertForbidden();
        }

        $this->assertSame(EstadoAutorizacion::Vigente, $autorizacion->fresh()->estado);
    }

    public function test_crea_autorizacion(): void
    {
        $respuesta = $this->actingAs($this->admin)
            ->post(route('panel.porteria.autorizaciones.store'), $this->datos());

        $autorizacion = Autorizacion::firstOrFail();
        $respuesta->assertRedirect(route('panel.porteria.autorizaciones.show', $autorizacion))
            ->assertSessionHas('exito');
        $this->assertSame('ABC123', $autorizacion->placa);
        $this->assertSame($this->residente->id, $autorizacion->residente_id);
        $this->assertSame(EstadoAutorizacion::Vigente, $autorizacion->estado);
    }

    public function test_crear_valida_campos_y_unidad_del_residente(): void
    {
        $this->actingAs($this->admin);
        $crear = route('panel.porteria.autorizaciones.create');

        $this->from($crear)->post(route('panel.porteria.autorizaciones.store'), $this->datos([
            'residente_id' => '',
            'visitante_nombre' => '',
            'valida_hasta' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))->assertRedirect($crear)->assertSessionHasErrors(['residente_id', 'visitante_nombre', 'valida_hasta']);

        $this->from($crear)->post(route('panel.porteria.autorizaciones.store'), $this->datos([
            'valida_hasta' => now()->addDays(9)->format('Y-m-d\TH:i'),
        ]))->assertRedirect($crear)->assertSessionHasErrors('valida_hasta');

        $this->from($crear)->post(route('panel.porteria.autorizaciones.store'), $this->datos([
            'unidad_id' => Unidad::factory()->create()->id,
        ]))->assertRedirect($crear)->assertSessionHasErrors('unidad_id');

        $this->assertSame(0, Autorizacion::count());
    }

    public function test_edita_autorizacion_vigente(): void
    {
        $autorizacion = $this->autorizacion();

        $this->actingAs($this->admin)
            ->put(route('panel.porteria.autorizaciones.update', $autorizacion), [
                'visitante_nombre' => 'Nuevo Nombre',
                'visitante_documento' => '999',
                'placa' => 'xyz987',
                'valida_desde' => now()->format('Y-m-d\TH:i'),
                'valida_hasta' => now()->addHours(5)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('panel.porteria.autorizaciones.show', $autorizacion))
            ->assertSessionHas('exito');

        $autorizacion->refresh();
        $this->assertSame('Nuevo Nombre', $autorizacion->visitante_nombre);
        $this->assertSame('XYZ987', $autorizacion->placa);
    }

    public function test_editar_usada_muestra_error_de_regla(): void
    {
        $usada = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->usada()->create();
        $editar = route('panel.porteria.autorizaciones.edit', $usada);

        $this->actingAs($this->admin)
            ->from($editar)
            ->put(route('panel.porteria.autorizaciones.update', $usada), ['visitante_nombre' => 'X', 'visitante_documento' => '1'])
            ->assertRedirect($editar)
            ->assertSessionHasErrors('regla');
    }

    public function test_anula_y_anular_de_nuevo_muestra_error(): void
    {
        $autorizacion = $this->autorizacion();
        $detalle = route('panel.porteria.autorizaciones.show', $autorizacion);
        $this->actingAs($this->admin);

        $this->delete(route('panel.porteria.autorizaciones.destroy', $autorizacion))
            ->assertRedirect(route('panel.porteria.autorizaciones.index'))
            ->assertSessionHas('exito');
        $this->assertSame(EstadoAutorizacion::Anulada, $autorizacion->fresh()->estado);
        $this->assertDatabaseHas('autorizaciones', ['id' => $autorizacion->id]);

        $this->from($detalle)->delete(route('panel.porteria.autorizaciones.destroy', $autorizacion))
            ->assertRedirect($detalle)
            ->assertSessionHasErrors(['regla' => 'Solo se puede anular una autorización vigente.']);

        $this->from($detalle)->followingRedirects()
            ->delete(route('panel.porteria.autorizaciones.destroy', $autorizacion))
            ->assertOk()
            ->assertSee('Solo se puede anular una autorización vigente.');
    }

    public function test_filtro_invalido_redirige_al_listado_limpio_con_error(): void
    {
        $this->actingAs($this->admin);
        $listado = route('panel.porteria.autorizaciones.index');

        foreach ([['estado' => 'inventado'], ['unidad_id' => 9999], ['search' => str_repeat('a', 101)]] as $filtro) {
            $this->from(route('panel.porteria.autorizaciones.index', $filtro))
                ->get(route('panel.porteria.autorizaciones.index', $filtro))
                ->assertRedirect($listado)
                ->assertSessionHasErrors(array_keys($filtro));
        }

        $this->followingRedirects()
            ->get(route('panel.porteria.autorizaciones.index', ['estado' => 'inventado']))
            ->assertOk()
            ->assertSee('El filtro no es válido');
    }
}
