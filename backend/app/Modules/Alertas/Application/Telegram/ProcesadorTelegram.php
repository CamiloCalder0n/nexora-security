<?php

namespace App\Modules\Alertas\Application\Telegram;

use App\Models\User;
use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\TransicionInvalida;
use App\Modules\Alertas\Infrastructure\Repositories\AlertaRepository;
use App\Modules\Alertas\Infrastructure\Repositories\CuentaTelegramRepository;
use App\Modules\Alertas\Infrastructure\Telegram\TelegramBotApi;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Lo que llega del bot, por webhook o por long polling: /start con el código de vinculación,
 * /ayuda y el botón "Atender" (HU24). Telegram puede repetir una actualización; se procesa una vez.
 */
class ProcesadorTelegram
{
    /** Lo único que el bot necesita recibir; se usa en setWebhook y en getUpdates. */
    public const ACTUALIZACIONES = ['message', 'callback_query'];

    private const INTENTOS_CODIGO = 5;

    /** Respuestas por chat no vinculado cada 10 min: el spam no gasta el cupo de envío de la Bot API (HU21). */
    private const RESPUESTAS_SIN_VINCULO = 6;

    private const ROLES_QUE_ATIENDEN = [Rol::Vigilante, Rol::Admin];

    public function __construct(
        private readonly TelegramBotApi $bot,
        private readonly VinculacionTelegram $vinculacion,
        private readonly CuentaTelegramRepository $cuentas,
        private readonly NotificadorTelegram $notificador,
        private readonly AlertaService $servicio,
        private readonly AlertaRepository $alertas,
    ) {}

    /**
     * Una actualización rota se registra y no frena a las siguientes (Telegram reintenta el webhook
     * si no recibe 200). Solo lanza si falla la caché del dedup: así Telegram la vuelve a mandar
     * en vez de procesarla sin saber si ya se hizo.
     */
    public function procesar(array $actualizacion): void
    {
        $id = $actualizacion['update_id'] ?? null;
        if (! is_int($id) || ! Cache::add("telegram:update:{$id}", true, now()->addDay())) {
            return;
        }

        try {
            if (isset($actualizacion['callback_query'])) {
                $this->boton($actualizacion['callback_query']);
            } elseif (isset($actualizacion['message']['text'])) {
                $this->mensaje($actualizacion['message']);
            }
        } catch (Throwable $e) {
            Log::error('Falló una actualización de Telegram', ['update_id' => $id, 'error' => $e->getMessage()]);
        }
    }

    private function mensaje(array $mensaje): void
    {
        // Solo chats privados: un grupo no se vincula ni recibe alertas.
        if (($mensaje['chat']['type'] ?? null) !== 'private' || ! isset($mensaje['chat']['id'])) {
            return;
        }
        $chatId = (string) $mensaje['chat']['id'];
        $usuario = $this->cuentas->activaDelChat($chatId);
        if ($usuario === null) {
            $limite = "telegram:respuestas:{$chatId}";
            if (RateLimiter::tooManyAttempts($limite, self::RESPUESTAS_SIN_VINCULO)) {
                return;
            }
            RateLimiter::hit($limite, 600);
        }

        [$comando, $argumento] = array_pad(preg_split('/\s+/', trim($mensaje['text']), 2), 2, '');
        $comando = strtolower(explode('@', $comando)[0]);
        $respuesta = $comando === '/start' && $argumento !== ''
            ? $this->vincular($chatId, $argumento)
            : $this->ayuda($usuario);

        $this->responder($chatId, $respuesta);
    }

