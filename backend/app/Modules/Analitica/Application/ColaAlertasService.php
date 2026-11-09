<?php

namespace App\Modules\Analitica\Application;

use App\Modules\Analitica\Infrastructure\Repositories\AlertasLecturaRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class ColaAlertasService
{
    public const LIMITE = 100;

    public function __construct(private readonly AlertasLecturaRepository $alertas) {}

    /** @return ?list<array> null si MongoDB no responde */
    public function pendientes(): ?array
    {
        try {
            return $this->alertas->cola(self::LIMITE);
        } catch (Throwable $e) {
            Log::warning('Cola de alertas: no se pudo leer MongoDB', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
