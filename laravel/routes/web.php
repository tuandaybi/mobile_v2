<?php

use App\Http\Controllers\AppUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppUpdateController::class, 'dashboard'])->name('app-updates.dashboard');
Route::get('/app-updates', [AppUpdateController::class, 'dashboard']);
Route::get('/uploader', [AppUpdateController::class, 'dashboard']);
