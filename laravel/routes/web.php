<?php

use App\Http\Controllers\AppUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AppUpdateController::class, 'dashboard'])->name('app-updates.dashboard');
Route::get('/app-updates', [AppUpdateController::class, 'dashboard']);
Route::get('/uploader', [AppUpdateController::class, 'dashboard']);

Route::post('/upload',             [AppUpdateController::class, 'fileUpload'])->name('file.upload');
Route::post('/request-upload-otp', [AppUpdateController::class, 'fileRequestUploadOtp'])->name('file.request-upload-otp');
Route::post('/request-download-otp', [AppUpdateController::class, 'fileRequestDownloadOtp'])->name('app-updates.request-otp');
Route::post('/verify-download-otp',  [AppUpdateController::class, 'fileVerifyDownloadOtp'])->name('app-updates.verify-otp');
Route::post('/delete-file',          [AppUpdateController::class, 'fileDeleteWithOtp'])->name('file.delete');
