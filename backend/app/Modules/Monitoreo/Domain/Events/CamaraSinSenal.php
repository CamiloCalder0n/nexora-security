<?php

namespace App\Modules\Monitoreo\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Una cámara dejó de mandar heartbeat y pasó a sin_senal (lo escucha Alertas, HU13). */
class CamaraSinSenal
{
    use Dispatchable;

    public function __construct(
        public readonly int $camaraId,
        public readonly string $camaraNombre,
        public readonly int $zonaId,
        public readonly string $zonaNombre,
        public readonly string $ultimaConexion,
        public readonly int $minutosSinSenal,
    ) {}
}
