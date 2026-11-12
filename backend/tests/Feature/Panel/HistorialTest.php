<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Analitica\Infrastructure\Repositories\EventosLecturaRepository;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

/** RF005 / HU14: historial de eventos con filtros. */
class HistorialTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private const PISCINA = ['zona_id' => 2, 'nombre' => 'Piscina'];

    private function como(Rol $rol): static
    {
        return $this->actingAs(User::factory()->conRol($rol)->create());
    }

    public function test_admin_vigilante_y_consejo_ven_el_historial_y_residente_no(): void
    {
        foreach ([Rol::Admin, Rol::Vigilante, Rol::Consejo] as $rol) {
            $this->como($rol)->get(route('panel.analitica.historial'))->assertOk()->assertSee('Historial de eventos');
        }
        $this->como(Rol::Residente)->get(route('panel.analitica.historial'))->assertForbidden();
    }

    public function test_filtra_por_zona_tipo_resultado_y_fechas(): void
    {
        $this->crearEvento(['fecha_hora' => now()->subDays(2), 'resultado' => 'alerta']);
        $this->crearEvento(['fecha_hora' => now()->subDays(1), 'zona' => self::PISCINA, 'resultado' => 'explicado']);
        $this->crearEvento(['fecha_hora' => now()->subHours(3), 'zona' => self::PISCINA, 'tipo_objeto' => 'vehiculo', 'resultado' => 'alerta']);
        $this->crearEvento(['fecha_hora' => now()->subDays(20), 'zona' => self::PISCINA, 'resultado' => 'alerta']);

        $total = fn (array $filtros) => $this->como(Rol::Admin)
            ->get(route('panel.analitica.historial', $filtros))->assertOk()->viewData('eventos')->total();

        $this->assertSame(4, $total([]));
        $this->assertSame(3, $total(['zona_id' => 2]));
        $this->assertSame(1, $total(['tipo_objeto' => 'vehiculo']));
        $this->assertSame(3, $total(['resultado' => 'alerta']));
        $this->assertSame(2, $total(['zona_id' => 2, 'resultado' => 'alerta']));
        $this->assertSame(3, $total(['desde' => now()->subDays(3)->toDateString()]));
        $this->assertSame(1, $total(['hasta' => now()->subDays(10)->toDateString()]));
    }

    public function test_ordena_por_fecha_y_pagina_de_a_15(): void
    {
        foreach (range(1, 17) as $i) {
            $this->crearEvento(['fecha_hora' => now()->subMinutes($i)]);
        }

        $recientes = $this->como(Rol::Admin)->get(route('panel.analitica.historial'))->viewData('eventos');
        $this->assertCount(15, $recientes->items());
        $this->assertSame(17, $recientes->total());
        $this->assertTrue($recientes->items()[0]['fecha_hora']->gt($recientes->items()[1]['fecha_hora']));

        $antiguos = $this->como(Rol::Admin)->get(route('panel.analitica.historial', ['orden' => 'antiguos']))->viewData('eventos');
        $this->assertTrue($antiguos->items()[0]['fecha_hora']->lt($antiguos->items()[1]['fecha_hora']));

        $pagina2 = $this->como(Rol::Admin)->get(route('panel.analitica.historial', ['page' => 2]))->viewData('eventos');
        $this->assertCount(2, $pagina2->items());
    }

    public function test_el_listado_no_muestra_la_ruta_de_la_evidencia(): void
    {
        $this->crearEvento(['ruta_evidencia' => 'evidencias/cam1/20261110-220000.jpg']);

        $respuesta = $this->como(Rol::Admin)->get(route('panel.analitica.historial'))->assertOk();

        $respuesta->assertDontSee('20261110-220000.jpg');
        $this->assertTrue($respuesta->viewData('eventos')->items()[0]['tiene_evidencia']);
        $this->assertArrayNotHasKey('ruta_evidencia', $respuesta->viewData('eventos')->items()[0]);
    }

    public function test_exporta_el_resultado_en_csv_sin_la_ruta_de_la_evidencia(): void
    {
        $this->crearEvento(['zona' => self::PISCINA, 'ruta_evidencia' => 'evidencias/cam1/20261110-220000.jpg']);
        $this->crearEvento();

        $csv = $this->como(Rol::Consejo)->get(route('panel.analitica.historial.csv', ['zona_id' => 2]))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Piscina', $csv);
        $this->assertStringNotContainsString('Parqueadero', $csv);
        $this->assertStringNotContainsString('20261110-220000.jpg', $csv);
        $this->como(Rol::Vigilante)->get(route('panel.analitica.historial.csv'))->assertForbidden();
    }

    public function test_filtros_invalidos_vuelven_al_listado(): void
    {
        $this->como(Rol::Admin)->get(route('panel.analitica.historial', ['resultado' => 'otro', 'desde' => '10/11/2026']))
            ->assertRedirect(route('panel.analitica.historial'))
            ->assertSessionHasErrors(['resultado', 'desde']);
    }

    public function test_no_se_cae_si_mongo_no_responde(): void
    {
        $this->mock(EventosLecturaRepository::class)
            ->shouldReceive('historial')->andThrow(new ConnectionTimeoutException('sin conexión'));

        $this->como(Rol::Vigilante)->get(route('panel.analitica.historial'))
            ->assertOk()
            ->assertSee('No se pudo leer MongoDB');
    }
}
