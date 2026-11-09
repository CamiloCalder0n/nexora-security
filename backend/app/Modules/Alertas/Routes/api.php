<?php

use App\Modules\Alertas\Http\Controllers\Api\V1\AlertaController;
use App\Modules\Alertas\Http\Controllers\Api\V1\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.alertas.')->group(function () {
    // Sin Sanctum ni throttle por IP: lo llama Telegram (todas las actualizaciones llegan desde sus
    // pocas IP) y se autentica con el secreto. El spam de chats desconocidos se limita en el procesador.
    Route::post('telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');

    // Panel del vigilante (HU11, HU03). Hoy con token Sanctum; con la sesión del panel cuando Identidad
    // active statefulApi. Quién puede usarla lo decide AlertaService::puedeAtender (igual que en Telegram).
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('alertas', [AlertaController::class, 'index'])->name('index');
        Route::post('alertas/{alerta}/atender', [AlertaController::class, 'atender'])->name('atender')->where('alerta', '[0-9a-f]{24}');
        Route::post('alertas/{alerta}/cerrar', [AlertaController::class, 'cerrar'])->name('cerrar')->where('alerta', '[0-9a-f]{24}');
    });
});
