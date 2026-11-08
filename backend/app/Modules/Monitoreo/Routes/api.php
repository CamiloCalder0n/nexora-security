<?php

use App\Modules\Monitoreo\Http\Controllers\Api\V1\ZonaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.monitoreo.')->group(function () {
    Route::middleware(['auth:sanctum', 'rol:admin'])->group(function () {
        Route::apiResource('zonas', ZonaController::class);
    });
});