    private function vincular(string $chatId, string $codigo): string
    {
        $limite = "telegram:vincular:{$chatId}";
        if (RateLimiter::tooManyAttempts($limite, self::INTENTOS_CODIGO)) {
            $minutos = (int) ceil(RateLimiter::availableIn($limite) / 60);

            return "Demasiados intentos. Prueba otra vez en {$minutos} min.";
        }

        $usuario = $this->vinculacion->vincular($chatId, $codigo);
        if ($usuario === null) {
            RateLimiter::hit($limite, 600);

            return 'Ese código no sirve: no existe, ya se usó o venció. Pide uno nuevo a la administración.';
        }
        RateLimiter::clear($limite);

        return 'Listo, '.e($usuario->name).'. Esta cuenta quedó vinculada'
            .(in_array($usuario->rol, self::ROLES_QUE_ATIENDEN, true) ? ' y aquí te llegarán las alertas.' : '.');
    }

    private function ayuda(?User $usuario): string
    {
        return $usuario !== null
            ? 'Estás vinculado como <b>'.e($usuario->name).'</b>. Las alertas llegan aquí con el botón "Atender".'
            : 'Hola. Para recibir las alertas de Nexora, pide tu código a la administración y envía: <code>/start CODIGO</code>';
    }

    private function boton(array $consulta): void
    {
        $consultaId = (string) ($consulta['id'] ?? '');
        $datos = (string) ($consulta['data'] ?? '');
        $usuario = isset($consulta['from']['id']) ? $this->cuentas->activaDelChat((string) $consulta['from']['id']) : null;

        if ($usuario === null || ! in_array($usuario->rol, self::ROLES_QUE_ATIENDEN, true)) {
            $this->avisar($consultaId, 'Tu cuenta no puede atender alertas.');

            return;
        }
        if (! preg_match('/\A'.preg_quote(NotificadorTelegram::PREFIJO_ATENDER, '/').'([0-9a-f]{24})\z/', $datos, $m) || ($alerta = $this->alertas->buscar($m[1])) === null) {
            $this->avisar($consultaId, 'Esa alerta ya no existe.');

            return;
        }

        try {
            $alerta = $this->servicio->atender($alerta, $usuario);
        } catch (TransicionInvalida) {
            // atender() ya recargó la instancia dentro del lock: trae el estado actual.
            $quien = $alerta->atendida_por['nombre'] ?? null;
            $this->avisar($consultaId, "Esta alerta ya está {$alerta->estado->value}".($quien ? " (la atendió {$quien})" : '').'.');

            return;
        } catch (LockTimeoutException) {
            $this->avisar($consultaId, 'La alerta está ocupada, intenta de nuevo.');

            return;
        }

        $this->avisar($consultaId, 'Alerta atendida. Ciérrala en el panel con su clasificación.');
        $this->marcarAtendida($consulta, $alerta, $usuario);
    }

    /** Quita el botón del mensaje y deja escrito quién la atendió. */
    private function marcarAtendida(array $consulta, Alerta $alerta, User $usuario): void
    {
        if (! isset($consulta['message']['chat']['id'], $consulta['message']['message_id'])) {
            return;
        }
        $hora = $alerta->fecha_atencion->copy()->setTimezone(config('app.timezone'))->format('H:i');

        try {
            $this->bot->llamar('editMessageText', [
                'chat_id' => $consulta['message']['chat']['id'],
                'message_id' => $consulta['message']['message_id'],
                'text' => $this->notificador->texto($alerta)."\n\n<b>Atendida por ".e($usuario->name)." a las {$hora}.</b>",
                'parse_mode' => 'HTML',
            ]);
        } catch (TelegramFallo) {
            // La alerta ya quedó atendida; el mensaje viejo con botón no cambia nada si lo vuelven a tocar.
        }
    }

    private function avisar(string $consultaId, string $texto): void
    {
        if ($consultaId === '') {
            return;
        }
        try {
            $this->bot->llamar('answerCallbackQuery', ['callback_query_id' => $consultaId, 'text' => $texto]);
        } catch (TelegramFallo) {
            // Si no llega, Telegram solo deja de mostrar el reloj en el botón.
        }
    }

    private function responder(string $chatId, string $texto): void
    {
        $this->bot->llamar('sendMessage', ['chat_id' => $chatId, 'text' => $texto, 'parse_mode' => 'HTML']);
    }
}
