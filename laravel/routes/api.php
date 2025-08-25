<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    MobileInController, MobileOutController,
    SupplierController, PurchaseInvoiceController,
    ColorController, DeviceStorageController, 
    CustomerController, ServiceController, AuthController,
    DeviceController, UserController
};

Route::post('/login', [AuthController::class, 'login']);
Route::post('/redeem', [AuthController::class, 'redeem'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'store']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'index']);
    Route::patch('/user', [AuthController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Mobile In
    Route::get('/mobile-in', [MobileInController::class,'index']);
    Route::get('/mobile-in/{id}', [MobileInController::class,'show']);
    Route::post('/mobile-in', [MobileInController::class,'store']);
    Route::match(['put','patch'],'/mobile-in/{id}', [MobileInController::class,'update']);
    Route::delete('/mobile-in/{id}', [MobileInController::class,'destroy']);
    Route::patch('/mobile-in/{id}/toggle-sold', [MobileInController::class,'toggleSold']);

    // Mobile Out
    Route::get('/mobile-out', [MobileOutController::class,'index']);
    Route::get('/mobile-out/{id}', [MobileOutController::class,'show']);
    Route::post('/mobile-out', [MobileOutController::class,'store']);
    Route::match(['put','patch'],'/mobile-out/{id}', [MobileOutController::class,'update']);
    Route::delete('/mobile-out/{id}', [MobileOutController::class,'destroy']);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class)->except(['create','edit']);

    // Purchase Invoices
    Route::apiResource('purchase-invoices', PurchaseInvoiceController::class)->except(['create','edit']);

    //Services
    Route::apiResource('services', ServiceController::class);

});
Route::middleware(['auth:sanctum'])
    ->prefix('admin')->name('admin.')
    ->group(function () {

        Route::apiResource('colors', ColorController::class);
        Route::apiResource('users', UserController::class);
        Route::apiResource('storages', DeviceStorageController::class);
        Route::apiResource('devices', DeviceController::class);
        Route::apiResource('customers', CustomerController::class);

    });
