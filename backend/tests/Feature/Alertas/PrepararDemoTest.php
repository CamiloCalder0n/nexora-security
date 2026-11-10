<?php

namespace Tests\Feature\Alertas;

use App\Models\User;
use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class PrepararDemoTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->conRol(Rol::Admin)->create();
    }

    public function test_cierra_abiertas_y_atendidas_y_deja_las_cerradas_intactas(): void
    {
        $abierta = $this->crearAlerta();
        $atendida = $this->crearAlerta(['estado' => 'atendida']);
        $cerrada = $this->crearAlerta(['estado' => 'cerrada', 'clasificacion' => 'incidente', 'observacion' => 'Robo']);

        $this->artisan('nexora:demo-preparar')
            ->expectsOutputToContain('2 alertas cerradas')
            ->assertSuccessful();

        foreach ([$abierta, $atendida] as $alerta) {
            $alerta = $alerta->fresh();
            $this->assertSame('cerrada', $alerta->estado->value);
            $this->assertSame('sin_novedad', $alerta->clasificacion->value);
            $this->assertNotNull($alerta->fecha_cierre);
        }
        $this->assertSame('incidente', $cerrada->fresh()->clasificacion->value);
        $this->assertSame('Robo', $cerrada->fresh()->observacion);
    }

    public function test_cada_cierre_queda_en_la_bitacora_con_la_cadena_intacta(): void
    {
        $this->crearAlerta();
        $this->crearAlerta(['estado' => 'atendida']);

        $this->artisan('nexora:demo-preparar')
            ->expectsOutputToContain('la cadena está completa')
            ->assertSuccessful();

        $this->assertSame(2, EntradaBitacora::where('tipo', 'alerta_cerrada')->count());
    }

    public function test_deja_todas_las_camaras_activas_con_senal_reciente(): void
    {
        $zona = Zona::factory()->create();
        Camara::factory()->create(['zona_id' => $zona->id, 'estado' => EstadoCamara::Inactiva]);
        Camara::factory()->create(['zona_id' => $zona->id, 'estado' => EstadoCamara::SinSenal, 'ultima_conexion' => now()->subDay()]);

        $this->artisan('nexora:demo-preparar')->expectsOutputToContain('2 cámaras activas')->assertSuccessful();

        foreach (Camara::all() as $camara) {
            $this->assertSame(EstadoCamara::Activa, $camara->estado);
            $this->assertTrue($camara->ultima_conexion->gt(now()->subMinute()));
        }
    }

    public function test_sin_admin_activo_falla_sin_cerrar_nada(): void
    {
        User::query()->update(['activo' => false]);
        $alerta = $this->crearAlerta();

        $this->artisan('nexora:demo-preparar')->expectsOutputToContain('admin activo')->assertFailed();

        $this->assertSame('abierta', $alerta->fresh()->estado->value);
    }

    public function test_en_produccion_sin_force_y_respondiendo_no_no_cambia_nada(): void
    {
        $alerta = $this->crearAlerta();
        $camara = Camara::factory()->create(['zona_id' => Zona::factory()->create()->id, 'estado' => EstadoCamara::Inactiva]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('nexora:demo-preparar')->expectsConfirmation('Estás en producción: se cerrarán todas las alertas activas. ¿Seguir?', 'no')->assertFailed();

        $this->assertSame('abierta', $alerta->fresh()->estado->value);
        $this->assertSame(EstadoCamara::Inactiva, $camara->fresh()->estado);
    }

    public function test_en_produccion_con_force_corre(): void
    {
        $alerta = $this->crearAlerta();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('nexora:demo-preparar', ['--force' => true])->assertSuccessful();

        $this->assertSame('cerrada', $alerta->fresh()->estado->value);
    }
}
