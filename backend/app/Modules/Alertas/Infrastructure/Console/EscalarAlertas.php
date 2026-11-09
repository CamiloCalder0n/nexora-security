<?php

namespace App\Modules\Alertas\Infrastructure\Console;

use App\Modules\Alertas\Application\AlertaService;
use Illuminate\Console\Command;

class EscalarAlertas extends Command
{
    protected $signature = 'nexora:escalar-alertas';

    protected $description = 'Sube de nivel las alertas de prioridad alta que nadie ha atendido';

    public function handle(AlertaService $alertas): int
    {
        $this->info("Alertas escaladas: {$alertas->escalarVencidas()}");

        return self::SUCCESS;
    }
}
