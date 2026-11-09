<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Analitica\Infrastructure\Repositories\AlertasLecturaRepository;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private function como(Rol $rol): static
    {
        return $this->actingAs(User::factory()->conRol($rol)->create());
    }

    public function test_admin_vigilante_y_consejo_ven_el_dashboard(): void
    {
        foreach ([Rol::Admin, Rol::Vigilante, Rol::Consejo] as $rol) {
            $this->como($rol)->get(route('panel.dashboard'))
                ->assertOk()
                ->assertSee('Ingresos por día (últimos 30 días)')
                ->assertSee('Alertas por zona (últimos 30 días)')
                ->assertSee('chart.js@4.4.1', false);
        }
    }

    public function test_residente_no_ve_el_dashboard(): void
    {
        $this->como(Rol::Residente)->get(route('panel.dashboard'))->assertForbidden();
    }

    public function test_sin_datos_muestra_estados_vacios(): void
    {
        $this->como(Rol::Admin)->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee('No hay ingresos registrados en este periodo.')
            ->assertSee('No hay alertas en este periodo.')
            ->assertDontSee('No se pudo leer MongoDB');
    }

    public function test_cuenta_alertas_por_zona_y_porcentaje_de_falsas_alarmas(): void
    {
        $porteria = ['zona_id' => 2, 'nombre' => 'Portería'];
        $this->crearAlerta();
        $this->crearAlerta();
        $this->crearAlerta(['zona' => $porteria, 'estado' => 'cerrada', 'clasificacion' => 'falsa_alarma']);
        $this->crearAlerta(['zona' => $porteria, 'estado' => 'cerrada', 'clasificacion' => 'falsa_alarma']);
        $this->crearAlerta(['zona' => $porteria, 'estado' => 'cerrada', 'clasificacion' => 'falsa_alarma']);
        $this->crearAlerta(['estado' => 'cerrada', 'clasificacion' => 'sin_novedad']);
        // Fuera de los 30 días: no cuenta para el periodo, pero sigue abierta.
        $this->crearAlerta(['zona' => ['zona_id' => 3, 'nombre' => 'Piscina'], 'fecha_hora' => now()->subDays(40)]);

        $respuesta = $this->como(Rol::Admin)->get(route('panel.dashboard'))->assertOk();
        $resumen = $respuesta->viewData('resumen');

        $this->assertSame(['Parqueadero' => 3, 'Portería' => 3], $resumen['alertas_por_zona']);
        $this->assertSame(3, $resumen['alertas_abiertas']);
        $this->assertSame(6, $resumen['alertas_periodo']);
        $this->assertSame(4, $resumen['alertas_cerradas']);
        $this->assertEquals(75.0, $resumen['porcentaje_falsas']);
        $respuesta->assertSee('75 %')->assertDontSee('No hay alertas en este periodo.');
    }

    public function test_ingresos_por_dia_rellena_los_dias_sin_ingresos(): void
    {
        RegistroPorteria::factory()->count(2)->create(['fecha_ingreso' => now(), 'fecha_salida' => null]);
        RegistroPorteria::factory()->create(['fecha_ingreso' => now()->subDays(3), 'fecha_salida' => null]);
        RegistroPorteria::factory()->create(['fecha_ingreso' => now()->subDays(45), 'fecha_salida' => null]);

        $resumen = $this->como(Rol::Admin)->get(route('panel.dashboard'))->assertOk()->viewData('resumen');
        $serie = $resumen['ingresos_por_dia'];

        $this->assertCount(30, $serie);
        $this->assertSame(2, $resumen['ingresos_hoy']);
        $this->assertSame(1, $serie[now()->subDays(3)->toDateString()]);
        $this->assertSame(0, $serie[now()->subDays(1)->toDateString()]);
        $this->assertSame(3, $resumen['ingresos_periodo']);
    }

    public function test_no_se_cae_si_mongo_no_responde(): void
    {
        $this->mock(AlertasLecturaRepository::class)
            ->shouldReceive('resumen')->andThrow(new ConnectionTimeoutException('sin conexión'));

        $respuesta = $this->como(Rol::Vigilante)->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee('No se pudo leer MongoDB');
        $resumen = $respuesta->viewData('resumen');

        $this->assertFalse($resumen['mongo_disponible']);
        $this->assertNull($resumen['alertas_abiertas']);
        $this->assertNull($resumen['porcentaje_falsas']);
        $this->assertNull($resumen['alertas_por_zona']);
    }
}
