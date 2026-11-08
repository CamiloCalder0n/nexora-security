<?php

namespace App\Modules\Alertas\Infrastructure\Console;

use App\Modules\Alertas\Application\Contracts\BotTelegram;
use App\Modules\Alertas\Application\Telegram\ProcesadorTelegram;
use App\Modules\Alertas\Application\Telegram\TelegramNoDisponible;
use App\Modules\Alertas\Application\Telegram\TelegramRechazo;
use Illuminate\Console\Command;

/**
 * Recibe lo del bot por long polling, para local, donde Telegram no puede llegar a un webhook.
 * Telegram responde 409 si hay un webhook registrado o si ya hay otro proceso escuchando.
 */
class EscucharTelegram extends Command
{
    protected $signature = 'nexora:telegram-escuchar {--una-vez : Procesa una tanda y termina}';

    protected $description = 'Escucha el bot de Telegram por long polling (solo en local)';

    private const ESPERA_SEG = 25;

    private bool $seguir = true;

    public function handle(BotTelegram $bot, ProcesadorTelegram $procesador): int
    {
        if (! $bot->disponible()) {
            $this->error('Falta TELEGRAM_BOT_TOKEN en el .env.');

            return self::FAILURE;
        }

        $this->trap([SIGTERM, SIGINT], fn () => $this->seguir = false);
        $this->info('Escuchando el bot (Ctrl+C para salir)...');
        $offset = 0;

        do {
            try {
                $actualizaciones = $bot->llamar('getUpdates', [
                    'offset' => $offset,
                    'timeout' => $this->option('una-vez') ? 0 : self::ESPERA_SEG,
                    'allowed_updates' => ProcesadorTelegram::ACTUALIZACIONES,
                ], self::ESPERA_SEG + 10);
            } catch (TelegramRechazo $e) {
                $this->error(match (true) {
                    $e->codigo === 409 && str_contains($e->getMessage(), 'webhook') => 'Hay un webhook registrado. Para escuchar en local: php artisan nexora:telegram-configurar --sin-webhook',
                    $e->codigo === 409 => 'Ya hay otro proceso escuchando este bot (solo puede haber uno). Detén el otro nexora:telegram-escuchar.',
                    default => $e->getMessage(),
                });

                return self::FAILURE;
            } catch (TelegramNoDisponible $e) {
                if ($this->option('una-vez')) {
                    $this->error($e->getMessage());

                    return self::FAILURE;
                }
                $espera = $e->reintentarEnSeg ?? 5;
                $this->warn("{$e->getMessage()} Reintento en {$espera} s.");
                sleep($espera);

                continue;
            }

            foreach ($actualizaciones as $actualizacion) {
                $offset = max($offset, $actualizacion['update_id'] + 1);
                $procesador->procesar($actualizacion);
            }
        } while ($this->seguir && ! $this->option('una-vez'));

        return self::SUCCESS;
    }
}
