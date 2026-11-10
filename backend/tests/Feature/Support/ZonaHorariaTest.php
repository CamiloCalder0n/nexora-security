<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Franja;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

/** El agente manda la hora en UTC; la alerta, Telegram y la cola la muestran en la hora del conjunto. */
class ZonaHorariaTest extends TestCase
{
    use LimpiaMongo, RefreshDatabase;

    private string $zonaAnterior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zonaAnterior = date_default_timezone_get();
        config(['app.timezone' => 'America/Bogota', 'alertas.telegram.token' => '123456:TOKEN-DE-PRUEBA']);
        date_default_timezone_set('America/Bogota');
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zonaAnterior);
        parent::tearDown();
    }

    public function test_evento_a_las_0445_utc_se_muestra_a_las_2345_en_bogota(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10T04:45:00Z'));
        $zona = Zona::factory()->create(['nombre' => 'Parqueadero sótano']);
        Franja::factory()->entre('00:00', '00:00')->for($zona)->create();
        $camara = Camara::factory()->for($zona)->create();
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create(['telegram_chat_id' => '1001']);

        Sanctum::actingAs(User::factory()->conRol(Rol::Agente)->create(), ['agente']);
        $this->postJson(route('api.v1.agente.eventos'), [
            'uuid' => (string) Str::uuid(),
            'camara_id' => $camara->id,
            'tipo_objeto' => 'persona',
            'cantidad' => 1,
            'confianza' => 0.9,
            'fecha_hora' => '2026-11-10T04:45:00Z',
            'ruta_evidencia' => 'evidencias/cam1/zona-horaria.jpg',
        ])->assertStatus(202);

        $alerta = Alerta::first();
        $this->assertNotNull($alerta, 'El evento sin registro en portería debía crear una alerta.');
        $this->assertStringContainsString('a las 23:45', $alerta->motivo);

        $mensajes = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/sendMessage'))->map(fn ($par) => $par[0]['text']);
        $this->assertCount(1, $mensajes);
        $this->assertStringContainsString('09/11 23:45', $mensajes->first());

        $this->actingAs($vigilante)->get(route('panel.analitica.cola-alertas'))
            ->assertOk()
            ->assertSee('09/11/2026 23:45:00');
    }
}
