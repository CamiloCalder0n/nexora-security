<?php

namespace Tests\Feature\Alertas;

use App\Models\User;
use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Application\CorrelacionService;
use App\Modules\Alertas\Application\Reglas\ReglaDeteccion;
use App\Modules\Alertas\Domain\Events\AlertaCreada;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use App\Modules\Alertas\Domain\Reglas\ResultadoRegla;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Application\EventoService;
use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Franja;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Application\Contracts\ConsultaRegistrosPorteria;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class CorrelacionTest extends TestCase
{
    use LimpiaMongo, RefreshDatabase;

    private Camara $camara;

    private PorteriaFalsa $porteria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('nexora:mongo-indices');
        Event::fake([AlertaCreada::class]);
        Carbon::setTestNow(Carbon::parse('2026-11-06 22:30:00', 'America/Bogota'));

        $zona = Zona::factory()->create([
            'nombre' => 'Parqueadero sótano', 'prioridad_base' => 3,
            'ventana_correlacion_min' => 10, 'ventana_silencio_min' => 5,
        ]);
        Franja::factory()->entre('18:00', '06:00')->for($zona)->create();
        $this->camara = Camara::factory()->for($zona)->create(['nombre' => 'CAM-01 Parqueadero']);

        $this->porteria = new PorteriaFalsa;
        $this->app->instance(ConsultaRegistrosPorteria::class, $this->porteria);
        Sanctum::actingAs(User::factory()->conRol(Rol::Agente)->create());
    }

    /** Envía un evento por la API; la cola es sync en los tests, así que la correlación corre enseguida. */
    private function enviarEvento(array $cambios = []): Evento
    {
        $datos = [
            'uuid' => (string) Str::uuid(),
            'camara_id' => $this->camara->id,
            'tipo_objeto' => 'persona',
            'cantidad' => 1,
            'confianza' => 0.9,
            'fecha_hora' => now()->toIso8601String(),
            'ruta_evidencia' => 'evidencias/cam1/'.Str::random(6).'.jpg',
            ...$cambios,
        ];
        $this->postJson(route('api.v1.agente.eventos'), $datos)->assertStatus(202);

        return Evento::where('uuid', $datos['uuid'])->first();
    }

    public function test_evento_con_registro_en_la_ventana_queda_explicado(): void
    {
        $this->porteria->registro = RegistroPorteria::factory()->make(['id' => 77]);

        $evento = $this->enviarEvento();

        $this->assertSame('explicado', $evento->resultado->value);
        $this->assertTrue($evento->explicado);
        $this->assertSame(77, $evento->registro_id);
        $this->assertSame([$this->camara->zona_id, 10, 1], $this->porteria->consulta);
        $this->assertSame(0, Alerta::count());
        Event::assertNotDispatched(AlertaCreada::class);
    }

    public function test_evento_sin_registro_crea_alerta_con_motivo_legible(): void
    {
        $evento = $this->enviarEvento();

        $alerta = Alerta::first();
        $this->assertSame('alerta', $evento->resultado->value);
        $this->assertSame($alerta->id, $evento->alerta_id);
        $this->assertSame('sin_registro', $alerta->tipo->value);
        $this->assertSame('abierta', $alerta->estado->value);
        $this->assertSame(3, $alerta->prioridad);
        $this->assertSame('sin_registro_en_porteria', $alerta->regla);
        $this->assertSame(
            'Persona en Parqueadero sótano a las 22:30 sin registro en portería (ventana ±10 min)',
            $alerta->motivo,
        );
        $this->assertSame([$evento->id], $alerta->eventos_ids);
        $this->assertSame('alerta_creada', EntradaBitacora::first()->tipo);
        Event::assertDispatched(AlertaCreada::class, fn ($e) => $e->alertaId === $alerta->id);
    }

    public function test_dos_eventos_en_5_minutos_son_una_sola_alerta_con_2_eventos(): void
    {
        $primero = $this->enviarEvento();
        Carbon::setTestNow(now()->addMinutes(3));
        $segundo = $this->enviarEvento(['cantidad' => 2]);

        $this->assertSame(1, Alerta::count());
        $alerta = Alerta::first();
        $this->assertSame(2, $alerta->num_eventos);
        $this->assertSame([$primero->id, $segundo->id], $alerta->eventos_ids);
        $this->assertSame('suprimido', $segundo->resultado->value);
        Event::assertDispatchedTimes(AlertaCreada::class, 1);
    }

    public function test_pasada_la_ventana_de_silencio_hay_alerta_nueva(): void
    {
        $this->enviarEvento();
        Carbon::setTestNow(now()->addMinutes(6));
        $this->enviarEvento();

        $this->assertSame(2, Alerta::count());
    }

    public function test_guarda_maximo_3_evidencias_recientes(): void
    {
        $rutas = [];
        for ($i = 0; $i < 5; $i++) {
            $rutas[] = $this->enviarEvento()->ruta_evidencia;
            Carbon::setTestNow(now()->addMinute());
        }

        $alerta = Alerta::first();
        $this->assertSame(5, $alerta->num_eventos);
        $this->assertSame(array_slice($rutas, -3), $alerta->evidencias_recientes);
    }

    public function test_fuera_de_franja_no_genera_alerta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 12:00:00', 'America/Bogota'));

        $evento = $this->enviarEvento();

        $this->assertSame('fuera_franja', $evento->resultado->value);
        $this->assertSame(0, Alerta::count());
    }

    public function test_un_evento_viejo_de_la_cola_del_agente_no_rompe_la_supresion(): void
    {
        $this->enviarEvento();                                                  // 22:30, crea la alerta
        $viejo = $this->enviarEvento(['fecha_hora' => now()->subMinutes(30)->toIso8601String()]); // 22:00, llega tarde
        Carbon::setTestNow(now()->addMinutes(3));
        $siguiente = $this->enviarEvento();                                     // 22:33

        $this->assertSame('alerta', $viejo->resultado->value);  // no lo cubría la alerta de las 22:30
        $alertaActual = Alerta::find($siguiente->alerta_id);
        $this->assertSame('suprimido', $siguiente->resultado->value);
        $this->assertSame(2, $alertaActual->num_eventos);
        $this->assertSame(2, Alerta::count());
        $this->assertTrue($alertaActual->fecha_ultimo_evento->equalTo(now()->startOfSecond()));
    }

    public function test_un_evento_tardio_dentro_de_la_alerta_no_la_hace_retroceder(): void
    {
        $this->enviarEvento();                                                   // 22:30
        Carbon::setTestNow(now()->addMinutes(3));
        $this->enviarEvento();                                                   // 22:33
        $tardio = $this->enviarEvento(['fecha_hora' => now()->subMinutes(2)->toIso8601String()]); // 22:31

        $alerta = Alerta::sole();
        $this->assertSame('suprimido', $tardio->resultado->value);
        $this->assertSame(3, $alerta->num_eventos);
        $this->assertTrue($alerta->fecha_ultimo_evento->equalTo(now()->startOfSecond())); // sigue en 22:33
    }

    public function test_reintento_con_la_alerta_ya_atendida_no_crea_otra(): void
    {
        $evento = $this->enviarEvento();
        app(AlertaService::class)->atender(Alerta::sole(), User::factory()->conRol(Rol::Vigilante)->create());
        $evento->update(['resultado' => 'pendiente', 'alerta_id' => null]);

        app(CorrelacionService::class)->correlacionar($evento->id);

        $this->assertSame(1, Alerta::count());
        $this->assertSame('alerta', $evento->fresh()->resultado->value);
        Event::assertDispatchedTimes(AlertaCreada::class, 1);  // no se vuelve a avisar una alerta atendida
    }

    public function test_si_el_job_falla_despues_de_crear_la_alerta_el_reintento_la_completa(): void
    {
        $evento = $this->enviarEvento();
        // Simula que el job murió después de crear la alerta y antes de marcar el evento.
        $evento->update(['resultado' => 'pendiente', 'alerta_id' => null]);

        app(CorrelacionService::class)->correlacionar($evento->id);

        $alerta = Alerta::sole();
        $this->assertSame([$evento->id], $alerta->eventos_ids);
        $this->assertSame(1, $alerta->num_eventos);
        $this->assertSame('alerta', $evento->fresh()->resultado->value);
        $this->assertSame(1, EntradaBitacora::where('tipo', 'alerta_creada')->count());
        Event::assertDispatchedTimes(AlertaCreada::class, 2);  // al menos una vez, nunca cero
    }

    public function test_un_job_duplicado_que_llega_tarde_no_vuelve_a_notificar(): void
    {
        $datos = ['uuid' => (string) Str::uuid(), 'camara_id' => $this->camara->id, 'tipo_objeto' => 'persona',
            'cantidad' => 1, 'confianza' => 0.9, 'fecha_hora' => now()->toIso8601String()];
        Event::fake([AlertaCreada::class, EventoRegistrado::class]);
        $this->postJson(route('api.v1.agente.eventos'), $datos)->assertStatus(202);
        $copiaVieja = Evento::where('uuid', $datos['uuid'])->first();   // como la lee el job reencolado
        $zona = app(EventoService::class)->zonaDe($copiaVieja);

        app(CorrelacionService::class)->correlacionar($copiaVieja->id);  // el job original
        $alerta = app(AlertaService::class)->crearOAgrupar(               // el reencolado, que ya pasó el filtro
            $copiaVieja, $zona, 'sin_registro_en_porteria', ResultadoRegla::alerta('x', 3),
        );

        $this->assertSame(1, Alerta::count());
        $this->assertSame(Alerta::sole()->id, $alerta->id);
        Event::assertDispatchedTimes(AlertaCreada::class, 1);
    }

    public function test_un_evento_que_perdio_su_job_termina_correlacionado_al_reencolarlo(): void
    {
        // La ingesta no encola nada: el job se "pierde".
        $evento = Event::fakeFor(fn () => $this->enviarEvento(), [EventoRegistrado::class]);
        $this->assertSame('pendiente', $evento->resultado->value);
        Carbon::setTestNow(now()->addMinutes(5));

        $this->artisan('nexora:reencolar-pendientes')->assertSuccessful();  // listener real, cola sync

        Carbon::setTestNow();
        $this->assertSame('alerta', $evento->fresh()->resultado->value);
        $this->assertSame(1, Alerta::count());
    }

    public function test_una_regla_nueva_se_enchufa_sin_tocar_el_motor(): void
    {
        config(['alertas.reglas' => [ReglaVehiculosDeNoche::class, ...config('alertas.reglas')]]);

        $persona = $this->enviarEvento();
        $vehiculo = $this->enviarEvento(['tipo_objeto' => 'vehiculo']);

        $this->assertSame('alerta', $persona->resultado->value);
        $this->assertSame('explicado', $vehiculo->resultado->value);
        $this->assertSame(5, $vehiculo->registro_id);
    }

    public function test_un_evento_ya_resuelto_no_se_vuelve_a_correlacionar(): void
    {
        $evento = $this->enviarEvento();

        app(CorrelacionService::class)->correlacionar($evento->id);

        $this->assertSame(1, Alerta::first()->num_eventos);
    }
}

/** Regla de prueba: va antes que la de portería y da por explicado cualquier vehículo. */
class ReglaVehiculosDeNoche implements ReglaDeteccion
{
    public function nombre(): string
    {
        return 'vehiculos_de_noche';
    }

    public function evaluar(Evento $evento, Zona $zona): ResultadoRegla
    {
        return $evento->tipo_objeto === 'vehiculo' ? ResultadoRegla::explicado(5) : ResultadoRegla::sinDecision();
    }
}

class PorteriaFalsa implements ConsultaRegistrosPorteria
{
    public ?RegistroPorteria $registro = null;

    public ?array $consulta = null;

    public function buscarParaCorrelacion(int $zonaId, CarbonInterface $momento, int $ventanaMin, int $cantidad): ?RegistroPorteria
    {
        $this->consulta = [$zonaId, $ventanaMin, $cantidad];

        return $this->registro;
    }
}
