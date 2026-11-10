<?php

namespace App\Modules\Identidad;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentidadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'identidad');

        // Por cuenta y por IP: rotar IPs no sirve para probar claves contra un mismo correo.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login-cuenta:'.mb_strtolower((string) $request->input('email'))),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(__DIR__.'/Routes/web.php');
            Route::prefix('api')->middleware('api')->group(__DIR__.'/Routes/api.php');
        }
    }
}
