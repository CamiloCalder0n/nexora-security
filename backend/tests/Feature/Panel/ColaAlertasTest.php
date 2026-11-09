<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class ColaAlertasTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private function como(Rol $rol): static
    {
        return $this->actingAs(User::factory()->conRol($rol)->create());
    }

    public function test_vigilante_y_admin_ven_la_cola(): void
    {
        foreach ([Rol::Vigilante, Rol::Admin] as $rol) {
            $this->como($rol)->get(route('panel.analitica.cola-alertas'))
                ->assertOk()
                ->assertSee('No hay alertas pendientes.');
        }
    }

    public function test_consejo_y_residente_no_ven_la_cola(): void
    {
        foreach ([Rol::Consejo, Rol::Residente] as $rol) {
            $this->como($rol)->get(route('panel.analitica.cola-alertas'))->assertForbidden();
        }
    }

    public function test_ordena_abiertas_primero_luego_prioridad_y_antiguedad_sin_cerradas(): void
    {
        $atiende = ['usuario_id' => 1, 'nombre' => 'Pedro Celador'];
        $this->crearAlerta(['motivo' => 'Atendida alta', 'prioridad' => 3, 'estado' => 'atendida', 'atendida_por' => $atiende, 'fecha_hora' => now()->subHours(5)]);
        $this->crearAlerta(['motivo' => 'Abierta baja', 'prioridad' => 1, 'fecha_hora' => now()->subHours(4)]);
        $this->crearAlerta(['motivo' => 'Abierta alta reciente', 'prioridad' => 3, 'fecha_hora' => now()->subMinutes(5)]);
        $this->crearAlerta(['motivo' => 'Abierta alta antigua', 'prioridad' => 3, 'fecha_hora' => now()->subHours(2),
            'camara' => ['camara_id' => 1, 'nombre' => 'CAM-07'], 'num_eventos' => 4]);
        $this->crearAlerta(['motivo' => 'Cerrada', 'prioridad' => 3, 'estado' => 'cerrada', 'clasificacion' => 'sin_novedad']);

        $respuesta = $this->como(Rol::Vigilante)->get(route('panel.analitica.cola-alertas'))->assertOk();

        $this->assertSame(
            ['Abierta alta antigua', 'Abierta alta reciente', 'Abierta baja', 'Atendida alta'],
            array_column($respuesta->viewData('alertas'), 'motivo'),
        );
        $respuesta->assertSeeInOrder(['Abierta alta antigua', 'Abierta alta reciente', 'Abierta baja', 'Atendida alta'])
            ->assertDontSee('>Cerrada<', false)
            ->assertSee('CAM-07')
            ->assertSee('Pedro Celador')
            ->assertSee('hace 2 horas')
            ->assertSee('Parqueadero');
    }

    public function test_los_botones_apuntan_al_api_de_alertas(): void
    {
        $alerta = $this->crearAlerta();

        $this->como(Rol::Vigilante)->get(route('panel.analitica.cola-alertas'))
            ->assertOk()
            ->assertSee(route('api.v1.alertas.atender', $alerta->id))
            ->assertSee(route('api.v1.alertas.cerrar', $alerta->id))
            ->assertSee('Confirmar cierre');
    }

    public function test_atender_y_cerrar_funcionan_con_la_sesion_del_panel(): void
    {
        $alerta = $this->crearAlerta();
        $panel = ['Referer' => config('app.url').'/panel/analitica/cola-alertas'];

        $this->como(Rol::Vigilante)
            ->postJson(route('api.v1.alertas.atender', $alerta->id), [], $panel)
            ->assertOk()
            ->assertJsonPath('data.estado', 'atendida');

        $this->postJson(route('api.v1.alertas.cerrar', $alerta->id), ['clasificacion' => 'sin_novedad'], $panel)
            ->assertOk()
            ->assertJsonPath('data.estado', 'cerrada');

        $this->get(route('panel.analitica.cola-alertas'))->assertSee('No hay alertas pendientes.');
    }

    public function test_sin_sesion_el_api_de_alertas_responde_401(): void
    {
        $alerta = $this->crearAlerta();

        $this->postJson(route('api.v1.alertas.atender', $alerta->id), [], ['Referer' => config('app.url').'/panel'])
            ->assertUnauthorized();
    }

    public function test_no_se_cae_si_mongo_no_responde(): void
    {
        $this->mock(AlertaService::class)
            ->shouldReceive('activas')->andThrow(new ConnectionTimeoutException('sin conexión'));

        $this->como(Rol::Vigilante)->get(route('panel.analitica.cola-alertas'))
            ->assertOk()
            ->assertSee('No se pudo leer MongoDB');
    }
}
