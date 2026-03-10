<?php

use App\Http\Controllers\AppUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/app-updates', [AppUpdateController::class, 'dashboard'])->name('app-updates.dashboard');
