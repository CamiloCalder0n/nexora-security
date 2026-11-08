<?php

namespace App\Modules\Alertas\Infrastructure\Repositories;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Support\Collection;

/**
 * Único punto de Alertas que lee o escribe las columnas de Telegram de `users`
 * (`telegram_chat_id`, `telegram_codigo`; la migración es de Identidad).
 */
class CuentaTelegramRepository
{
    public function buscar(int $id): ?User
    {
        return User::find($id);
    }

    public function activaPorEmail(string $email): ?User
    {
        return User::where('email', $email)->where('activo', true)->first();
    }

    public function codigoEnUso(string $codigo): bool
    {
        return User::where('telegram_codigo', $codigo)->exists();
    }

    public function guardarCodigo(User $usuario, string $codigo): void
    {
        $usuario->forceFill(['telegram_codigo' => $codigo])->save();
    }

    /** Bloquea la fila: dos chats con el mismo código no pueden vincularse a la vez. */
    public function porCodigoBloqueando(string $codigo): ?User
    {
        return User::where('telegram_codigo', $codigo)->lockForUpdate()->first();
    }

    /** Un chat es de una sola cuenta: si estaba con otra, deja de recibir lo de esa. */
    public function vincular(User $usuario, string $chatId): void
    {
        User::where('telegram_chat_id', $chatId)->whereKeyNot($usuario->id)->update(['telegram_chat_id' => null]);
        $usuario->forceFill(['telegram_chat_id' => $chatId, 'telegram_codigo' => null])->save();
    }

    public function desvincular(int $usuarioId): void
    {
        User::whereKey($usuarioId)->update(['telegram_chat_id' => null]);
    }

    public function activaDelChat(string $chatId): ?User
    {
        return User::where('telegram_chat_id', $chatId)->where('activo', true)->first();
    }

    /** @param list<Rol> $roles */
    public function vinculadasConRol(array $roles): Collection
    {
        return User::whereIn('rol', $roles)->where('activo', true)->whereNotNull('telegram_chat_id')->get();
    }
}
