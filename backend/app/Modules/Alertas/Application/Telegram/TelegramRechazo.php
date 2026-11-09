<?php

namespace App\Modules\Alertas\Application\Telegram;

/** Telegram rechazó la llamada (chat bloqueado, token inválido, datos malos): reintentar no sirve. */
class TelegramRechazo extends TelegramFallo
{
    public function __construct(string $mensaje, public readonly int $codigo)
    {
        parent::__construct($mensaje);
    }

    /** El usuario bloqueó el bot o el chat ya no existe: hay que dejar de escribirle. */
    public function chatPerdido(): bool
    {
        return $this->codigo === 403 || ($this->codigo === 400 && str_contains($this->getMessage(), 'chat not found'));
    }
}
