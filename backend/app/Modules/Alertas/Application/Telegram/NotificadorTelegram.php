<?php

namespace App\Modules\Alertas\Application\Telegram;

use App\Modules\Alertas\Application\Contracts\BotTelegram;
use App\Modules\Alertas\Domain\EstadoAlerta;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Infrastructure\Repositories\AlertaRepository;
use App\Modules\Alertas\Infrastructure\Repositories\CuentaTelegramRepository;
use Illuminate\Support\Facades\Cache;

/** Mensajes de alerta para Telegram: solo texto, sin imágenes ni video (HU27, HU37). */
class NotificadorTelegram
{
    public const PREFIJO_ATENDER = 'atender:';

    private const PRIORIDADES = [1 => 'baja', 2 => 'media', 3 => 'alta'];

    public function __construct(
        private readonly BotTelegram $bot,
        private readonly AlertaRepository $alertas,
        private readonly CuentaTelegramRepository $cuentas,
    ) {}

    /**
     * Envía el aviso de la alerta (o de su escalamiento, si viene `$nivel`) a un usuario. No hace
     * nada si la alerta ya no está abierta o si este mismo aviso ya le llegó (reintentos y
     * AlertaCreada repetido tras un reintento de la correlación).
     */
    public function enviar(string $alertaId, int $usuarioId, ?int $nivel = null): void
    {
        $alerta = $this->alertas->buscar($alertaId);
        $usuario = $this->cuentas->buscar($usuarioId);
        if ($alerta?->estado !== EstadoAlerta::Abierta || ! $usuario?->activo || blank($usuario->telegram_chat_id)) {
            return;
        }

        $enviado = "telegram:enviado:{$alertaId}:".($nivel ?? 0).":{$usuarioId}";
        if (Cache::has($enviado)) {
            return;
        }

        $this->bot->llamar('sendMessage', [
            'chat_id' => $usuario->telegram_chat_id,
            'text' => $this->texto($alerta, $nivel),
            'parse_mode' => 'HTML',
            'reply_markup' => ['inline_keyboard' => [[
                ['text' => 'Atender', 'callback_data' => self::PREFIJO_ATENDER.$alerta->id],
            ]]],
        ]);
        Cache::put($enviado, true, now()->addDays(2));
    }

    public function texto(Alerta $alerta, ?int $nivel = null): string
    {
        $hora = $alerta->fecha_hora->copy()->setTimezone(config('app.timezone'))->format('d/m H:i');
        $lugar = e($alerta->zona['nombre']).(isset($alerta->camara['nombre']) ? ' · '.e($alerta->camara['nombre']) : '');
        $prioridad = self::PRIORIDADES[$alerta->prioridad] ?? (string) $alerta->prioridad;

        $lineas = [
            $nivel !== null
                ? "<b>SIN ATENDER · nivel {$nivel}</b>"
                : '<b>ALERTA · prioridad '.$prioridad.'</b>',
            "<b>Zona:</b> {$lugar}",
            e($alerta->motivo),
            "<b>Hora:</b> {$hora}".($alerta->num_eventos > 1 ? " · {$alerta->num_eventos} detecciones" : ''),
        ];

        return implode("\n", $lineas);
    }
}
