<?php

namespace Tests\Feature\Porteria;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutorizacionApiTest extends TestCase
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
            'valida_desde' => now()->toIso8601String(),
            'valida_hasta' => now()->addDay()->toIso8601String(),
            ...$extra,
        ];
    }

    private function ajena(): Autorizacion
    {
        $otro = Residente::factory()->create();
        $unidad = Unidad::factory()->create();
        $otro->unidades()->attach($unidad);

        return Autorizacion::factory()->paraResidente($otro, $unidad)->create(['visitante_nombre' => 'Visita ajena']);
    }

    public function test_sin_token_es_401(): void
    {
        $this->getJson(route('api.v1.porteria.autorizaciones.index'))->assertUnauthorized();
        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos())->assertUnauthorized();
    }

    public function test_residente_solo_ve_las_de_sus_unidades(): void
    {
        Sanctum::actingAs($this->usuario);
        Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->count(2)->create();
        $this->ajena();

        $this->getJson(route('api.v1.porteria.autorizaciones.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.unidad.etiqueta', $this->unidad->etiqueta);
    }

    public function test_residente_sin_perfil_recibe_lista_vacia_y_no_un_500(): void
    {
        Autorizacion::factory()->create();
        Sanctum::actingAs(User::factory()->conRol(Rol::Residente)->create());

        $this->getJson('/api/v1/autorizaciones')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_ve_todas_paginadas_y_busca(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Admin)->create());
        Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->count(11)->create();
        $this->ajena();

        $this->getJson(route('api.v1.porteria.autorizaciones.index'))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 12);

        $this->getJson(route('api.v1.porteria.autorizaciones.index', ['search' => 'ajena']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_residente_crea_201(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos())
            ->assertCreated()
            ->assertJsonPath('data.estado', 'vigente')
            ->assertJsonPath('data.placa', 'ABC123')
            ->assertJsonPath('data.residente.id', $this->residente->id);
    }

    public function test_crear_invalido_es_422(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos([
            'visitante_nombre' => '',
            'valida_hasta' => now()->subDay()->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['visitante_nombre', 'valida_hasta']);

        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos([
            'valida_hasta' => now()->addDays(9)->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('valida_hasta');
    }

    public function test_crear_para_unidad_ajena_es_403(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos(['unidad_id' => Unidad::factory()->create()->id]))
            ->assertForbidden();
    }

    public function test_admin_crea_indicando_residente(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Admin)->create());

        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('residente_id');

        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos(['residente_id' => $this->residente->id]))
            ->assertCreated();
    }

    public function test_show_200_403_y_404(): void
    {
        Sanctum::actingAs($this->usuario);
        $propia = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create();

        $this->getJson(route('api.v1.porteria.autorizaciones.show', $propia))->assertOk()->assertJsonPath('data.id', $propia->id);
        $this->getJson(route('api.v1.porteria.autorizaciones.show', $this->ajena()))->assertForbidden();
        $this->getJson(route('api.v1.porteria.autorizaciones.show', 9999))->assertNotFound();
    }

    public function test_update_200_y_409_si_ya_se_uso(): void
    {
        Sanctum::actingAs($this->usuario);
        $vigente = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create();
        $usada = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->usada()->create();

        $this->putJson(route('api.v1.porteria.autorizaciones.update', $vigente), ['visitante_nombre' => 'Nuevo nombre'])
            ->assertOk()
            ->assertJsonPath('data.visitante_nombre', 'Nuevo nombre');

        $this->putJson(route('api.v1.porteria.autorizaciones.update', $usada), ['visitante_nombre' => 'X'])
            ->assertStatus(409);
    }

    public function test_update_ajena_es_403(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->putJson(route('api.v1.porteria.autorizaciones.update', $this->ajena()), ['visitante_nombre' => 'X'])
            ->assertForbidden();
    }

    public function test_destroy_anula_204_y_409_la_segunda_vez(): void
    {
        Sanctum::actingAs($this->usuario);
        $autorizacion = Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create();

        $this->deleteJson(route('api.v1.porteria.autorizaciones.destroy', $autorizacion))->assertNoContent();
        $this->assertSame(EstadoAutorizacion::Anulada, $autorizacion->fresh()->estado);

        $this->deleteJson(route('api.v1.porteria.autorizaciones.destroy', $autorizacion))->assertStatus(409);
    }

    public function test_destroy_ajena_403_e_inexistente_404(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->deleteJson(route('api.v1.porteria.autorizaciones.destroy', $this->ajena()))->assertForbidden();
        $this->deleteJson(route('api.v1.porteria.autorizaciones.destroy', 9999))->assertNotFound();
    }

    public function test_vigilante_solo_ve_vigentes_y_no_crea(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Vigilante)->create());
        Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create(['valida_desde' => now()->subHour()]);
        Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->usada()->create();

        $this->getJson(route('api.v1.porteria.autorizaciones.index'))->assertOk()->assertJsonCount(1, 'data');
        $usada = Autorizacion::where('estado', 'usada')->first();
        $this->getJson(route('api.v1.porteria.autorizaciones.show', $usada))->assertForbidden();
        $this->postJson(route('api.v1.porteria.autorizaciones.store'), $this->datos())->assertForbidden();
    }

    public function test_consejo_recibe_403(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Consejo)->create());

        $this->getJson(route('api.v1.porteria.autorizaciones.index'))->assertForbidden();
    }

    public function test_filtro_invalido_es_422(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Admin)->create());

        $this->getJson(route('api.v1.porteria.autorizaciones.index', ['estado' => 'inventado']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
    }
}
