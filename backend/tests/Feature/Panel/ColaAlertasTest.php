<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Analitica\Infrastructure\Repositories\AlertasLecturaRepository;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    public function test_sin_el_api_de_alertas_no_muestra_botones_de_accion(): void
    {
        $this->assertFalse(Route::has('api.v1.alertas.atender'));
        $this->crearAlerta();

        $respuesta = $this->como(Rol::Vigilante)->get(route('panel.analitica.cola-alertas'))->assertOk();

        $this->assertFalse($respuesta->viewData('acciones'));
        $respuesta->assertDontSee('js-atender')->assertDontSee('Confirmar cierre');
    }

    public function test_no_se_cae_si_mongo_no_responde(): void
    {
        $this->mock(AlertasLecturaRepository::class)
            ->shouldReceive('cola')->andThrow(new ConnectionTimeoutException('sin conexión'));

        $this->como(Rol::Vigilante)->get(route('panel.analitica.cola-alertas'))
            ->assertOk()
            ->assertSee('No se pudo leer MongoDB');
    }
}
