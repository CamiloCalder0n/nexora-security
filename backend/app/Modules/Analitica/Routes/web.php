<?php

use App\Modules\Analitica\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'rol:admin,vigilante,consejo'])->group(function () {
    Route::get('panel', DashboardController::class)->name('panel.dashboard');

    Route::prefix('panel/analitica')->name('panel.analitica.')->group(function () {
        //
    });
});
