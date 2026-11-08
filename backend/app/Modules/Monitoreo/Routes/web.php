<?php

use Illuminate\Support\Facades\Route;

Route::prefix('panel/monitoreo')->middleware('auth')->name('panel.monitoreo.')->group(function () {
    //
});
