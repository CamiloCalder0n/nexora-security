<?php

use App\Modules\Alertas\Http\Controllers\Api\V1\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.alertas.')->group(function () {
    // Sin Sanctum ni throttle por IP: lo llama Telegram (todas las actualizaciones llegan desde sus
    // pocas IP) y se autentica con el secreto. El spam de chats desconocidos se limita en el procesador.
    Route::post('telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
});
