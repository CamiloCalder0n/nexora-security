<?php

namespace App\Modules\Identidad\Application;

use App\Models\User;
use App\Modules\Identidad\Domain\Exceptions\CredencialesInvalidas;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Identidad\Infrastructure\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\Hash;

class AutenticacionService
{
    public function __construct(private readonly UsuarioRepository $usuarios) {}

    /**
     * Devuelve el usuario si la contraseña coincide y la cuenta está activa.
     * Con $soloRol, cualquier otro rol cuenta como credencial inválida.
     */
    public function validarCredenciales(string $email, string $password, ?Rol $soloRol = null): User
    {
        $usuario = $this->usuarios->buscarPorEmail($email);

        if (! $usuario || ! Hash::check($password, $usuario->password)) {
            throw new CredencialesInvalidas;
        }

        if ($soloRol !== null && $usuario->rol !== $soloRol) {
            throw new CredencialesInvalidas;
        }

        if (! $usuario->activo) {
            throw new CredencialesInvalidas;
        }

        return $usuario;
    }

    /** El agente es un cliente de la API: no entra al panel web. */
    public function validarCredencialesPanel(string $email, string $password): User
    {
        $usuario = $this->validarCredenciales($email, $password);

        if ($usuario->rol === Rol::Agente) {
            throw new CredencialesInvalidas;
        }

        return $usuario;
    }

    /** Horas de vida del token de una persona. El del agente no vence: corre sin nadie que vuelva a entrar. */
    public const HORAS_TOKEN_USUARIO = 12;

    /** @return array{token: string, usuario: User} */
    public function emitirToken(string $email, string $password, string $dispositivo, ?Rol $soloRol = null): array
    {
        $usuario = $this->validarCredenciales($email, $password, $soloRol);
        $esAgente = $usuario->rol === Rol::Agente;
        $vence = $esAgente ? null : now()->addHours(self::HORAS_TOKEN_USUARIO);

        return [
            'token' => $usuario->createToken($dispositivo, $esAgente ? ['agente'] : ['*'], $vence)->plainTextToken,
            'usuario' => $usuario,
        ];
    }

    public function revocarTokenActual(User $usuario): void
    {
        $usuario->currentAccessToken()?->delete();
    }
}
