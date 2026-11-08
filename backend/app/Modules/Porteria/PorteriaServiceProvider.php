<?php

namespace App\Modules\Porteria;

use App\Modules\Porteria\Application\AutorizacionService;
use App\Modules\Porteria\Application\Contracts\ConsultaRegistrosPorteria;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Http\Policies\AutorizacionPolicy;
use App\Modules\Porteria\Infrastructure\Repositories\RegistroPorteriaRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PorteriaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConsultaRegistrosPorteria::class, RegistroPorteriaRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'porteria');

        Gate::policy(Autorizacion::class, AutorizacionPolicy::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn (AutorizacionService $servicio) => $servicio->expirarVencidas())
                ->name('porteria:expirar-autorizaciones')
                ->everyTenMinutes()
                ->withoutOverlapping();
        });

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(__DIR__.'/Routes/web.php');
            Route::prefix('api')->middleware('api')->group(__DIR__.'/Routes/api.php');
        }
    }
}
