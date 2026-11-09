<?php

namespace App\Modules\Identidad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si la administración desactiva una cuenta con la sesión del panel abierta, la sesión se cierra en la
 * siguiente petición, también en las rutas que solo exigen "auth" y en el API con la sesión del panel.
 * Los tokens de API ya se revocan al desactivarla (UsuarioService::cambiarActivo).
 */
class CerrarSesionInactiva
{
    public const MENSAJE = 'Tu cuenta fue desactivada. Comunícate con la administración.';

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('web')->user();

        if (! $usuario || $usuario->activo) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => self::MENSAJE], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->with('error', self::MENSAJE);
    }
}
