<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\Models\Zona;
use Illuminate\Support\Facades\Cache;

/** Si una zona está dentro de una franja horaria. Lo de "ahora" se cachea en Redis 60 s. */
class VigilanciaService
{
    public const CACHE_SEG = 60;

    public function vigiladaAhora(Zona $zona): bool
    {
        return Cache::remember(
            "monitoreo:zona:{$zona->id}:vigilada",
            self::CACHE_SEG,
            fn () => $zona->estaVigilada(now()),
        );
    }
}
