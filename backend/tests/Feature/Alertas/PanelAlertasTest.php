<?php

namespace Tests\Feature\Alertas;

use App\Models\User;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class PanelAlertasTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private User $vigilante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vigilante = User::factory()->conRol(Rol::Vigilante)->create(['name' => 'Pedro Celador']);
        Sanctum::actingAs($this->vigilante);
    }

    public function test_la_cola_trae_las_no_cerradas_primero_las_mas_urgentes_y_antiguas(): void
    {
        $media = $this->crearAlerta(['prioridad' => 2, 'fecha_hora' => now()->subMinutes(30)]);
        $altaNueva = $this->crearAlerta(['prioridad' => 3, 'fecha_hora' => now()->subMinutes(5)]);
        $altaVieja = $this->crearAlerta(['prioridad' => 3, 'fecha_hora' => now()->subMinutes(20)]);
        $atendida = $this->crearAlerta(['prioridad' => 3, 'fecha_hora' => now()->subHour(), 'estado' => 'atendida', 'atendida_por' => ['usuario_id' => 1, 'nombre' => 'Ana'], 'fecha_atencion' => now()]);
        $this->crearAlerta(['estado' => 'cerrada', 'clasificacion' => 'falsa_alarma', 'fecha_cierre' => now()]);

        $respuesta = $this->getJson(route('api.v1.alertas.index'))->assertOk();

        $this->assertSame([$altaVieja->id, $altaNueva->id, $media->id, $atendida->id], array_column($respuesta->json('data'), 'id'));
        $respuesta->assertJsonPath('data.3.atendida_por', 'Ana')
            ->assertJsonPath('data.0.zona', 'Parqueadero')
            ->assertJsonMissingPath('data.0.evidencias_recientes');
    }

    public function test_atender_desde_el_panel_cambia_el_estado_y_queda_en_la_bitacora(): void
    {
        $alerta = $this->crearAlerta();

        $this->postJson(route('api.v1.alertas.atender', $alerta->id))
            ->assertOk()
            ->assertJsonPath('data.estado', 'atendida')
            ->assertJsonPath('data.atendida_por', 'Pedro Celador');

        $this->assertSame('alerta_atendida', EntradaBitacora::first()->tipo);
    }

    public function test_si_otro_ya_la_atendio_responde_409_con_el_motivo(): void
    {
        $alerta = $this->crearAlerta();
        $this->postJson(route('api.v1.alertas.atender', $alerta->id))->assertOk();

        $this->postJson(route('api.v1.alertas.atender', $alerta->id))
            ->assertStatus(409)
            ->assertJsonPath('message', 'No se puede atender una alerta atendida.');
    }

    public function test_si_la_zona_esta_ocupada_responde_503_y_pide_reintentar(): void
    {
        $alerta = $this->crearAlerta();
        $lock = Cache::lock('alertas:zona:1', 10);
        $lock->get();

        try {
            $this->postJson(route('api.v1.alertas.atender', $alerta->id))
                ->assertStatus(503)
                ->assertHeader('Retry-After', '2')
                ->assertJsonPath('message', 'La alerta se está actualizando; intenta de nuevo.');
        } finally {
            $lock->release();
        }
        $this->assertSame('abierta', $alerta->fresh()->estado->value);
    }

    public function test_cerrar_con_clasificacion_cierra_y_guarda_la_observacion(): void
    {
        $alerta = $this->crearAlerta();

        $this->postJson(route('api.v1.alertas.cerrar', $alerta->id), [
            'clasificacion' => 'incidente', 'observacion' => 'Persona ajena; se llamó a la policía.',
        ])->assertOk()->assertJsonPath('data.estado', 'cerrada')->assertJsonPath('data.clasificacion', 'incidente');

        $this->assertSame('Persona ajena; se llamó a la policía.', $alerta->fresh()->observacion);
    }

    /** @return array<string, array{0: array, 1: string}> */
    public static function cierresInvalidos(): array
    {
        return [
            'sin clasificar' => [[], 'clasificacion'],
            'clasificación inventada' => [['clasificacion' => 'ya_paso'], 'clasificacion'],
            'incidente sin observación' => [['clasificacion' => 'incidente'], 'observacion'],
            'incidente con observación en blanco' => [['clasificacion' => 'incidente', 'observacion' => '   '], 'observacion'],
            'observación demasiado larga' => [['clasificacion' => 'sin_novedad', 'observacion' => str_repeat('x', Alerta::MAX_OBSERVACION + 1)], 'observacion'],
        ];
    }

    #[DataProvider('cierresInvalidos')]
    public function test_cerrar_mal_responde_422_y_la_alerta_sigue_abierta(array $datos, string $campo): void
    {
        $alerta = $this->crearAlerta();

        $this->postJson(route('api.v1.alertas.cerrar', $alerta->id), $datos)->assertStatus(422)->assertJsonValidationErrors($campo);

        $this->assertSame('abierta', $alerta->fresh()->estado->value);
    }

    public function test_una_alerta_cerrada_no_se_vuelve_a_cerrar(): void
    {
        $alerta = $this->crearAlerta(['estado' => 'cerrada', 'clasificacion' => 'sin_novedad', 'fecha_cierre' => now()]);

        $this->postJson(route('api.v1.alertas.cerrar', $alerta->id), ['clasificacion' => 'falsa_alarma'])->assertStatus(409);
    }

    public function test_un_id_que_no_existe_o_mal_formado_es_404(): void
    {
        $this->postJson('/api/v1/alertas/'.str_repeat('a', 24).'/atender')->assertNotFound();
        $this->postJson('/api/v1/alertas/no-es-un-id/atender')->assertNotFound();
    }

    public function test_un_admin_tambien_usa_el_panel(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Admin)->create());

        $this->getJson(route('api.v1.alertas.index'))->assertOk();
    }

    /** @return array<string, array{0: Rol}> */
    public static function rolesSinAcceso(): array
    {
        return ['residente' => [Rol::Residente], 'consejo' => [Rol::Consejo], 'agente' => [Rol::Agente]];
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_otros_roles_no_ven_ni_tocan_las_alertas(Rol $rol): void
    {
        $alerta = $this->crearAlerta();
        Sanctum::actingAs(User::factory()->conRol($rol)->create());

        $this->getJson(route('api.v1.alertas.index'))->assertForbidden();
        $this->postJson(route('api.v1.alertas.atender', $alerta->id))->assertForbidden()->assertJsonPath('message', 'Tu cuenta no puede atender alertas.');
        $this->postJson(route('api.v1.alertas.cerrar', $alerta->id), [])->assertForbidden()->assertJsonPath('message', 'Tu cuenta no puede atender alertas.');
        $this->assertSame('abierta', $alerta->fresh()->estado->value);
    }

    public function test_un_vigilante_desactivado_no_usa_el_panel_aunque_tenga_token(): void
    {
        $alerta = $this->crearAlerta();
        $this->vigilante->update(['activo' => false]);

        $this->getJson(route('api.v1.alertas.index'))->assertForbidden();
        $this->postJson(route('api.v1.alertas.atender', $alerta->id))->assertForbidden()->assertJsonPath('message', 'Tu cuenta no puede atender alertas.');
        $this->postJson(route('api.v1.alertas.cerrar', $alerta->id), [])->assertForbidden()->assertJsonPath('message', 'Tu cuenta no puede atender alertas.');
        $this->assertSame('abierta', $alerta->fresh()->estado->value);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(route('api.v1.alertas.index'))->assertUnauthorized();
    }
}
