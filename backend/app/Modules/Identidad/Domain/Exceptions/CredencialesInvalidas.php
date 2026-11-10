<?php

namespace App\Modules\Identidad\Domain\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Respuesta esperada (401), no una falla: no va al log. */
class CredencialesInvalidas extends RuntimeException implements ShouldntReport
{
    public function __construct(string $mensaje = 'Credenciales inválidas')
    {
        parent::__construct($mensaje);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage()], 401);
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => $this->getMessage()]);
    }
}
