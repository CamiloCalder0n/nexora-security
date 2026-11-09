<?php

namespace App\Providers;

use App\Support\Mongo\InstalarMongo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstalarMongo::class]);  // esquema de MongoDB de todos los módulos
        }

        // Producción (Render + Upstash gratis): cada deploy reinicia el contenedor, así que el worker y el
        // scheduler no revisan en la caché las señales de pausa o reinicio, y los mutex del scheduler van
        // a la base. Sin esto le mandarían a Redis decenas de miles de comandos al día sin hacer nada.
        if ($this->app->isProduction()) {
            Queue::withoutInterruptionPolling();
            Schedule::withoutInterruptionPolling();
            $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->useCache('database'));
        }
    }
}
