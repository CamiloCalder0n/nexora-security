<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class EventosEnVivoPanelTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private function como(Rol $rol): static
    {
        return $this->actingAs(User::factory()->conRol($rol)->create());
    }

    public function test_admin_y_vigilante_ven_la_pagina_y_el_feed(): void
    {
        foreach ([Rol::Admin, Rol::Vigilante] as $rol) {
            $this->como($rol)->get(route('panel.monitoreo.eventos.index'))
                ->assertOk()
                ->assertSee('Aún no hay eventos registrados.');
            $this->como($rol)->getJson(route('panel.monitoreo.eventos.feed'))
                ->assertOk()
                ->assertExactJson(['eventos' => []]);
        }
    }

    public function test_consejo_y_residente_reciben_403(): void
    {
        foreach ([Rol::Consejo, Rol::Residente] as $rol) {
            $this->como($rol)->get(route('panel.monitoreo.eventos.index'))->assertForbidden();
            $this->como($rol)->getJson(route('panel.monitoreo.eventos.feed'))->assertForbidden();
        }
    }

    public function test_invitado_va_a_login(): void
    {
        $this->get(route('panel.monitoreo.eventos.index'))->assertRedirect(route('login'));
        $this->get(route('panel.monitoreo.eventos.feed'))->assertRedirect(route('login'));
    }

    public function test_feed_devuelve_los_eventos_con_las_claves_esperadas_y_sin_datos_sensibles(): void
    {
        $this->crearEvento([
            'fecha_hora' => now()->setTimezone('UTC')->setTime(15, 30, 0), 'cantidad' => 2, 'confianza' => 0.876,
            'resultado' => 'alerta', 'ruta_evidencia' => 'evidencias/secreta.jpg', 'registro_id' => 7, 'alerta_id' => 'abc123',
        ]);

        $respuesta = $this->como(Rol::Vigilante)->getJson(route('panel.monitoreo.eventos.feed'))->assertOk();

        $evento = $respuesta->json('eventos.0');
        $this->assertEqualsCanonicalizing(
            ['id', 'hora', 'camara', 'zona', 'tipo', 'cantidad', 'confianza', 'resultado'],
            array_keys($evento),
        );
        $this->assertSame('CAM-01', $evento['camara']);
        $this->assertSame('Parqueadero', $evento['zona']);
        $this->assertSame('persona', $evento['tipo']);
        $this->assertSame(2, $evento['cantidad']);
        $this->assertSame(88, $evento['confianza']);
        $this->assertSame('alerta', $evento['resultado']);
        $this->assertStringContainsString(
            now()->setTimezone('UTC')->setTime(15, 30, 0)->timezone(config('app.timezone'))->format('H:i:s'),
            $evento['hora'],
        );
        $respuesta->assertDontSee('ruta_evidencia')->assertDontSee('registro_id')->assertDontSee('alerta_id')
            ->assertDontSee('secreta.jpg')->assertDontSee('abc123');
    }

    public function test_feed_trae_como_maximo_50_el_mas_reciente_primero(): void
    {
        foreach (range(1, 55) as $i) {
            $this->crearEvento(['fecha_hora' => now()->subMinutes(100 - $i), 'camara' => ['camara_id' => 1, 'nombre' => "CAM-$i"]]);
        }

        $eventos = $this->como(Rol::Admin)->getJson(route('panel.monitoreo.eventos.feed'))->assertOk()->json('eventos');

        $this->assertCount(50, $eventos);
        $this->assertSame('CAM-55', $eventos[0]['camara']);
        $this->assertSame('CAM-6', $eventos[49]['camara']);
    }

    public function test_la_pagina_renderiza_los_eventos_en_el_servidor(): void
    {
        $this->crearEvento(['camara' => ['camara_id' => 2, 'nombre' => 'CAM-XYZ'], 'resultado' => 'fuera_franja']);

        $this->como(Rol::Admin)->get(route('panel.monitoreo.eventos.index'))
            ->assertOk()
            ->assertSee('CAM-XYZ')
            ->assertSee('Fuera de franja');
    }
}
