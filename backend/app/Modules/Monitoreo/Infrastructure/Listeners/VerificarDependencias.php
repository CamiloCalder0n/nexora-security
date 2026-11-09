<?php

namespace App\Modules\Monitoreo\Infrastructure\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

/**
 * `/up` responde 200 solo si el backend alcanza sus tres bases (HU39). Si una falla, `/up` da 500 y el
 * log dice cuál fue ("MongoDB no responde."), con el error original adjunto para diagnosticar.
 */
class VerificarDependencias
{
    public function handle(DiagnosingHealth $evento): void
    {
        $this->verificar('PostgreSQL', fn () => DB::connection()->getPdo());
        $this->verificar('MongoDB', fn () => DB::connection('mongodb')->getDatabase()->command(['ping' => 1]));
        // Un solo PING: caché, locks y colas usan el mismo servidor de Redis.
        $this->verificar('Redis', fn () => Redis::connection()->ping());
    }

    private function verificar(string $servicio, callable $prueba): void
    {
        try {
            $prueba();
        } catch (Throwable $e) {
            throw new RuntimeException("{$servicio} no responde.", previous: $e);
        }
    }
}
