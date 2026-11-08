<?php

namespace App\Modules\Monitoreo\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ZonaEnUso extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La zona tiene cámaras o registros de portería; desactívela en lugar de eliminarla.');
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        return back()->withErrors(['zona' => $this->getMessage()]);
    }
}
