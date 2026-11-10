<?php

use App\Modules\Monitoreo\Http\Controllers\Web\EventoController;
use App\Modules\Monitoreo\Http\Controllers\Web\ZonaController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel/monitoreo')->middleware('auth')->name('panel.monitoreo.')->group(function () {
    Route::middleware('rol:admin,vigilante')->group(function () {
        Route::get('eventos', [EventoController::class, 'index'])->name('eventos.index');
        Route::get('eventos/feed', [EventoController::class, 'feed'])->name('eventos.feed');
    });

    Route::middleware('rol:admin')->group(function () {
        Route::resource('zonas', ZonaController::class);
    });
});
