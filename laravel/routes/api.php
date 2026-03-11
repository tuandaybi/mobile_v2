<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    MobileInController, MobileOutController, PurchaseInvoiceController, 
    ServiceController, AuthController, DebtController, HomeController,
    ReportController, InboxController, ZaloWebhookController, AppUpdateController
};
use App\Http\Controllers\admin\{
    BackupController, CustomerController, DeviceController, 
    DeviceStorageController, ColorController, StoreController, 
    SupplierController, UserController, UserTokenController
};

Route::get('/ping', fn() => response()->json(['ok' => true]));

Route::post('/login', [AuthController::class, 'login']);
Route::post('/redeem', [AuthController::class, 'redeem'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'store']);
Route::get('/home', [HomeController::class, 'index'])->middleware('auth:sanctum');

Route::get('/app-updates/latest', [AppUpdateController::class, 'latest'])->name('app-updates.latest.legacy');
Route::get('/app-updates/{appSlug}/latest', [AppUpdateController::class, 'latest'])->name('app-updates.latest.default');
Route::get('/app-updates/{appSlug}/{channel}/latest', [AppUpdateController::class, 'latest'])->name('app-updates.latest');
Route::get('/app-updates/{appSlug}/{channel}/download/{filename}', [AppUpdateController::class, 'download'])->name('app-updates.download');
// App updates
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('app-updates', [AppUpdateController::class, 'index'])->name('app-updates.index');
    Route::get('app-updates/trash', [AppUpdateController::class, 'trash'])->name('app-updates.trash');
    Route::post('app-updates/{appSlug}/{channel}/restore', [AppUpdateController::class, 'restore'])->name('app-updates.restore');
    //Route::delete('app-updates/{appSlug}/{channel}', [AppUpdateController::class, 'destroy'])->name('app-updates.destroy');
}

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
    Route::get('/debts/{debt}/payments', [DebtController::class, 'paymentsByDebt']);

    //Search Imei
    Route::get('/mobile-in/search-imei/{imei}', [MobileInController::class, 'searchImei']);

    //Report
    Route::get('/reports/profit-daily', [ReportController::class, 'profitDaily']);
    Route::get('/reports/sales-models', [ReportController::class, 'salesModels']);
    Route::get('/reports/debt-summary', [ReportController::class, 'debtSummary']);

    //Notifications
    Route::get('/inbox', [InboxController::class, 'index']);
    Route::post('/inbox', [InboxController::class, 'store']);
    Route::get('/inbox/{id}', [InboxController::class, 'show']);
    Route::post('/inbox/{id}/read', [InboxController::class, 'markRead']);
    Route::post('/inbox/read-all', [InboxController::class, 'readAll']); // optional
    Route::post('/inbox/{id}/comment', [InboxController::class, 'comment']);
    Route::delete('/inbox/read', [InboxController::class, 'destroyRead']);
    // Purchase Invoices
    //Route::apiResource('purchase-invoices', PurchaseInvoiceController::class)->except(['create','edit']);

    Route::post('app-updates/publish', [AppUpdateController::class, 'publish'])->name('app-updates.publish');

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
        Route::get('ad-customers', [CustomerController::class, 'indexAdmin']); // Admin Customer Page

        //Admin -> Devices
        Route::apiResource('devices', DeviceController::class);
            //Admin -> Devices -> Storages
            Route::apiResource('storages', DeviceStorageController::class);

        //Admin -> Colors
        Route::apiResource('colors', ColorController::class);



        //Admin -> Backups
        Route::get('backups', [BackupController::class, 'index']);
        Route::post('backups', [BackupController::class, 'create']);
        Route::get('backups/download/{id}', [BackupController::class, 'download']);
        Route::delete('backups/{id}', [BackupController::class, 'destroy']);

        // Suppliers
        //Route::apiResource('suppliers', SupplierController::class)->except(['create','edit']);

    });

// routes/web.php (khu admin, đã có auth + can)
Route::prefix('admin/users')->middleware(['auth:sanctum'])->group(function () {
    Route::get('{id}/tokens', [UserTokenController::class, 'index']);
    Route::post('{id}/tokens', [UserTokenController::class, 'store']);
    Route::delete('{id}/tokens/{tokenId}', [UserTokenController::class, 'destroy']);
});

 
