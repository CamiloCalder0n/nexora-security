<?php

namespace App\Modules\Identidad\Http\Middleware;

use App\Modules\Identidad\Application\AuditoriaService;
use App\Modules\Identidad\Domain\Rol;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
            $this->registrarRechazo($request);
            abort(403, 'No tiene permiso para esta acción.');
        }

        return $next($request);
    }

    /** CU10: el intento queda en la auditoría; si no se puede guardar, igual se niega el acceso. */
    private function registrarRechazo(Request $request): void
    {
        try {
            $recurso = $request->route()?->getName() ?? $request->path();
            app(AuditoriaService::class)->registrarRechazo($request->user(), $recurso, $request->ip());
        } catch (Throwable $e) {
            Log::warning('No se pudo auditar un acceso denegado', ['error' => $e->getMessage()]);
        }
    }
}
