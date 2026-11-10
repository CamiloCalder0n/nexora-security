<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Domain\TransicionInvalida;
use App\Modules\Alertas\Infrastructure\Repositories\AlertaRepository;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Infrastructure\Repositories\CamaraRepository;
use Illuminate\Console\Command;

/**
 * Deja el sistema listo para una demo en vivo sin borrar nada: cierra como sin_novedad las alertas
 * abiertas o atendidas (por el servicio, así cada cierre queda en la bitácora con su hash) y pone
 * todas las cámaras activas con señal de ahora. Termina verificando la cadena de la bitácora.
 *
 * Contra la nube, con las URLs de los servicios en el entorno:
 * `DB_URL=... MONGODB_URI=... REDIS_URL=... APP_ENV=production php artisan nexora:demo-preparar`
 * (REDIS_URL aplica a la conexión por defecto, donde van los locks, y a la de caché).
 */
class PrepararDemo extends Command
{
    protected $signature = 'nexora:demo-preparar {--force : No pedir confirmación en producción}';

    protected $description = 'Cierra las alertas activas y deja las cámaras activas para la demo en vivo';

    public function handle(AlertaService $servicio, AlertaRepository $alertas, CamaraRepository $camaras): int
    {
        $this->line('Conectando a:');
        $this->line('  PostgreSQL: '.$this->host(config('database.connections.'.config('database.default').'.url'), config('database.connections.'.config('database.default').'.host')));
        $this->line('  MongoDB:    '.$this->host(config('database.connections.mongodb.dsn')));
        $this->line('  Redis:      '.$this->host(config('database.redis.default.url'), config('database.redis.default.host')));

        if (app()->isProduction() && ! $this->option('force')
            && ! $this->confirm('Estás en producción: se cerrarán todas las alertas activas. ¿Seguir?', false)) {
            $this->warn('Cancelado, no se cambió nada.');

            return self::FAILURE;
        }

        $admin = User::where('rol', Rol::Admin)->where('activo', true)->orderBy('id')->first();
        if ($admin === null) {
            $this->error('No hay un usuario admin activo que firme los cierres.');

            return self::FAILURE;
        }

        $cerradas = 0;
        foreach ($alertas->activas(PHP_INT_MAX) as $alerta) {
            try {
                $servicio->cerrar($alerta, $admin, 'sin_novedad', 'Cierre automático antes de la demo');
                $cerradas++;
            } catch (TransicionInvalida) {
                continue;
            }
        }

        $activas = $camaras->activarTodas();

        $this->info("{$cerradas} alertas cerradas como sin_novedad.");
        $this->info("{$activas} cámaras activas.");

        return $this->call('nexora:verificar-bitacora');
    }

    private function host(?string $url, ?string $respaldo = null): string
    {
        if (blank($url)) {
            return $respaldo ?? 'sin configurar';
        }

        $sinCredenciales = preg_replace('#^([a-z0-9+.-]+://)?[^/@]*@#i', '$1', $url);

        return explode('/', preg_replace('#^[a-z0-9+.-]+://#i', '', $sinCredenciales), 2)[0] ?: 'sin configurar';
    }
}
