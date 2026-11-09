<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentePanelTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Residente $residente;

    private Unidad $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->conRol(Rol::Residente)->create();
        $this->residente = Residente::factory()->create(['user_id' => $this->usuario->id]);
        $this->unidad = Unidad::factory()->create();
        $this->residente->unidades()->attach($this->unidad, ['es_titular' => true]);
    }

    private function datos(array $extra = []): array
    {
        return [
            'unidad_id' => $this->unidad->id,
            'visitante_nombre' => 'Carlos Ruiz',
            'visitante_documento' => '1020304050',
            'placa' => 'abc123',
            'valida_desde' => now()->format('Y-m-d\TH:i'),
            'valida_hasta' => now()->addDay()->format('Y-m-d\TH:i'),
            ...$extra,
        ];
    }

    /** @return array{0: Residente, 1: Unidad} */
    private function otroResidente(): array
    {
        $otro = Residente::factory()->conCuenta()->create();
        $unidad = Unidad::factory()->create();
        $otro->unidades()->attach($unidad);

        return [$otro, $unidad];
    }

    public function test_el_residente_ve_sus_tres_pantallas(): void
    {
        $this->actingAs($this->usuario);

        $this->get(route('panel.porteria.mis-autorizaciones.index'))->assertOk()->assertSee('Nueva autorización');
        $this->get(route('panel.porteria.mis-autorizaciones.create'))->assertOk()->assertSee('Máximo 7 días');
        $this->get(route('panel.porteria.mis-visitas.index'))->assertOk();
    }

    public function test_el_vigilante_no_entra_a_las_pantallas_del_residente(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Vigilante)->create());

        $this->get(route('panel.porteria.mis-autorizaciones.index'))->assertForbidden();
        $this->get(route('panel.porteria.mis-autorizaciones.create'))->assertForbidden();
        $this->post(route('panel.porteria.mis-autorizaciones.store'), $this->datos())->assertForbidden();
        $this->get(route('panel.porteria.mis-visitas.index'))->assertForbidden();
    }

    public function test_usuario_residente_sin_registro_ve_un_mensaje_y_no_un_error(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Residente)->create());

        $this->get(route('panel.porteria.mis-autorizaciones.index'))->assertOk()->assertSee('no está vinculada a un residente');
        $this->get(route('panel.porteria.mis-visitas.index'))->assertOk()->assertSee('no está vinculada a un residente');
        $this->get(route('panel.porteria.mis-autorizaciones.create'))->assertRedirect(route('panel.porteria.mis-autorizaciones.index'));
        $this->post(route('panel.porteria.mis-autorizaciones.store'), $this->datos())->assertRedirect(route('panel.porteria.mis-autorizaciones.index'));
        $this->assertDatabaseCount('autorizaciones', 0);
    }

    public function test_el_formulario_solo_ofrece_sus_unidades(): void
    {
        [, $ajena] = $this->otroResidente();

        $this->actingAs($this->usuario)
            ->get(route('panel.porteria.mis-autorizaciones.create'))
            ->assertSee($this->unidad->etiqueta)
            ->assertDontSee($ajena->etiqueta);
    }

    public function test_crea_una_autorizacion_para_su_unidad(): void
    {
        $this->actingAs($this->usuario)
            ->post(route('panel.porteria.mis-autorizaciones.store'), $this->datos())
            ->assertRedirect(route('panel.porteria.mis-autorizaciones.index'))
            ->assertSessionHas('exito');

        $this->assertDatabaseHas('autorizaciones', [
            'unidad_id' => $this->unidad->id,
            'residente_id' => $this->residente->id,
            'visitante_nombre' => 'Carlos Ruiz',
            'placa' => 'ABC123',
            'estado' => EstadoAutorizacion::Vigente->value,
        ]);
    }

    public function test_no_crea_para_una_unidad_ajena(): void
    {
        [, $ajena] = $this->otroResidente();

        $this->actingAs($this->usuario)
            ->post(route('panel.porteria.mis-autorizaciones.store'), $this->datos(['unidad_id' => $ajena->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('autorizaciones', 0);
    }

    public function test_no_crea_una_autorizacion_de_mas_de_siete_dias(): void
    {
        $this->actingAs($this->usuario)
            ->from(route('panel.porteria.mis-autorizaciones.create'))
            ->post(route('panel.porteria.mis-autorizaciones.store'), $this->datos([
                'valida_hasta' => now()->addDays(8)->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect(route('panel.porteria.mis-autorizaciones.create'))
            ->assertSessionHasErrors('valida_hasta');

        $this->assertDatabaseCount('autorizaciones', 0);
    }

    public function test_valida_los_campos_requeridos(): void
    {
        $this->actingAs($this->usuario)
            ->from(route('panel.porteria.mis-autorizaciones.create'))
            ->post(route('panel.porteria.mis-autorizaciones.store'), [])
            ->assertRedirect(route('panel.porteria.mis-autorizaciones.create'))
            ->assertSessionHasErrors(['unidad_id', 'visitante_nombre', 'visitante_documento', 'valida_desde', 'valida_hasta']);
    }

    public function test_anula_su_autorizacion_vigente(): void
    {
        $autorizacion = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create();

        $this->actingAs($this->usuario)
            ->delete(route('panel.porteria.mis-autorizaciones.destroy', $autorizacion))
            ->assertRedirect(route('panel.porteria.mis-autorizaciones.index'))
            ->assertSessionHas('exito');

        $this->assertSame(EstadoAutorizacion::Anulada, $autorizacion->fresh()->estado);
    }

    public function test_no_anula_una_autorizacion_que_ya_no_esta_vigente(): void
    {
        $autorizacion = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->usada()->create();

        $this->actingAs($this->usuario)
            ->from(route('panel.porteria.mis-autorizaciones.index'))
            ->delete(route('panel.porteria.mis-autorizaciones.destroy', $autorizacion))
            ->assertRedirect(route('panel.porteria.mis-autorizaciones.index'))
            ->assertSessionHasErrors('regla');

        $this->assertSame(EstadoAutorizacion::Usada, $autorizacion->fresh()->estado);
    }

    public function test_no_anula_la_autorizacion_de_otro_residente(): void
    {
        [$otro, $unidad] = $this->otroResidente();
        $ajena = Autorizacion::factory()->paraResidente($otro, $unidad)->create();

        $this->actingAs($this->usuario)
            ->delete(route('panel.porteria.mis-autorizaciones.destroy', $ajena))
            ->assertForbidden();

        $this->assertSame(EstadoAutorizacion::Vigente, $ajena->fresh()->estado);
    }

    public function test_el_listado_solo_muestra_sus_autorizaciones_y_filtra_por_estado(): void
    {
        [$otro, $unidad] = $this->otroResidente();
        Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create(['visitante_nombre' => 'Visita vigente']);
        Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->anulada()->create(['visitante_nombre' => 'Visita anulada']);
        Autorizacion::factory()->paraResidente($otro, $unidad)->create(['visitante_nombre' => 'Visita ajena']);

        $this->actingAs($this->usuario)
            ->get(route('panel.porteria.mis-autorizaciones.index'))
            ->assertSee('Visita vigente')
            ->assertSee('Visita anulada')
            ->assertDontSee('Visita ajena');

        $this->get(route('panel.porteria.mis-autorizaciones.index', ['estado' => 'anulada']))
            ->assertSee('Visita anulada')
            ->assertDontSee('Visita vigente');
    }

    public function test_mis_visitas_solo_muestra_ingresos_a_sus_unidades(): void
    {
        [, $unidad] = $this->otroResidente();
        RegistroPorteria::factory()->create(['unidad_id' => $this->unidad->id, 'nombre_visitante' => 'Ingreso propio', 'fecha_ingreso' => now()->subDay()]);
        RegistroPorteria::factory()->create(['unidad_id' => $this->unidad->id, 'nombre_visitante' => 'Ingreso antiguo', 'fecha_ingreso' => now()->subDays(20)]);
        RegistroPorteria::factory()->create(['unidad_id' => $unidad->id, 'nombre_visitante' => 'Ingreso ajeno']);

        $this->actingAs($this->usuario)
            ->get(route('panel.porteria.mis-visitas.index'))
            ->assertOk()
            ->assertSee('Ingreso propio')
            ->assertSee('Ingreso antiguo')
            ->assertDontSee('Ingreso ajeno');

        $this->get(route('panel.porteria.mis-visitas.index', ['desde' => now()->subDays(3)->toDateString()]))
            ->assertSee('Ingreso propio')
            ->assertDontSee('Ingreso antiguo');
    }

    public function test_filtro_invalido_da_422_en_json_y_redirige_limpio_en_web(): void
    {
        $this->actingAs($this->usuario);

        $this->getJson(route('panel.porteria.mis-autorizaciones.index', ['estado' => 'inventado']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');
        $this->getJson(route('panel.porteria.mis-visitas.index', ['desde' => 'ayer-no']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('desde');

        $this->get(route('panel.porteria.mis-autorizaciones.index', ['estado' => 'inventado']))
            ->assertRedirect(route('panel.porteria.mis-autorizaciones.index'))
            ->assertSessionHas('error');
        $this->get(route('panel.porteria.mis-visitas.index', ['desde' => '2026-11-08', 'hasta' => '2026-11-01']))
            ->assertRedirect(route('panel.porteria.mis-visitas.index'))
            ->assertSessionHas('error');
    }
}
