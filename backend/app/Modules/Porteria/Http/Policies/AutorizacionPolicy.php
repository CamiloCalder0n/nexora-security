<?php

namespace App\Modules\Porteria\Http\Policies;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Infrastructure\Repositories\ResidenteRepository;

/** HU10: el residente solo ve y modifica autorizaciones de sus unidades. */
class AutorizacionPolicy
{
    public function __construct(private readonly ResidenteRepository $residentes) {}

    public function before(User $usuario): ?bool
    {
        return $usuario->rol === Rol::Admin ? true : null;
    }

    public function viewAny(User $usuario): bool
    {
        return in_array($usuario->rol, [Rol::Residente, Rol::Vigilante], true);
    }

    public function view(User $usuario, Autorizacion $autorizacion): bool
    {
        if ($usuario->rol === Rol::Vigilante) {
            return $autorizacion->estado === EstadoAutorizacion::Vigente;
        }

        return $this->esDeSusUnidades($usuario, $autorizacion);
    }

    public function create(User $usuario): bool
    {
        return $usuario->rol === Rol::Residente && $usuario->residente !== null;
    }

    public function update(User $usuario, Autorizacion $autorizacion): bool
    {
        return $this->esDeSusUnidades($usuario, $autorizacion);
    }

    public function delete(User $usuario, Autorizacion $autorizacion): bool
    {
        return $this->esDeSusUnidades($usuario, $autorizacion);
    }

    private function esDeSusUnidades(User $usuario, Autorizacion $autorizacion): bool
    {
        return $usuario->rol === Rol::Residente
            && $usuario->residente !== null
            && $this->residentes->perteneceUnidad($usuario->residente, $autorizacion->unidad_id);
    }
}
