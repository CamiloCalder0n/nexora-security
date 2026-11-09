<?php

namespace App\Modules\Alertas\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Alertas\Application\Telegram\ProcesadorTelegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entrada del bot en producción. Solo Telegram conoce el secreto que se registró con setWebhook
 * (nexora:telegram-configurar --webhook=...). Responde 200 aunque la actualización falle por
 * dentro, para no trabar las siguientes. La excepción es la caché del dedup: si falla, responde
 * 500 a propósito y Telegram la reintenta más tarde.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, ProcesadorTelegram $procesador): JsonResponse
    {
        $secreto = (string) config('alertas.telegram.webhook_secret');
        if ($secreto === '' || ! hash_equals($secreto, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(404);
        }

        $procesador->procesar($request->json()->all());

        return response()->json(['ok' => true]);
    }
}
