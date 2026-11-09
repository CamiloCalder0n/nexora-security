<?php

use App\Modules\Porteria\Http\Controllers\Web\AutorizacionController;
use App\Modules\Porteria\Http\Controllers\Web\AutorizacionesVigentesController;
use App\Modules\Porteria\Http\Controllers\Web\IngresoController;
use App\Modules\Porteria\Http\Controllers\Web\MisAutorizacionesController;
use App\Modules\Porteria\Http\Controllers\Web\MisVisitasController;
use App\Modules\Porteria\Http\Controllers\Web\ResidenteController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel/porteria')->middleware('auth')->name('panel.porteria.')->group(function () {
    Route::middleware('rol:admin')->group(function () {
        Route::resource('residentes', ResidenteController::class);
        Route::resource('autorizaciones', AutorizacionController::class)
            ->parameters(['autorizaciones' => 'autorizacion']);
    });

    Route::middleware('rol:vigilante,admin')->group(function () {
        Route::get('autorizaciones-vigentes', AutorizacionesVigentesController::class)->name('autorizaciones.vigentes');
        Route::get('ingresos/crear', [IngresoController::class, 'create'])->name('ingresos.create');
        Route::post('ingresos', [IngresoController::class, 'store'])->name('ingresos.store');
        Route::get('ingresos/autorizaciones', [IngresoController::class, 'autorizacionesDeUnidad'])->name('ingresos.autorizaciones');
        Route::patch('ingresos/{registro}/salida', [IngresoController::class, 'salida'])->name('ingresos.salida');
    });

    Route::middleware('rol:vigilante,admin,consejo')->group(function () {
        Route::get('ingresos', [IngresoController::class, 'index'])->name('ingresos.index');
    });

    Route::middleware('rol:residente')->group(function () {
        Route::get('mis-autorizaciones', [MisAutorizacionesController::class, 'index'])->name('mis-autorizaciones.index');
        Route::get('mis-autorizaciones/crear', [MisAutorizacionesController::class, 'create'])->name('mis-autorizaciones.create');
        Route::post('mis-autorizaciones', [MisAutorizacionesController::class, 'store'])->name('mis-autorizaciones.store');
        Route::delete('mis-autorizaciones/{autorizacion}', [MisAutorizacionesController::class, 'destroy'])->name('mis-autorizaciones.destroy');
        Route::get('mis-visitas', MisVisitasController::class)->name('mis-visitas.index');
    });
});
