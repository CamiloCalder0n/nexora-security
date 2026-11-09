<?php

namespace App\Modules\Alertas\Infrastructure\Listeners;

use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Monitoreo\Domain\Events\CamaraSinSenal;
use Illuminate\Contracts\Queue\ShouldQueue;

class AlertarCamaraSinSenal implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly AlertaService $alertas) {}

    public function handle(CamaraSinSenal $caida): void
    {
        $this->alertas->alertarCamaraSinSenal($caida);
    }
}
