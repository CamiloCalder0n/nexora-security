<?php

namespace App\Modules\Alertas\Application\Telegram;

use RuntimeException;

/** Cualquier falla al hablar con la Bot API: pasajera (TelegramNoDisponible) o definitiva (TelegramRechazo). */
abstract class TelegramFallo extends RuntimeException {}
