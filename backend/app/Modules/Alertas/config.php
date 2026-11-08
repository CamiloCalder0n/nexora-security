<?php

use App\Modules\Alertas\Application\Reglas\SinRegistroEnPorteria;

return [
    // Reglas del motor de correlación, en orden: decide la primera que tenga algo que decir.
    // Agregar una regla = crear la clase (ReglaDeteccion) y ponerla aquí, sin tocar el motor (HU31).
    'reglas' => [
        SinRegistroEnPorteria::class,
    ],

    // Escalamiento (HU12): las de esta prioridad suben un nivel cada N minutos sin atender.
    'prioridad_alta' => 3,
    'minutos_escalar' => (int) env('ALERTAS_MINUTOS_ESCALAR', 5),

    // Bot de Telegram (HU21, HU24). Sin token no se envía nada: la alerta igual queda en el panel.
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        // Telegram lo manda en la cabecera X-Telegram-Bot-Api-Secret-Token; sin él el webhook responde 404.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'minutos_codigo' => 15,
    ],
];
