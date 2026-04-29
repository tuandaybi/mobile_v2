<?php

use App\Http\Controllers\AppUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppUpdateController::class, 'dashboard'])->name('app-updates.dashboard');
Route::get('/app-updates', [AppUpdateController::class, 'dashboard']);
Route::get('/uploader', [AppUpdateController::class, 'dashboard']);

Route::get('/downloads', [AppUpdateController::class, 'downloadPage'])->name('app-updates.downloads');
Route::post('/downloads/request-otp', [AppUpdateController::class, 'requestDownloadOtp'])->name('app-updates.request-otp');
Route::post('/downloads/verify-otp', [AppUpdateController::class, 'verifyDownloadOtp'])->name('app-updates.verify-otp');
