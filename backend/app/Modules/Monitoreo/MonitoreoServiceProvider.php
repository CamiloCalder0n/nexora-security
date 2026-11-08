<?php

namespace App\Modules\Monitoreo;

use App\Modules\Monitoreo\Infrastructure\Console\CrearIndicesMongo;
use App\Modules\Monitoreo\Infrastructure\Console\MarcarCamarasSinSenal;
use App\Modules\Monitoreo\Infrastructure\Console\ReencolarPendientes;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MonitoreoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'monitoreo');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'monitoreo');

        // Un agente que se vuelve loco no tumba el backend: 60 eventos por minuto por token → 429.
        RateLimiter::for('agente', fn (Request $request) => Limit::perMinute(config('monitoreo.eventos_por_minuto'))
            ->by('agente:'.($request->user()?->currentAccessToken()?->id ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([CrearIndicesMongo::class, MarcarCamarasSinSenal::class, ReencolarPendientes::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('nexora:camaras-sin-senal')->everyMinute()->withoutOverlapping();
            $schedule->command('nexora:reencolar-pendientes')->everyMinute()->withoutOverlapping();
        });

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(__DIR__.'/Routes/web.php');
            Route::prefix('api')->middleware('api')->group(__DIR__.'/Routes/api.php');
        }
    }
}
