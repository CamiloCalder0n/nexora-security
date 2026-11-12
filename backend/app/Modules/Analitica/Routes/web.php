<?php

use App\Modules\Analitica\Http\Controllers\Web\ColaAlertasController;
use App\Modules\Analitica\Http\Controllers\Web\DashboardController;
use App\Modules\Analitica\Http\Controllers\Web\HistorialController;
use App\Modules\Analitica\Http\Controllers\Web\InformeMensualController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'rol:admin,vigilante,consejo'])->group(function () {
    Route::get('panel', DashboardController::class)->name('panel.dashboard');

    Route::prefix('panel/analitica')->name('panel.analitica.')->group(function () {
        Route::get('cola-alertas', ColaAlertasController::class)
            ->middleware('rol:vigilante,admin')
            ->name('cola-alertas');

        // RF005 / HU14
        Route::get('historial', [HistorialController::class, 'index'])->name('historial');
        Route::get('historial/csv', [HistorialController::class, 'csv'])->middleware('rol:admin,consejo')->name('historial.csv');

        // RF007: solo la administración ve evidencias, siempre con motivo.
        Route::middleware('rol:admin')->where(['uuid' => '[0-9a-f\-]{36}'])->group(function () {
            Route::get('historial/{uuid}/evidencia', [HistorialController::class, 'evidencia'])->name('evidencia');
            Route::post('historial/{uuid}/evidencia', [HistorialController::class, 'verEvidencia'])
                ->middleware('throttle:20,1')
                ->name('evidencia.ver');
        });

        // RF008
        Route::middleware('rol:admin,consejo')->group(function () {
            Route::get('informe-mensual', [InformeMensualController::class, 'show'])->name('informe');
            Route::get('informe-mensual/csv', [InformeMensualController::class, 'csv'])->name('informe.csv');
        });
    });
});
