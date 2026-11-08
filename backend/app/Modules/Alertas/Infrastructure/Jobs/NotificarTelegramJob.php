<?php

namespace App\Modules\Alertas\Infrastructure\Jobs;

use App\Modules\Alertas\Application\Telegram\NotificadorTelegram;
use App\Modules\Alertas\Application\Telegram\TelegramNoDisponible;
use App\Modules\Alertas\Application\Telegram\TelegramRechazo;
use App\Modules\Alertas\Application\Telegram\VinculacionTelegram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Un aviso a un usuario. Va un job por destinatario para que, si Telegram falla con uno, el
 * reintento no le repita el mensaje a los demás. La alerta igual queda en el panel.
 */
class NotificarTelegramJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    /** Mientras uno está en cola, otro igual no se encola (AlertaCreada repetido). */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $alertaId,
        public readonly int $usuarioId,
        public readonly ?int $nivel = null,
    ) {
        // Cola propia, que el worker atiende primero: una ráfaga de correlaciones no retrasa el aviso (HU21).
        $this->onQueue('telegram');
    }

    public function uniqueId(): string
    {
        return "{$this->alertaId}:".($this->nivel ?? 0).":{$this->usuarioId}";
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(NotificadorTelegram $notificador, VinculacionTelegram $vinculacion): void
    {
        try {
            $notificador->enviar($this->alertaId, $this->usuarioId, $this->nivel);
        } catch (TelegramNoDisponible $e) {
            if ($e->reintentarEnSeg !== null && $this->attempts() < $this->tries) {
                $this->release($e->reintentarEnSeg);

                return;
            }
            throw $e;
        } catch (TelegramRechazo $e) {
            Log::warning('Telegram rechazó el aviso de la alerta', [
                'alerta_id' => $this->alertaId, 'usuario_id' => $this->usuarioId, 'motivo' => $e->getMessage(),
            ]);
            if ($e->chatPerdido()) {
                $vinculacion->desvincularUsuario($this->usuarioId);
            }
            $this->fail($e);
        }
    }
}
