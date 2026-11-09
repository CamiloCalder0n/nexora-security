<?php

namespace App\Modules\Porteria\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

abstract class ReglaPorteriaException extends RuntimeException
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
