<?php

namespace App\Modules\Identidad;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentidadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'identidad');

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(__DIR__.'/Routes/web.php');
            Route::prefix('api')->middleware('api')->group(__DIR__.'/Routes/api.php');
        }
    }
}
