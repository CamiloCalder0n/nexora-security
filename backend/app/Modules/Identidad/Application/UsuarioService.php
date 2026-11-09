<?php

namespace App\Modules\Identidad\Application;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Identidad\Infrastructure\Repositories\UsuarioRepository;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UsuarioService
{
    public function __construct(private readonly UsuarioRepository $usuarios) {}

    public function listar(array $filtros = [], int $porPagina = 10): LengthAwarePaginator
    {
        return $this->usuarios->paginar($filtros, $porPagina);
    }

    /** @return array<string, int> conteo por cada rol, incluidos los que no tienen usuarios */
    public function totales(): array
    {
        $conteo = $this->usuarios->contarPorRol();
        $totales = [];

        foreach (Rol::cases() as $rol) {
            $totales[$rol->value] = (int) ($conteo[$rol->value] ?? 0);
        }

        return $totales;
    }

    /**
     * Un admin no puede desactivarse a sí mismo. Al desactivar se revocan
     * los tokens de API para que la cuenta deje de entrar también por ahí.
     */
    public function cambiarActivo(User $usuario, bool $activo, User $actor): User
    {
        if (! $activo && $usuario->is($actor)) {
            throw new DomainException('No puede desactivar su propia cuenta.');
        }

        return DB::transaction(function () use ($usuario, $activo) {
            $this->usuarios->cambiarActivo($usuario, $activo);

            if (! $activo) {
                $usuario->tokens()->delete();
            }

            return $usuario;
        });
    }
}
