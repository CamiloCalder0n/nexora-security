<?php

namespace App\Modules\Identidad\Infrastructure\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UsuarioRepository
{
    public function buscarPorEmail(string $email): ?User
    {
        return User::query()->where('email', mb_strtolower($email))->first();
    }

    /** Filtros: search (nombre o email), rol y activo ('1' o '0'). */
    public function paginar(array $filtros = [], int $porPagina = 10): LengthAwarePaginator
    {
        return User::query()
            ->when($filtros['search'] ?? null, function (Builder $q, string $texto) {
                $like = '%'.mb_strtolower($texto).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]));
            })
            ->when($filtros['rol'] ?? null, fn (Builder $q, $rol) => $q->where('rol', $rol))
            ->when(isset($filtros['activo']) && $filtros['activo'] !== '', fn (Builder $q) => $q->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN)))
            ->orderBy('name')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function contarPorRol(): array
    {
        return User::query()->selectRaw('rol, COUNT(*) as total')->groupBy('rol')->pluck('total', 'rol')->all();
    }

    public function cambiarActivo(User $usuario, bool $activo): User
    {
        $usuario->update(['activo' => $activo]);

        return $usuario;
    }
}
