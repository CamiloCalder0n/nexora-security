<?php

use App\Modules\Porteria\Http\Controllers\Web\AutorizacionController;
use App\Modules\Porteria\Http\Controllers\Web\ResidenteController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel/porteria')->middleware('auth')->name('panel.porteria.')->group(function () {
    Route::middleware('rol:admin')->group(function () {
        Route::resource('residentes', ResidenteController::class);
        Route::resource('autorizaciones', AutorizacionController::class)
            ->parameters(['autorizaciones' => 'autorizacion']);
    });
});
