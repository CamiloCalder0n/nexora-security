<?php

namespace Tests\Feature\Porteria;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Application\AutorizacionService;
use App\Modules\Porteria\Application\Contracts\ConsultaRegistrosPorteria;
use App\Modules\Porteria\Application\RegistroPorteriaService;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Events\IngresoRegistrado;
use App\Modules\Porteria\Domain\Exceptions\AutorizacionFueraDeVentana;
use App\Modules\Porteria\Domain\Exceptions\AutorizacionNoVigente;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use App\Modules\Porteria\Domain\TipoIngreso;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReglasPorteriaTest extends TestCase
{
    use RefreshDatabase;

    private User $vigilante;

    private Zona $zona;

    private Unidad $unidad;

    private Residente $residente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $this->zona = Zona::factory()->create();
        $this->unidad = Unidad::factory()->create();
        $this->residente = Residente::factory()->create();
        $this->residente->unidades()->attach($this->unidad, ['es_titular' => true]);
    }

    private function autorizacion(array $atributos = []): Autorizacion
    {
        return Autorizacion::factory()->paraResidente($this->residente, $this->unidad)->create([
            'valida_desde' => now()->subHour(),
            'valida_hasta' => now()->addHours(5),
            ...$atributos,
        ]);
    }

    private function registrarCon(Autorizacion $autorizacion): RegistroPorteria
    {
        return app(RegistroPorteriaService::class)->registrar($this->vigilante, [
            'tipo_ingreso' => TipoIngreso::Visitante->value,
            'cantidad_personas' => 1,
            'zona_id' => $this->zona->id,
            'autorizacion_id' => $autorizacion->id,
        ]);
    }

    public function test_registrar_con_autorizacion_la_consume_y_dispara_evento(): void
    {
        Event::fake([IngresoRegistrado::class]);
        $autorizacion = $this->autorizacion();

        $registro = $this->registrarCon($autorizacion);

        $this->assertSame($autorizacion->visitante_nombre, $registro->nombre_visitante);
        $this->assertSame($this->unidad->id, $registro->unidad_id);
        $this->assertSame(EstadoAutorizacion::Usada, $autorizacion->fresh()->estado);
        Event::assertDispatched(IngresoRegistrado::class, fn ($e) => $e->registro->is($registro));
    }

    public function test_consumir_dos_veces_responde_409(): void
    {
        $autorizacion = $this->autorizacion();
        $this->registrarCon($autorizacion);

        try {
            $this->registrarCon($autorizacion);
            $this->fail('Debió lanzar AutorizacionNoVigente');
        } catch (AutorizacionNoVigente $e) {
            $this->assertSame(409, $e->status());
        }

        $this->assertSame(1, RegistroPorteria::count());
    }

    public function test_fuera_de_ventana_responde_422_y_queda_expirada(): void
    {
        $autorizacion = $this->autorizacion([
            'valida_desde' => now()->subDays(2),
            'valida_hasta' => now()->subDay(),
        ]);

        try {
            $this->registrarCon($autorizacion);
            $this->fail('Debió lanzar AutorizacionFueraDeVentana');
        } catch (AutorizacionFueraDeVentana $e) {
            $this->assertSame(422, $e->status());
        }

        $this->assertSame(EstadoAutorizacion::Expirada, $autorizacion->fresh()->estado);
        $this->assertSame(0, RegistroPorteria::count());
    }

    public function test_antes_de_la_ventana_responde_422_sin_expirar(): void
    {
        $autorizacion = $this->autorizacion(['valida_desde' => now()->addHour(), 'valida_hasta' => now()->addHours(3)]);

        $this->expectException(AutorizacionFueraDeVentana::class);

        try {
            $this->registrarCon($autorizacion);
        } finally {
            $this->assertSame(EstadoAutorizacion::Vigente, $autorizacion->fresh()->estado);
        }
    }

    public function test_residente_con_unidad_ajena_es_403(): void
    {
        $ajena = Unidad::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(AutorizacionService::class)->crear($this->residente, [
            'unidad_id' => $ajena->id,
            'visitante_nombre' => 'Pedro Pérez',
            'visitante_documento' => '123',
            'valida_desde' => now(),
            'valida_hasta' => now()->addDay(),
        ]);
    }

    public function test_autorizacion_de_mas_de_7_dias_es_422(): void
    {
        $this->expectException(ValidationException::class);

        app(AutorizacionService::class)->crear($this->residente, [
            'unidad_id' => $this->unidad->id,
            'visitante_nombre' => 'Pedro Pérez',
            'visitante_documento' => '123',
            'valida_desde' => now(),
            'valida_hasta' => now()->addDays(8),
        ]);
    }

    public function test_anular_solo_si_esta_vigente(): void
    {
        $servicio = app(AutorizacionService::class);
        $autorizacion = $this->autorizacion();

        $servicio->anular($autorizacion);
        $this->assertSame(EstadoAutorizacion::Anulada, $autorizacion->fresh()->estado);

        $this->expectException(AutorizacionNoVigente::class);
        $servicio->anular($autorizacion->fresh());
    }

    public function test_expirar_vencidas(): void
    {
        $vencida = $this->autorizacion(['valida_desde' => now()->subDays(2), 'valida_hasta' => now()->subMinute()]);
        $vigente = $this->autorizacion();

        $this->assertSame(1, app(AutorizacionService::class)->expirarVencidas());
        $this->assertSame(EstadoAutorizacion::Expirada, $vencida->fresh()->estado);
        $this->assertSame(EstadoAutorizacion::Vigente, $vigente->fresh()->estado);
    }

    public function test_puerto_de_correlacion_encuentra_el_registro_mas_cercano(): void
    {
        $momento = now();
        $crear = fn (int $minutos, int $personas = 1, ?Zona $zona = null) => RegistroPorteria::factory()->create([
            'zona_id' => ($zona ?? $this->zona)->id,
            'unidad_id' => $this->unidad->id,
            'cantidad_personas' => $personas,
            'fecha_ingreso' => $momento->copy()->addMinutes($minutos),
        ]);
        $crear(-8);
        $cercano = $crear(3);
        $crear(1, 1, Zona::factory()->create());
        $crear(-30);

        $puerto = app(ConsultaRegistrosPorteria::class);

        $this->assertTrue($puerto->buscarParaCorrelacion($this->zona->id, $momento, 10, 1)->is($cercano));
        $this->assertNull($puerto->buscarParaCorrelacion($this->zona->id, $momento, 2, 1));
        $this->assertNull($puerto->buscarParaCorrelacion($this->zona->id, $momento, 10, 3));
    }
}
