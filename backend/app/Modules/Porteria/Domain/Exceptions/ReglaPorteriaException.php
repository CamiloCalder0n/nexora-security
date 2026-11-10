<?php

namespace App\Modules\Porteria\Domain\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Regla de negocio que se le responde al usuario (409/422), no una falla: no va al log. */
abstract class ReglaPorteriaException extends RuntimeException implements ShouldntReport
{
    abstract public function status(): int;

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage()], $this->status());
        }

        return back()->withInput()->withErrors(['regla' => $this->getMessage()]);
    }
}
