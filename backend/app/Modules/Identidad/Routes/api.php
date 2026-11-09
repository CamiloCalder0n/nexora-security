<?php

use App\Modules\Identidad\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.identidad.')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('agente/login', [AuthController::class, 'loginAgente'])->name('agente.login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('yo', [AuthController::class, 'yo'])->name('yo');
    });
});
