<?php

namespace Tests\Feature\Monitoreo;

use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use App\Modules\Monitoreo\Domain\Models\Evento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class ReencolarPendientesTest extends TestCase
{
    use LimpiaMongo;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EventoRegistrado::class]);
    }

    private function evento(int $minutosAtras, string $resultado = 'pendiente'): Evento
    {
        Carbon::setTestNow(now()->subMinutes($minutosAtras));
        $evento = Evento::create(['uuid' => (string) Str::uuid(), 'fecha_hora' => now(), 'tipo_objeto' => 'persona', 'resultado' => $resultado]);
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

        Event::assertDispatchedTimes(EventoRegistrado::class, 1);
        Event::assertDispatched(EventoRegistrado::class, fn ($e) => $e->eventoId === $perdido->id);
    }

    public function test_reencola_cada_pocos_minutos_y_se_detiene_en_el_tope(): void
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

        $this->assertSame(range(1, 19, 2), $corridas);  // cada 2 min sin cambios, y no más de 10 veces
        $this->assertSame(10, $evento->fresh()->reencolos);
    }

    public function test_esta_agendado_cada_minuto(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('nexora:reencolar-pendientes')->assertSuccessful();
    }
}
