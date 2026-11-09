<?php

use App\Modules\Monitoreo\Http\Controllers\Api\V1\AgenteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/agente')
    ->name('api.v1.agente.')
    ->middleware(['auth:sanctum', 'rol:agente'])
    ->group(function () {
        Route::get('camaras', [AgenteController::class, 'camaras'])->name('camaras');
        Route::post('eventos', [AgenteController::class, 'eventos'])->middleware('throttle:agente')->name('eventos');
        Route::post('camaras/{camara}/heartbeat', [AgenteController::class, 'heartbeat'])->name('heartbeat');
    });
