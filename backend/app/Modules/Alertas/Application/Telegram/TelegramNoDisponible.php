<?php

namespace App\Modules\Alertas\Application\Telegram;

/** Falla pasajera (sin red, 5xx o 429): se reintenta, respetando `retry_after` si Telegram lo da. */
class TelegramNoDisponible extends TelegramFallo
{
    public function __construct(string $mensaje, public readonly ?int $reintentarEnSeg = null)
    {
        parent::__construct($mensaje);
    }
}
