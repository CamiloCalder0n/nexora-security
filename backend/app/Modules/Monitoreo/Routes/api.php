<?php

use App\Modules\Monitoreo\Http\Controllers\Api\V1\AgenteController;
use App\Modules\Monitoreo\Http\Controllers\Api\V1\ZonaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.monitoreo.')->group(function () {
    Route::middleware(['auth:sanctum', 'rol:admin'])->group(function () {
        Route::apiResource('zonas', ZonaController::class);
    });
});

Route::prefix('v1/agente')
    ->name('api.v1.agente.')
    ->middleware(['auth:sanctum', 'rol:agente'])
    ->group(function () {
        Route::get('camaras', [AgenteController::class, 'camaras'])->name('camaras');
        Route::post('eventos', [AgenteController::class, 'eventos'])->middleware('throttle:agente')->name('eventos');
        Route::post('camaras/{camara}/heartbeat', [AgenteController::class, 'heartbeat'])->name('heartbeat');
    });
