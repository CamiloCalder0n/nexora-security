<?php

namespace App\Modules\Alertas\Infrastructure\Listeners;

use App\Modules\Alertas\Application\Contracts\BotTelegram;
use App\Modules\Alertas\Application\Telegram\VinculacionTelegram;
use App\Modules\Alertas\Domain\Events\AlertaCreada;
use App\Modules\Alertas\Domain\Events\AlertaEscalada;
use App\Modules\Alertas\Infrastructure\Jobs\NotificarTelegramJob;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Support\Facades\Log;

/**
 * Reparte el aviso: una alerta nueva va a los vigilantes (HU21); una escalada también a la
 * administración (HU12). Cada destinatario sale en su propio job.
 */
class AvisarPorTelegram
{
    public function __construct(
        private readonly BotTelegram $bot,
        private readonly VinculacionTelegram $vinculacion,
    ) {}

    public function handle(AlertaCreada|AlertaEscalada $evento): void
    {
        if (! $this->bot->disponible()) {
            return;
        }

        $nivel = $evento instanceof AlertaEscalada ? $evento->nivel : null;
        $roles = $nivel === null ? [Rol::Vigilante] : [Rol::Vigilante, Rol::Admin];
        $destinatarios = $this->vinculacion->destinatarios($roles);

        if ($destinatarios->isEmpty()) {
            Log::warning('Alerta sin nadie vinculado a Telegram para avisar', ['alerta_id' => $evento->alertaId, 'nivel' => $nivel]);

            return;
        }

        foreach ($destinatarios as $usuario) {
            NotificarTelegramJob::dispatch($evento->alertaId, $usuario->id, $nivel);
        }
    }
}
