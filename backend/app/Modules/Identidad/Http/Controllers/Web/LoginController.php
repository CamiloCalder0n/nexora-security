<?php

namespace App\Modules\Identidad\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identidad\Application\AutenticacionService;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Identidad\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AutenticacionService $autenticacion) {}

    public function mostrar(): View
    {
        return view('identidad::auth.login');
    }

    public function entrar(LoginRequest $request): RedirectResponse
    {
        $usuario = $this->autenticacion->validarCredencialesPanel(
            $request->validated('email'),
            $request->validated('password'),
        );

        Auth::login($usuario, $request->boolean('recordarme'));
        $request->session()->regenerate();

        return redirect()->intended(route('panel.inicio'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('exito', 'Sesión cerrada.');
    }

    public function inicio(Request $request): RedirectResponse
    {
        return redirect()->route(self::rutaInicio($request->user()));
    }

    public static function rutaInicio(User $usuario): string
    {
        $preferida = $usuario->rol === Rol::Residente ? 'panel.porteria.mis-autorizaciones.index' : 'panel.dashboard';

        return Route::has($preferida) ? $preferida : 'panel.dashboard';
    }
}
