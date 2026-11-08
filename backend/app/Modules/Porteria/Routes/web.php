<?php

use Illuminate\Support\Facades\Route;

Route::prefix('panel/porteria')->middleware('auth')->name('panel.porteria.')->group(function () {
    //
});
