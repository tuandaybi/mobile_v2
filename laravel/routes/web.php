<?php

use App\Http\Controllers\AppUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppUpdateController::class, 'dashboard'])->name('app-updates.dashboard');
Route::get('/app-updates', [AppUpdateController::class, 'dashboard']);
Route::get('/uploader', [AppUpdateController::class, 'dashboard']);

Route::post('/request-download-otp', [AppUpdateController::class, 'requestDownloadOtp'])->name('app-updates.request-otp');
Route::post('/verify-download-otp', [AppUpdateController::class, 'verifyDownloadOtp'])->name('app-updates.verify-otp');
Route::post('/delete-file', [AppUpdateController::class, 'deleteWithOtp'])->name('app-updates.delete-with-otp');
