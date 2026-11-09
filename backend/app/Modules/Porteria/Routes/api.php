<?php

use App\Modules\Porteria\Http\Controllers\Api\V1\AutorizacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.porteria.')->group(function () {
    Route::middleware(['auth:sanctum', 'rol:admin,residente,vigilante'])->group(function () {
        Route::apiResource('autorizaciones', AutorizacionController::class)
            ->parameters(['autorizaciones' => 'autorizacion']);
    });
});
