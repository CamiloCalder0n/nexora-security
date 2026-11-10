<?php

use App\Modules\Identidad\Http\Controllers\Web\LoginController;
use App\Modules\Identidad\Http\Controllers\Web\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('login', [LoginController::class, 'entrar'])->middleware('throttle:login')->name('login.entrar');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'salir'])->name('logout');
    Route::get('panel/inicio', [LoginController::class, 'inicio'])->name('panel.inicio');
});

Route::prefix('panel/identidad')->name('panel.identidad.')->middleware(['auth', 'rol:admin'])->group(function () {
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::patch('usuarios/{usuario}/activo', [UsuarioController::class, 'cambiarActivo'])->name('usuarios.activo');
});
