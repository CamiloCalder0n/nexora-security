<?php

namespace App\Modules\Alertas\Infrastructure\Listeners;

use App\Modules\Alertas\Domain\Events\AlertaCreada;
use App\Modules\Alertas\Domain\Events\AlertaEscalada;
use App\Modules\Alertas\Infrastructure\Jobs\NotificarTelegramJob;
use App\Modules\Alertas\Infrastructure\Repositories\CuentaTelegramRepository;
use App\Modules\Alertas\Infrastructure\Telegram\TelegramBotApi;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Reparte el aviso: una alerta nueva va a los vigilantes (HU21); una escalada también a la
 * administración (HU12). Cada destinatario sale en su propio job.
 *
 * Va en cola: así repartir el aviso no corre dentro del lock de la zona, y si falla (PostgreSQL
 * caído) se reintenta durante unos 2 min sin deshacer la alerta, que ya quedó guardada (y sigue en el panel).
 */
class AvisarPorTelegram implements ShouldQueue
{
    public string $queue = 'telegram';

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        private readonly TelegramBotApi $bot,
        private readonly CuentaTelegramRepository $cuentas,
    ) {}

    public function handle(AlertaCreada|AlertaEscalada $evento): void
    {
        if (! $this->bot->disponible()) {
            return;
        }

        $nivel = $evento instanceof AlertaEscalada ? $evento->nivel : null;
        $roles = $nivel === null ? [Rol::Vigilante] : [Rol::Vigilante, Rol::Admin];
        $destinatarios = $this->cuentas->vinculadasConRol($roles);

        if ($destinatarios->isEmpty()) {
            Log::warning('Alerta sin nadie vinculado a Telegram para avisar', ['alerta_id' => $evento->alertaId, 'nivel' => $nivel]);

            return;
        }

        foreach ($destinatarios as $usuario) {
            NotificarTelegramJob::dispatch($evento->alertaId, $usuario->id, $nivel);
        }
    }
}
