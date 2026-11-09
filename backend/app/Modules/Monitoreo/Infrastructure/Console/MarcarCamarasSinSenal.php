<?php

namespace App\Modules\Monitoreo\Infrastructure\Console;

use App\Modules\Monitoreo\Application\CamaraService;
use Illuminate\Console\Command;

class MarcarCamarasSinSenal extends Command
{
    protected $signature = 'nexora:camaras-sin-senal';

    protected $description = 'Marca sin_senal las cámaras que llevan más de 5 min sin heartbeat';

    public function handle(CamaraService $camaras): int
    {
        $this->info("Cámaras sin señal: {$camaras->marcarSinSenal()}");

        return self::SUCCESS;
    }
}
