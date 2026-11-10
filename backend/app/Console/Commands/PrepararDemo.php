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
 * activas con señal de ahora solo las cámaras de --camaras (por defecto 1 y 2, las del guion); las
 * demás quedan inactivas para que, sin agente, no disparen alertas de "sin señal". Termina
 * verificando la cadena de la bitácora.
 *
 * Contra la nube, con las URLs de los servicios en el entorno:
 * `DB_URL=... MONGODB_URI=... REDIS_URL=... APP_ENV=production php artisan nexora:demo-preparar`
 * (REDIS_URL aplica a la conexión por defecto, donde van los locks, y a la de caché).
 */
class PrepararDemo extends Command
{
    protected $signature = 'nexora:demo-preparar
        {--camaras=1,2 : Ids de las cámaras que quedan activas (separados por coma); las demás quedan inactivas}
        {--force : No pedir confirmación en producción}';

    protected $description = 'Cierra las alertas activas y deja activas solo las cámaras de la demo';

    public function handle(AlertaService $servicio, AlertaRepository $alertas, CamaraRepository $camaras): int
    {
        $ids = $this->idsDeCamaras();
        if ($ids === null) {
            $this->error('--camaras debe ser una lista de ids separados por coma, por ejemplo --camaras=1,2.');

            return self::FAILURE;
        }

        $this->line('Conectando a:');
        $this->line('  PostgreSQL: '.$this->host(config('database.connections.'.config('database.default').'.url'), config('database.connections.'.config('database.default').'.host')));
        $this->line('  MongoDB:    '.$this->host(config('database.connections.mongodb.dsn')));
        $this->line('  Redis:      '.$this->host(config('database.redis.default.url'), config('database.redis.default.host')));

        if (app()->isProduction() && ! $this->option('force')
            && ! $this->confirm('Estás en producción: se cerrarán todas las alertas activas. ¿Seguir?', false)) {
            $this->warn('Cancelado, no se cambió nada.');

            return self::FAILURE;
        }

        if ($faltan = $camaras->idsInexistentes($ids)) {
            $this->error('No existen las cámaras: '.implode(', ', $faltan).'. No se cambió nada.');

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

        [$activas, $inactivas] = $camaras->activarSolo($ids);

        $this->info("{$cerradas} alertas cerradas como sin_novedad.");
        $this->info("{$activas->count()} cámaras activas: ".$activas->pluck('nombre')->implode(', ').'.');
        $this->info("{$inactivas} cámaras inactivas (sin agente no disparan \"sin señal\").");

        return $this->call('nexora:verificar-bitacora');
    }

    /** @return list<int>|null null si la opción no es una lista de enteros positivos */
    private function idsDeCamaras(): ?array
    {
        $partes = array_filter(array_map('trim', explode(',', (string) $this->option('camaras'))), fn ($p) => $p !== '');
        if ($partes === [] || array_filter($partes, fn ($p) => ! ctype_digit($p) || (int) $p === 0)) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $partes)));
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
