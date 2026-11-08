<?php

namespace App\Modules\Alertas\Infrastructure\Console;

use App\Modules\Alertas\Application\Contracts\BotTelegram;
use App\Modules\Alertas\Application\Telegram\TelegramFallo;
use App\Modules\Alertas\Application\Telegram\VinculacionTelegram;
use App\Modules\Alertas\Infrastructure\Repositories\CuentaTelegramRepository;
use Illuminate\Console\Command;

/** Código de vinculación para un usuario (mientras el panel no tenga su botón). */
class CodigoTelegram extends Command
{
    protected $signature = 'nexora:telegram-codigo {email : Correo del usuario del panel}';

    protected $description = 'Genera el código de un solo uso para vincular una cuenta con el bot';

    public function handle(VinculacionTelegram $vinculacion, CuentaTelegramRepository $cuentas, BotTelegram $bot): int
    {
        $usuario = $cuentas->activaPorEmail($this->argument('email'));
        if ($usuario === null) {
            $this->error('No hay un usuario activo con ese correo.');

            return self::FAILURE;
        }

        $codigo = $vinculacion->generarCodigo($usuario);
        $minutos = config('alertas.telegram.minutos_codigo');
        $this->info("Código para {$usuario->name}: {$codigo} (vence en {$minutos} min, un solo uso)");

        try {
            $this->line("Enlace directo: https://t.me/{$bot->llamar('getMe')['username']}?start={$codigo}");
        } catch (TelegramFallo) {
            $this->line("En el bot, envía: /start {$codigo}");
        }

        return self::SUCCESS;
    }
}
