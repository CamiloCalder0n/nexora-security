<?php

namespace App\Modules\Alertas\Application\Contracts;

use App\Modules\Alertas\Application\Telegram\TelegramNoDisponible;
use App\Modules\Alertas\Application\Telegram\TelegramRechazo;

/** Puerto hacia la Bot API de Telegram. */
interface BotTelegram
{
    /** ¿Hay token configurado? Sin él no se intenta nada. */
    public function disponible(): bool;

    /**
     * Llama un método de la Bot API y devuelve su `result`.
     *
     * @throws TelegramNoDisponible si conviene reintentar (red, 5xx, 429)
     * @throws TelegramRechazo si Telegram dijo que no y reintentar no sirve (400, 401, 403, 404)
     */
    public function llamar(string $metodo, array $parametros = [], int $timeoutSeg = 10): mixed;
}
