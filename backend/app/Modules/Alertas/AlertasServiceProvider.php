<?php

namespace App\Modules\Alertas;

use App\Modules\Alertas\Application\MotorReglas;
use App\Modules\Alertas\Infrastructure\Console\VerificarBitacora;
use App\Modules\Alertas\Infrastructure\Listeners\CorrelacionJob;
use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AlertasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'alertas');
        $this->app->bind(MotorReglas::class, fn ($app) => new MotorReglas(
            array_map(fn (string $regla) => $app->make($regla), config('alertas.reglas')),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'alertas');

        Event::listen(EventoRegistrado::class, CorrelacionJob::class);

        if ($this->app->runningInConsole()) {
            $this->commands([VerificarBitacora::class]);
        }

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(__DIR__.'/Routes/web.php');
            Route::prefix('api')->middleware('api')->group(__DIR__.'/Routes/api.php');
        }
    }
}
