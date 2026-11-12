<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Analitica\Infrastructure\Repositories\AlertasLecturaRepository;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

/** RF008: informe mensual de indicadores. */
class InformeMensualTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private const TZ = 'America/Bogota';

    private string $mes;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => self::TZ]);
        $this->mes = now()->subMonthNoOverflow()->format('Y-m');
    }

    private function el(string $diaHora): Carbon
    {
        return Carbon::parse("{$this->mes}-{$diaHora}", self::TZ);
    }

    private function como(Rol $rol): static
    {
        return $this->actingAs(User::factory()->conRol($rol)->create());
    }

    public function test_admin_y_consejo_ven_el_informe_y_los_demas_no(): void
    {
        foreach ([Rol::Admin, Rol::Consejo] as $rol) {
            $this->como($rol)->get(route('panel.analitica.informe'))->assertOk()->assertSee('Informe mensual');
        }
        foreach ([Rol::Vigilante, Rol::Residente] as $rol) {
            $this->como($rol)->get(route('panel.analitica.informe'))->assertForbidden();
        }
    }

    public function test_calcula_los_indicadores_del_mes(): void
    {
        $porteria = ['zona_id' => 2, 'nombre' => 'Portería'];
        $vigilante = ['usuario_id' => 1, 'nombre' => 'Pedro'];

        // Madrugada, atendida a los 10 min, incidente.
        $this->crearAlerta(['fecha_hora' => $this->el('05 02:00'), 'estado' => 'cerrada', 'clasificacion' => 'incidente',
            'atendida_por' => $vigilante, 'fecha_atencion' => $this->el('05 02:10'), 'nivel_escalamiento' => 1]);
        // Noche, atendida a los 30 min, falsa alarma.
        $this->crearAlerta(['fecha_hora' => $this->el('10 21:00'), 'estado' => 'cerrada', 'clasificacion' => 'falsa_alarma',
            'atendida_por' => $vigilante, 'fecha_atencion' => $this->el('10 21:30'), 'zona' => $porteria]);
        $this->crearAlerta(['fecha_hora' => $this->el('12 22:00'), 'estado' => 'cerrada', 'clasificacion' => 'falsa_alarma', 'zona' => $porteria]);
        $this->crearAlerta(['fecha_hora' => $this->el('15 14:00'), 'estado' => 'cerrada', 'clasificacion' => 'sin_novedad']);
        $this->crearAlerta(['fecha_hora' => $this->el('20 09:00')]);
        // Otro mes: no cuenta.
        $this->crearAlerta(['fecha_hora' => $this->el('10 12:00')->subMonthNoOverflow()]);

        $this->crearEvento(['fecha_hora' => $this->el('05 01:59'), 'resultado' => 'alerta']);
        $this->crearEvento(['fecha_hora' => $this->el('06 10:00'), 'resultado' => 'explicado']);
        $this->crearEvento(['fecha_hora' => $this->el('07 10:00'), 'resultado' => 'explicado']);

        RegistroPorteria::factory()->count(3)->create(['fecha_ingreso' => $this->el('08 12:00'), 'fecha_salida' => null]);
        RegistroPorteria::factory()->create(['fecha_ingreso' => $this->el('08 12:00')->subMonthNoOverflow(), 'fecha_salida' => null]);

        $informe = $this->como(Rol::Consejo)->get(route('panel.analitica.informe', ['mes' => $this->mes]))
            ->assertOk()->viewData('informe');
        $a = $informe['alertas'];

        $this->assertTrue($informe['mongo_disponible']);
        $this->assertSame(3, $informe['ingresos']);
        $this->assertEquals(['alerta' => 1, 'explicado' => 2], array_intersect_key($informe['eventos'], ['alerta' => 0, 'explicado' => 0]));
        $this->assertSame(3, $informe['eventos_total']);
        $this->assertSame(5, $a['total']);
        $this->assertSame(1, $a['escaladas']);
        $this->assertSame(4, $informe['clasificadas']);
        $this->assertEquals(50.0, $informe['porcentaje_falsas']);
        $this->assertSame(['zona' => 'Parqueadero', 'total' => 3, 'incidentes' => 1, 'falsas' => 0], $a['por_zona'][0]);
        $this->assertSame(['zona' => 'Portería', 'total' => 2, 'incidentes' => 0, 'falsas' => 2], $a['por_zona'][1]);
        $this->assertSame([1, 1, 1, 2], array_values($informe['por_franja']));
        $this->assertEquals(2, $a['atencion']['atendidas']);
        $this->assertEquals(20.0, $a['atencion']['promedio_min']);
        $this->assertEquals(30.0, $a['atencion']['maximo_min']);
    }

    public function test_descarga_el_csv(): void
    {
        $this->crearAlerta(['fecha_hora' => $this->el('10 21:00'), 'estado' => 'cerrada', 'clasificacion' => 'incidente']);

        $respuesta = $this->como(Rol::Admin)->get(route('panel.analitica.informe.csv', ['mes' => $this->mes]))->assertOk();

        $this->assertStringContainsString("informe-nexora-{$this->mes}.csv", $respuesta->headers->get('Content-Disposition'));
        $csv = $respuesta->streamedContent();
        $this->assertStringContainsString('Alertas;total;1', $csv);
        $this->assertStringContainsString('"Alertas cerradas";incidente;1', $csv);
    }

    public function test_mes_futuro_o_mal_escrito_no_se_acepta(): void
    {
        foreach ([now()->addMonthNoOverflow()->format('Y-m'), '2026-13', 'noviembre'] as $mes) {
            $this->como(Rol::Admin)->get(route('panel.analitica.informe', ['mes' => $mes]))
                ->assertRedirect(route('panel.analitica.informe'))
                ->assertSessionHasErrors('mes');
        }
    }

    public function test_sin_mongo_muestra_los_ingresos_igual(): void
    {
        $this->mock(AlertasLecturaRepository::class)
            ->shouldReceive('informe')->andThrow(new ConnectionTimeoutException('sin conexión'));

        $respuesta = $this->como(Rol::Admin)->get(route('panel.analitica.informe'))->assertOk()->assertSee('No se pudo leer MongoDB');
        $this->assertFalse($respuesta->viewData('informe')['mongo_disponible']);
    }
}
