<?php

namespace App\Modules\Alertas\Application\Telegram;

use App\Models\User;
use App\Modules\Alertas\Infrastructure\Repositories\CuentaTelegramRepository;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Une la cuenta del panel con un chat de Telegram. El usuario recibe un código de un solo uso
 * que vence en `alertas.telegram.minutos_codigo` y se lo manda al bot con /start.
 */
class VinculacionTelegram
{
    /** Sin 0/O ni 1/I/L: se dicta en portería sin confusiones. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LARGO_CODIGO = 6;

    public function __construct(private readonly CuentaTelegramRepository $cuentas) {}

    public function generarCodigo(User $usuario): string
    {
        do {
            $codigo = '';
            for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
                $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
        } while ($this->cuentas->codigoEnUso($codigo));

        $this->cuentas->guardarCodigo($usuario, $codigo);
        // Como texto: Redis devuelve los números como string y la comparación de abajo es estricta.
        Cache::put($this->claveVence($codigo), (string) $usuario->id, now()->addMinutes(config('alertas.telegram.minutos_codigo')));

        return $codigo;
    }

    /** Devuelve el usuario vinculado, o null si el código no existe, venció o la cuenta está inactiva. */
    public function vincular(string $chatId, string $codigo): ?User
    {
        $codigo = strtoupper(trim($codigo));
        if (! preg_match('/\A['.self::ALFABETO.']{'.self::LARGO_CODIGO.'}\z/', $codigo)) {
            return null;
        }

        return DB::transaction(function () use ($chatId, $codigo) {
            $usuario = $this->cuentas->porCodigoBloqueando($codigo);
            // El vencimiento vive en caché: si ya no está (o es de otro usuario) el código no sirve.
            if ($usuario === null || Cache::pull($this->claveVence($codigo)) !== (string) $usuario->id || ! $usuario->activo) {
                return null;
            }
            $this->cuentas->vincular($usuario, $chatId);

            return $usuario;
        });
    }

    /** El usuario bloqueó el bot: se deja de intentar hasta que se vuelva a vincular. */
    public function desvincularUsuario(int $usuarioId): void
    {
        $this->cuentas->desvincular($usuarioId);
    }

    public function usuarioDelChat(string $chatId): ?User
    {
        return $this->cuentas->activaDelChat($chatId);
    }

    /** @param list<Rol> $roles */
    public function destinatarios(array $roles): Collection
    {
        return $this->cuentas->vinculadasConRol($roles);
    }

    private function claveVence(string $codigo): string
    {
        return "telegram:codigo:{$codigo}";
    }
}
