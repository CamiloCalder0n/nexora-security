<?php

namespace Tests\Feature\Alertas;

use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Domain\Events\AlertaCreada;
use App\Modules\Alertas\Domain\Events\AlertaEscalada;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use App\Modules\Alertas\Infrastructure\Repositories\AlertaRepository;
use App\Modules\Monitoreo\Application\CamaraService;
use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Events\CamaraSinSenal;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class TareasProgramadasTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([AlertaCreada::class, AlertaEscalada::class]);
        Carbon::setTestNow(Carbon::parse('2026-11-06 22:30:00', 'America/Bogota'));
    }

    private function camara(?Carbon $ultimaConexion, string $nombre = 'CAM-01 Parqueadero', string $zona = 'Parqueadero sótano'): Camara
    {
        return Camara::factory()
            ->for(Zona::factory()->create(['nombre' => $zona]))
            ->create(['nombre' => $nombre, 'estado' => EstadoCamara::Activa, 'ultima_conexion' => $ultimaConexion]);
    }

    public function test_camara_sin_heartbeat_por_mas_de_5_min_queda_sin_senal_con_una_alerta(): void
    {
        $caida = $this->camara(now()->subMinutes(6));
        $viva = $this->camara(now()->subMinutes(2), 'CAM-02 Portería', 'Portería');
        $nuncaConectada = $this->camara(null, 'CAM-03 Piscina', 'Piscina');

        $this->artisan('nexora:camaras-sin-senal')->expectsOutputToContain('Cámaras sin señal: 1')->assertSuccessful();

        $this->assertSame(EstadoCamara::SinSenal, $caida->fresh()->estado);
        $this->assertSame(EstadoCamara::Activa, $viva->fresh()->estado);
        $this->assertSame(EstadoCamara::Activa, $nuncaConectada->fresh()->estado);

        $alerta = Alerta::sole();
        $this->assertSame('camara_sin_senal', $alerta->tipo->value);
        $this->assertSame($caida->id, $alerta->camara['camara_id']);
        $this->assertStringContainsString('CAM-01 Parqueadero (Parqueadero sótano) sin señal desde las 22:24', $alerta->motivo);
        Event::assertDispatched(AlertaCreada::class);
    }

    public function test_una_sola_alerta_por_caida_aunque_se_reporte_dos_veces(): void
    {
        $camara = $this->camara(now()->subMinutes(6));
        $this->artisan('nexora:camaras-sin-senal');

        app(AlertaService::class)->alertarCamaraSinSenal(new CamaraSinSenal(
            $camara->id, $camara->nombre, $camara->zona_id, 'Parqueadero sótano', $camara->ultima_conexion->toIso8601String(), 5,
        ));

        $this->assertSame(1, Alerta::count());
    }

    public function test_si_la_camara_vuelve_y_se_cae_otra_vez_hay_alerta_nueva(): void
    {
        $camara = $this->camara(now()->subMinutes(6));
        $this->artisan('nexora:camaras-sin-senal');

        // vuelve (heartbeat) sin que nadie cierre la alerta, y a la hora se cae otra vez
        Carbon::setTestNow(now()->addMinutes(2));
        app(CamaraService::class)->registrarLatido($camara->fresh());
        Carbon::setTestNow(now()->addHour());
        $this->artisan('nexora:camaras-sin-senal');

        $this->assertSame(2, Alerta::count());
    }

    public function test_no_escala_una_alerta_que_atendieron_mientras_se_revisaba(): void
    {
        $alerta = $this->alerta(3, 6);
        // Repositorio que, justo después de leer la lista, deja la alerta atendida (como si el
        // vigilante tocara "Atender" en Telegram en ese instante).
        $this->app->instance(AlertaRepository::class, new class extends AlertaRepository
        {
            public function paraEscalar(int $prioridad, CarbonInterface $limite): Collection
            {
                $lista = parent::paraEscalar($prioridad, $limite);
                Alerta::query()->update(['estado' => 'atendida']);

                return $lista;
            }
        });

        $this->assertSame(0, app(AlertaService::class)->escalarVencidas());
        $this->assertSame(0, $alerta->fresh()->nivel_escalamiento);
    }

    private function alerta(int $prioridad, int $minutosAtras, string $estado = 'abierta'): Alerta
    {
        return $this->crearAlerta(['fecha_hora' => now()->subMinutes($minutosAtras), 'prioridad' => $prioridad, 'estado' => $estado]);
    }

    public function test_escala_las_de_prioridad_alta_sin_atender_por_mas_de_5_min(): void
    {
        $vencida = $this->alerta(3, 6);
        $reciente = $this->alerta(3, 2);
        $baja = $this->alerta(2, 30);
        $atendida = $this->alerta(3, 30, 'atendida');

        $this->artisan('nexora:escalar-alertas')->expectsOutputToContain('Alertas escaladas: 1')->assertSuccessful();

        $this->assertSame(1, $vencida->fresh()->nivel_escalamiento);
        $this->assertSame([0, 0, 0], [$reciente->fresh()->nivel_escalamiento, $baja->fresh()->nivel_escalamiento, $atendida->fresh()->nivel_escalamiento]);
        $this->assertSame('alerta_escalada', EntradaBitacora::sole()->tipo);
        Event::assertDispatched(AlertaEscalada::class, fn ($e) => $e->alertaId === $vencida->id && $e->nivel === 1);
    }

    public function test_sube_un_nivel_cada_5_min_y_no_cada_minuto(): void
    {
        $alerta = $this->alerta(3, 6);

        $this->artisan('nexora:escalar-alertas');
        Carbon::setTestNow(now()->addMinute());
        $this->artisan('nexora:escalar-alertas');
        $this->assertSame(1, $alerta->fresh()->nivel_escalamiento);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->artisan('nexora:escalar-alertas');
        $this->assertSame(2, $alerta->fresh()->nivel_escalamiento);
    }
}
