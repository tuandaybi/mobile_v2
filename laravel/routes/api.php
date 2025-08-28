<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    MobileInController, MobileOutController, PurchaseInvoiceController, 
    ServiceController, AuthController, DebtController
};
use App\Http\Controllers\admin\{
    BackupController, CustomerController, DeviceController, 
    DeviceStorageController, ColorController, StoreController, 
    SupplierController, UserController
};

Route::post('/login', [AuthController::class, 'login']);
Route::post('/redeem', [AuthController::class, 'redeem'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'store']);
Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/user', [AuthController::class, 'index']);
    Route::patch('/user', [AuthController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);

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

    //Services
    Route::apiResource('services', ServiceController::class);

    //Debt
    Route::get('/debts/summary', [DebtController::class, 'summary']);
    Route::get('/debts/customer/{customer}', [DebtController::class, 'openDebtsByCustomer']);
    Route::post('/debts/{debt}/pay', [DebtController::class, 'payOne']);
    Route::post('/debts/settle-customer/{customer}', [DebtController::class, 'settleCustomer']);

    //Search Imei
    Route::get('/mobile-in/search-imei/{imei}', [MobileInController::class, 'searchImei']);

    // Purchase Invoices
    //Route::apiResource('purchase-invoices', PurchaseInvoiceController::class)->except(['create','edit']);

});

Route::middleware(['auth:sanctum'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        //Admin -> Users
        Route::apiResource('user', UserController::class);
        Route::put('user/{id}/active', [UserController::class, 'activeUser']);
        Route::get('taoquyen', [UserController::class, 'defaultRoleAndPermiss']);

        //Admin -> Stores
        Route::apiResource('stores', StoreController::class);

        //Admin -> Customers
        Route::apiResource('customers', CustomerController::class);

        //Admin -> Devices
        Route::apiResource('devices', DeviceController::class);
            //Admin -> Devices -> Storages
            Route::apiResource('storages', DeviceStorageController::class);

        //Admin -> Colors
        Route::apiResource('colors', ColorController::class);

        //Admin -> Backups
        Route::apiResource('backups', BackupController::class);

        // Suppliers
        //Route::apiResource('suppliers', SupplierController::class)->except(['create','edit']);

    });
