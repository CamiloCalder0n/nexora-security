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

    /** Las del guion: el comando, sin --camaras, exige que existan la 1 y la 2. */
    private function camarasDelGuion(): void
    {
        $zona = Zona::factory()->create();
        Camara::factory()->create(['id' => 1, 'zona_id' => $zona->id]);
        Camara::factory()->create(['id' => 2, 'zona_id' => $zona->id]);
    }

    public function test_cierra_abiertas_y_atendidas_y_deja_las_cerradas_intactas(): void
    {
        $this->camarasDelGuion();
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
        $this->camarasDelGuion();
        $this->crearAlerta();
        $this->crearAlerta(['estado' => 'atendida']);

        $this->artisan('nexora:demo-preparar')
            ->expectsOutputToContain('la cadena está completa')
            ->assertSuccessful();

        $this->assertSame(2, EntradaBitacora::where('tipo', 'alerta_cerrada')->count());
    }

    public function test_por_defecto_deja_activas_solo_las_camaras_1_y_2_y_las_demas_inactivas(): void
    {
        $zona = Zona::factory()->create();
        $una = Camara::factory()->create(['id' => 1, 'zona_id' => $zona->id, 'estado' => EstadoCamara::Inactiva]);
        $dos = Camara::factory()->create(['id' => 2, 'zona_id' => $zona->id, 'estado' => EstadoCamara::SinSenal, 'ultima_conexion' => now()->subDay()]);
        $otra = Camara::factory()->create(['id' => 3, 'zona_id' => $zona->id, 'estado' => EstadoCamara::Activa, 'ultima_conexion' => now()->subDay()]);

        $this->artisan('nexora:demo-preparar')
            ->expectsOutputToContain('2 cámaras activas')
            ->expectsOutputToContain('1 cámaras inactivas')
            ->assertSuccessful();

        foreach ([$una, $dos] as $camara) {
            $this->assertSame(EstadoCamara::Activa, $camara->fresh()->estado);
            $this->assertTrue($camara->fresh()->ultima_conexion->gt(now()->subMinute()));
        }
        $this->assertSame(EstadoCamara::Inactiva, $otra->fresh()->estado);

        // La inactiva no entra al chequeo de heartbeat: no dispara "sin señal".
        $this->travel(10)->minutes();
        $this->artisan('nexora:camaras-sin-senal')->assertSuccessful();
        $this->assertSame(EstadoCamara::Inactiva, $otra->fresh()->estado);
    }

    public function test_con_camaras_activa_solo_las_pedidas(): void
    {
        $zona = Zona::factory()->create();
        [$a, $b, $c] = Camara::factory()->count(3)->create(['zona_id' => $zona->id, 'estado' => EstadoCamara::Activa]);

        $this->artisan('nexora:demo-preparar', ['--camaras' => "{$a->id}, {$c->id}"])
            ->expectsOutputToContain('2 cámaras activas')
            ->assertSuccessful();

        $this->assertSame(EstadoCamara::Activa, $a->fresh()->estado);
        $this->assertSame(EstadoCamara::Inactiva, $b->fresh()->estado);
        $this->assertSame(EstadoCamara::Activa, $c->fresh()->estado);
    }

    public function test_camaras_inexistentes_o_mal_escritas_fallan_sin_cambiar_nada(): void
    {
        $alerta = $this->crearAlerta();
        $camara = Camara::factory()->create(['zona_id' => Zona::factory()->create()->id, 'estado' => EstadoCamara::SinSenal]);

        $this->artisan('nexora:demo-preparar', ['--camaras' => "{$camara->id},999"])
            ->expectsOutputToContain('No existen las cámaras: 999')
            ->assertFailed();
        $this->artisan('nexora:demo-preparar', ['--camaras' => '1,dos'])->assertFailed();
        $this->artisan('nexora:demo-preparar', ['--camaras' => ''])->assertFailed();

        $this->assertSame('abierta', $alerta->fresh()->estado->value);
        $this->assertSame(EstadoCamara::SinSenal, $camara->fresh()->estado);
    }

    public function test_sin_admin_activo_falla_sin_cerrar_nada(): void
    {
        $this->camarasDelGuion();
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
        $this->camarasDelGuion();
        $alerta = $this->crearAlerta();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('nexora:demo-preparar', ['--force' => true])->assertSuccessful();

        $this->assertSame('cerrada', $alerta->fresh()->estado->value);
    }
}
