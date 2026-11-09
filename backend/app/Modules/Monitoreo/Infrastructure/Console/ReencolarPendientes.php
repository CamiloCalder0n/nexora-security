<?php

namespace App\Modules\Monitoreo\Infrastructure\Console;

use App\Modules\Monitoreo\Application\IngestaEventosService;
use Illuminate\Console\Command;

class ReencolarPendientes extends Command
{
    protected $signature = 'nexora:reencolar-pendientes';

    protected $description = 'Vuelve a encolar los eventos que llevan demasiado en pendiente (jobs perdidos)';

    public function handle(IngestaEventosService $ingesta): int
    {
        $this->info("Eventos reencolados: {$ingesta->reencolarPendientes()}");

        return self::SUCCESS;
    }
}
