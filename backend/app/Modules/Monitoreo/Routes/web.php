<?php

use App\Modules\Monitoreo\Http\Controllers\Web\ZonaController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel/monitoreo')->middleware('auth')->name('panel.monitoreo.')->group(function () {
    Route::middleware('rol:admin')->group(function () {
        Route::resource('zonas', ZonaController::class);
    });
});
