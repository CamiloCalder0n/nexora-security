<?php

namespace App\Modules\Alertas\Infrastructure\Telegram;

use App\Modules\Alertas\Application\Telegram\TelegramNoDisponible;
use App\Modules\Alertas\Application\Telegram\TelegramRechazo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP de la Bot API (sin interfaz: tiene una sola implementación y los tests falsean HTTP).
 * El token va en la URL, así que ningún mensaje de error que salga de aquí incluye la URL:
 * terminaría en los logs o en failed_jobs.
 */
class TelegramBotApi
{
    public function __construct(private readonly ?string $token) {}

    public function disponible(): bool
    {
        return filled($this->token);
    }

    /**
     * Llama un método de la Bot API y devuelve su `result`.
     *
     * @throws TelegramNoDisponible si conviene reintentar (red, 5xx, 429)
     * @throws TelegramRechazo si Telegram dijo que no y reintentar no sirve (400, 401, 403, 404)
     */
    public function llamar(string $metodo, array $parametros = [], int $timeoutSeg = 10): mixed
    {
        if (! $this->disponible()) {
            throw new TelegramRechazo("Telegram sin configurar (falta TELEGRAM_BOT_TOKEN): {$metodo} no se envió.", 401);
        }

        try {
            $respuesta = Http::connectTimeout(5)->timeout($timeoutSeg)->acceptJson()
                ->post("https://api.telegram.org/bot{$this->token}/{$metodo}", $parametros);
        } catch (ConnectionException) {
            throw new TelegramNoDisponible("Sin conexión con Telegram ({$metodo}).");
        }

        $cuerpo = $respuesta->json();
        if ($respuesta->successful() && ($cuerpo['ok'] ?? false) === true) {
            return $cuerpo['result'] ?? null;
        }

        $codigo = $respuesta->status();
        $detalle = mb_substr((string) ($cuerpo['description'] ?? 'sin detalle'), 0, 200);
        if ($codigo === 429 || $codigo >= 500) {
            throw new TelegramNoDisponible("Telegram respondió {$codigo} en {$metodo}: {$detalle}", $cuerpo['parameters']['retry_after'] ?? null);
        }

        throw new TelegramRechazo("Telegram rechazó {$metodo} ({$codigo}): {$detalle}", $codigo);
    }
}
