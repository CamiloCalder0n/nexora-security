<?php

namespace App\Modules\Alertas\Infrastructure\Console;

use App\Modules\Alertas\Application\Telegram\ProcesadorTelegram;
use App\Modules\Alertas\Application\Telegram\TelegramFallo;
use App\Modules\Alertas\Infrastructure\Telegram\TelegramBotApi;
use Illuminate\Console\Command;

/** Deja el bot con sus comandos y elige cómo recibe: webhook (producción) o long polling (local). */
class ConfigurarTelegram extends Command
{
    protected $signature = 'nexora:telegram-configurar
        {--webhook= : URL pública https del backend (producción); registra el webhook con el secreto}
        {--sin-webhook : Quita el webhook para usar nexora:telegram-escuchar en local}';

    protected $description = 'Configura el bot de Telegram (comandos y forma de recibir)';

    public function handle(TelegramBotApi $bot): int
    {
        if (! $bot->disponible()) {
            $this->error('Falta TELEGRAM_BOT_TOKEN en el .env.');

            return self::FAILURE;
        }

        $webhook = $this->option('webhook');
        if ($webhook !== null && ! $this->webhookValido($webhook)) {
            return self::FAILURE;
        }

        try {
            $yo = $bot->llamar('getMe');
            $bot->llamar('setMyCommands', ['commands' => [
                ['command' => 'start', 'description' => 'Vincular tu cuenta: /start CODIGO'],
                ['command' => 'ayuda', 'description' => 'Ver con qué cuenta estás vinculado'],
            ]]);

            if ($webhook !== null) {
                $bot->llamar('setWebhook', [
                    'url' => rtrim($webhook, '/').route('api.v1.alertas.telegram.webhook', absolute: false),
                    'secret_token' => config('alertas.telegram.webhook_secret'),
                    'allowed_updates' => ProcesadorTelegram::ACTUALIZACIONES,
                ]);
            } elseif ($this->option('sin-webhook')) {
                $bot->llamar('deleteWebhook');
            }
            $info = $bot->llamar('getWebhookInfo');
        } catch (TelegramFallo $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Bot @{$yo['username']} configurado.");
        $this->line(filled($info['url'] ?? '')
            ? "Recibe por webhook en {$info['url']}"
            : 'Sin webhook: en local corre php artisan nexora:telegram-escuchar');

        return self::SUCCESS;
    }

    private function webhookValido(string $url): bool
    {
        if (! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->error('El webhook necesita una URL https:// válida (Telegram no acepta http).');

            return false;
        }
        if (strlen((string) config('alertas.telegram.webhook_secret')) < 16
            || ! preg_match('/\A[A-Za-z0-9_-]+\z/', (string) config('alertas.telegram.webhook_secret'))) {
            $this->error('Define TELEGRAM_WEBHOOK_SECRET (mínimo 16 caracteres: letras, números, _ o -).');

            return false;
        }

        return true;
    }
}
