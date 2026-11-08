<?php

namespace Tests\Feature\Monitoreo;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Franja;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class IngestaAgenteTest extends TestCase
{
    use LimpiaMongo, RefreshDatabase;

    private Camara $camara;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EventoRegistrado::class]);

        $zona = Zona::factory()->create(['nombre' => 'Parqueadero sótano', 'prioridad_base' => 3]);
        Franja::factory()->todoElDia()->for($zona)->create();
        $this->camara = Camara::factory()->for($zona)->create(['nombre' => 'CAM-01 Parqueadero']);
    }

    private function comoAgente(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Agente)->create());
    }

    private function evento(array $cambios = []): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'camara_id' => $this->camara->id,
            'tipo_objeto' => 'persona',
            'cantidad' => 2,
            'confianza' => 0.87,
            'fecha_hora' => now('America/Bogota')->toIso8601String(),
            'ruta_evidencia' => 'evidencias/cam1/20261106-190440.jpg',
            ...$cambios,
        ];
    }

    public function test_evento_nuevo_responde_202_y_queda_en_cola(): void
    {
        $this->comoAgente();
        $datos = $this->evento();

        $this->postJson(route('api.v1.agente.eventos'), $datos)
            ->assertStatus(202)
            ->assertExactJson(['data' => ['uuid' => $datos['uuid'], 'estado' => 'recibido']]);

        $evento = Evento::where('uuid', $datos['uuid'])->first();
        $this->assertSame('pendiente', $evento->resultado->value);
        $this->assertSame(['camara_id' => $this->camara->id, 'nombre' => 'CAM-01 Parqueadero'], $evento->camara);
        $this->assertSame('Parqueadero sótano', $evento->zona['nombre']);
        Event::assertDispatched(EventoRegistrado::class, fn ($e) => $e->eventoId === $evento->id);
    }

    public function test_mismo_uuid_responde_200_duplicado_sin_volver_a_encolar(): void
    {
        $this->comoAgente();
        $datos = $this->evento();
        $this->postJson(route('api.v1.agente.eventos'), $datos)->assertStatus(202);

        $this->postJson(route('api.v1.agente.eventos'), $datos)
            ->assertOk()
            ->assertJsonPath('data.estado', 'duplicado');

        $this->assertSame(1, Evento::where('uuid', $datos['uuid'])->count());
        Event::assertDispatchedTimes(EventoRegistrado::class, 1);
    }

    /** Un caso por regla del contrato. */
    public static function invalidos(): array
    {
        return [
            'uuid que no es v4' => ['uuid', 'a8098c1a-f86e-11da-bd1a-00112444be1e'],
            'uuid ausente' => ['uuid', null],
            'cámara inexistente' => ['camara_id', 999],
            'tipo de objeto raro' => ['tipo_objeto', 'perro'],
            'cantidad cero' => ['cantidad', 0],
            'cantidad mayor a 50' => ['cantidad', 51],
            'cantidad decimal' => ['cantidad', 1.5],
            'confianza mayor a 1' => ['confianza', 1.5],
            'confianza negativa' => ['confianza', -0.1],
            'fecha sin zona horaria' => ['fecha_hora', '2026-11-06 19:04:40'],
            'fecha de hace más de 24 h' => ['fecha_hora', fn () => now()->subHours(25)->toIso8601String()],
            'fecha a más de 5 min en el futuro' => ['fecha_hora', fn () => now()->addMinutes(6)->toIso8601String()],
            'ruta de evidencia muy larga' => ['ruta_evidencia', str_repeat('a', 256)],
            'cantidad booleana' => ['cantidad', true],
            'cantidad como texto' => ['cantidad', '5'],
            'confianza como texto' => ['confianza', '0.5'],
            'cámara como texto' => ['camara_id', '1'],
            'ruta que sube de carpeta' => ['ruta_evidencia', '../../../../etc/passwd'],
            'ruta absoluta' => ['ruta_evidencia', '/etc/passwd'],
            'ruta con carácter nulo' => ['ruta_evidencia', "evidencias/cam1/a\0.jpg"],
            'ruta con unicode de control' => ['ruta_evidencia', "evidencias/cam1/\u{202E}gpj.exe"],
            'ruta con html' => ['ruta_evidencia', 'evidencias/<script>.jpg'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_cada_dato_invalido_responde_422(string $campo, mixed $valor): void
    {
        $this->comoAgente();

        $this->postJson(route('api.v1.agente.eventos'), $this->evento([$campo => value($valor)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campo]);

        $this->assertSame(0, Evento::count());
    }

    public function test_rutas_validas_del_agente(): void
    {
        $this->comoAgente();

        foreach (['evidencias/cam1/20261106-190440.jpg', 'evidencias/cam12/20261106-190440-1.jpg'] as $ruta) {
            $this->postJson(route('api.v1.agente.eventos'), $this->evento(['ruta_evidencia' => $ruta]))->assertStatus(202);
        }
    }

    public function test_un_salto_de_linea_final_se_recorta_antes_de_guardar(): void
    {
        $this->comoAgente();
        $datos = $this->evento(['ruta_evidencia' => "evidencias/cam1/a.jpg\n"]);

        $this->postJson(route('api.v1.agente.eventos'), $datos)->assertStatus(202);

        $this->assertSame('evidencias/cam1/a.jpg', Evento::where('uuid', $datos['uuid'])->value('ruta_evidencia'));
    }

    public function test_fecha_dentro_del_rango_y_sin_evidencia_es_valida(): void
    {
        $this->comoAgente();
        $datos = $this->evento(['fecha_hora' => now()->subHours(23)->toIso8601String()]);
        unset($datos['ruta_evidencia']);

        $this->postJson(route('api.v1.agente.eventos'), $datos)->assertStatus(202);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->postJson(route('api.v1.agente.eventos'), $this->evento())->assertUnauthorized();
        $this->getJson(route('api.v1.agente.camaras'))->assertUnauthorized();
    }

    public function test_usuario_que_no_es_agente_responde_403(): void
    {
        Sanctum::actingAs(User::factory()->conRol(Rol::Vigilante)->create());

        $this->postJson(route('api.v1.agente.eventos'), $this->evento())->assertForbidden();
    }

    public function test_pasar_de_60_por_minuto_responde_429_con_retry_after(): void
    {
        $this->comoAgente();

        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('api.v1.agente.eventos'), $this->evento())->assertStatus(202);
        }

        $this->postJson(route('api.v1.agente.eventos'), $this->evento())
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_camaras_del_agente_con_zona_y_vigilada_ahora(): void
    {
        $this->comoAgente();
        Camara::factory()->create(['estado' => EstadoCamara::Inactiva]);

        $this->getJson(route('api.v1.agente.camaras'))
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $this->camara->id,
                'nombre' => 'CAM-01 Parqueadero',
                'estado' => 'activa',
                'zona' => ['id' => $this->camara->zona_id, 'nombre' => 'Parqueadero sótano', 'prioridad_base' => 3],
                'vigilada_ahora' => true,
            ]]]);
    }

    public function test_heartbeat_responde_204_y_reactiva_la_camara(): void
    {
        $this->comoAgente();
        $this->camara->update(['estado' => EstadoCamara::SinSenal]);

        $this->postJson(route('api.v1.agente.heartbeat', $this->camara), ['fecha_hora' => now()->toIso8601String()])
            ->assertNoContent();

        $this->camara->refresh();
        $this->assertSame(EstadoCamara::Activa, $this->camara->estado);
        $this->assertNotNull($this->camara->ultima_conexion);
    }

    public function test_heartbeat_de_camara_inexistente_responde_404(): void
    {
        $this->comoAgente();

        $this->postJson(route('api.v1.agente.heartbeat', 999))->assertNotFound();
    }
}
