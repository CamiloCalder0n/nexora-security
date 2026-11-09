<?php

namespace App\Modules\Alertas\Infrastructure\Console;

use App\Modules\Alertas\Application\BitacoraService;
use Illuminate\Console\Command;

class VerificarBitacora extends Command
{
    protected $signature = 'nexora:verificar-bitacora';

    protected $description = 'Recorre la cadena de hashes de la bitácora y dice si alguien la alteró';

    public function handle(BitacoraService $bitacora): int
    {
        ['entradas' => $entradas, 'rota' => $rota] = $bitacora->verificar();

        if ($rota === null) {
            $this->info("OK: {$entradas} entradas, la cadena está completa.");

            return self::SUCCESS;
        }

        $this->error("La cadena se rompe en la entrada #{$rota->secuencia} ({$rota->id}, {$rota->fecha_hora}).");

        return self::FAILURE;
    }
}
