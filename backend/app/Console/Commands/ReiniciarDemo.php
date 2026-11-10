<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deja la base lista para la sustentación: PostgreSQL desde cero con los seeders, MongoDB sin
 * eventos, alertas ni bitácora previos (con su validador e índices) y la caché de Redis vacía
 * (zonas vigiladas y límites de intentos). Nunca en producción.
 */
class ReiniciarDemo extends Command
{
    protected $signature = 'nexora:demo-reset {--force : No pedir confirmación}';

    protected $description = 'Borra todo y deja la base con los datos de demo (no corre en producción)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('nexora:demo-reset no corre con APP_ENV=production.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Se borran PostgreSQL, MongoDB y la caché. ¿Seguir?', true)) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        DB::connection('mongodb')->getDatabase()->drop();
        $this->info('MongoDB vaciado.');

        if ($this->call('nexora:mongo-instalar') !== self::SUCCESS) {
            return self::FAILURE;
        }

        Cache::flush();
        $this->info('Caché vaciada. Demo lista: usuarios con contraseña "password" (ver README).');

        return self::SUCCESS;
    }
}
