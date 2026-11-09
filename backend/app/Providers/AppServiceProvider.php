<?php

namespace App\Providers;

use App\Support\Mongo\InstalarMongo;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if ($this->app->runningInConsole()) {
            $this->commands([InstalarMongo::class]);  // esquema de MongoDB de todos los módulos
        }
    }
}
