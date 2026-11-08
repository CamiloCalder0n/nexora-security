<?php

namespace App\Modules\Identidad\Http\Middleware;

use App\Modules\Identidad\Domain\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            abort(401, 'No autenticado.');
        }

        if (! $usuario->activo) {
            abort(403, 'La cuenta está inactiva.');
        }

        $permitidos = array_map(fn (string $rol) => Rol::from($rol), $roles);

        if (! in_array($usuario->rol, $permitidos, true)) {
            abort(403, 'No tiene permiso para esta acción.');
        }

        return $next($request);
    }
}
