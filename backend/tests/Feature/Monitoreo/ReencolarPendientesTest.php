<?php

namespace Tests\Feature\Monitoreo;

use App\Modules\Monitoreo\Domain\Events\EventoPendienteReencolado;
use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use App\Modules\Monitoreo\Domain\Models\Evento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class ReencolarPendientesTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EventoRegistrado::class, EventoPendienteReencolado::class]);
    }

    private function evento(int $minutosAtras, string $resultado = 'pendiente'): Evento
    {
        Carbon::setTestNow(now()->subMinutes($minutosAtras));
        $evento = $this->crearEvento(['resultado' => $resultado]);
        Carbon::setTestNow();

        return $evento;
    }

    public function test_reencola_solo_los_pendientes_que_perdieron_su_job(): void
    {
        $perdido = $this->evento(5);
        $this->evento(1);                     // recién llegado: su job todavía puede estar en cola
        $this->evento(5, 'alerta');           // ya resuelto
        $this->evento(60 * 25);               // de hace más de 24 h

        $this->artisan('nexora:reencolar-pendientes')->expectsOutputToContain('Eventos reencolados: 1')->assertSuccessful();

        Event::assertDispatchedTimes(EventoPendienteReencolado::class, 1);
        Event::assertDispatched(EventoPendienteReencolado::class, fn ($e) => $e->eventoId === $perdido->id);
        Event::assertNotDispatched(EventoRegistrado::class);  // no se cuenta como evento nuevo
    }

    public function test_espera_creciente_y_tope_de_reencolados(): void
    {
        $evento = $this->evento(5);
        $corridas = [];

        // 24 h de scheduler cada minuto con un evento que nunca sale de pendiente.
        for ($minuto = 1; $minuto <= 60 * 24; $minuto++) {
            Carbon::setTestNow(now()->addMinute());
            Artisan::call('nexora:reencolar-pendientes');
            if (str_contains(Artisan::output(), 'reencolados: 1')) {
                $corridas[] = $minuto;
            }
        }
        Carbon::setTestNow();

        $this->assertCount(config('monitoreo.max_reencolos'), $corridas);
        $this->assertSame(config('monitoreo.max_reencolos'), $evento->fresh()->reencolos);
        $saltos = array_map(fn ($a, $b) => $b - $a, array_slice($corridas, 0, -1), array_slice($corridas, 1));
        $this->assertSame([4, 8, 16, 32], $saltos);  // espera creciente: 2 × 2^intento minutos
    }

    public function test_respeta_el_tope_por_corrida(): void
    {
        config(['monitoreo.reencolar_por_minuto' => 3]);
        foreach (range(1, 5) as $_) {
            $this->evento(5);
        }

        $this->artisan('nexora:reencolar-pendientes')->expectsOutputToContain('Eventos reencolados: 3');
    }

    public function test_esta_agendado_cada_minuto(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('nexora:reencolar-pendientes')->assertSuccessful();
    }
}
