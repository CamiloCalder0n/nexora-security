<?php

namespace App\Modules\Identidad\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Identidad\Application\AutenticacionService;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Identidad\Http\Requests\LoginRequest;
use App\Modules\Identidad\Http\Resources\SesionUsuarioResource;
use App\Modules\Identidad\Http\Resources\UsuarioResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AutenticacionService $autenticacion) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->respuestaToken($request);
    }

    public function loginAgente(LoginRequest $request): JsonResponse
    {
        return $this->respuestaToken($request, Rol::Agente);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->autenticacion->revocarTokenActual($request->user());

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function yo(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user());
    }

    private function respuestaToken(LoginRequest $request, ?Rol $soloRol = null): JsonResponse
    {
        $resultado = $this->autenticacion->emitirToken(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name', 'api'),
            $soloRol,
        );

        return response()->json([
            'token' => $resultado['token'],
            'usuario' => new SesionUsuarioResource($resultado['usuario']),
        ]);
    }
}
