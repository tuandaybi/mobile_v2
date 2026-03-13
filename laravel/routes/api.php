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
Route::get('/home', [HomeController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:trangchinh']);


Route::get('admin/app-updates/latest', [AppUpdateController::class, 'latest'])->name('app-updates.latest.legacy');
Route::get('admin/app-updates/{appSlug}/latest', [AppUpdateController::class, 'latest'])->name('app-updates.latest.default');
Route::get('admin/app-updates/{appSlug}/{channel}/latest', [AppUpdateController::class, 'latest'])->name('app-updates.latest');
Route::get('admin/app-updates/{appSlug}/{channel}/download/{filename}', [AppUpdateController::class, 'download'])->name('app-updates.download');
// App updates
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'permission:admin.saoluu'])
    ->name('admin.')
    ->group(function () {
        Route::get('app-updates', [AppUpdateController::class, 'index'])->name('app-updates.index');
        Route::get('app-updates/trash', [AppUpdateController::class, 'trash'])->name('app-updates.trash');
        Route::post('app-updates/publish', [AppUpdateController::class, 'publish'])->name('app-updates.publish');
        Route::post('app-updates/{appSlug}/{channel}/restore', [AppUpdateController::class, 'restore'])->name('app-updates.restore');
        Route::delete('app-updates/{appSlug}/{channel}', [AppUpdateController::class, 'destroy'])->name('app-updates.destroy');
    });

Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/user', [AuthController::class, 'index']);
    Route::patch('/user', [AuthController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Mobile In
    Route::get('/mobile-in', [MobileInController::class,'index'])->middleware('permission:dienthoai.xemmua');
    Route::get('/mobile-in/{id}', [MobileInController::class,'show'])->middleware('permission:dienthoai.xemmua');
    Route::post('/mobile-in', [MobileInController::class,'store'])->middleware('permission:dienthoai.themmua');
    Route::match(['put','patch'],'/mobile-in/{id}', [MobileInController::class,'update'])->middleware('permission:dienthoai.suamua');
    Route::delete('/mobile-in/{id}', [MobileInController::class,'destroy'])->middleware('permission:dienthoai.xoamua');
    Route::patch('/mobile-in/{id}/toggle-sold', [MobileInController::class,'toggleSold'])->middleware('permission:dienthoai.suamua');

    // Mobile Out
    Route::get('/mobile-out', [MobileOutController::class,'index'])->middleware('permission:dienthoai.xemban');
    Route::get('/mobile-out/{id}', [MobileOutController::class,'show'])->middleware('permission:dienthoai.xemban');
    Route::post('/mobile-out', [MobileOutController::class,'store'])->middleware('permission:dienthoai.themban');
    Route::match(['put','patch'],'/mobile-out/{id}', [MobileOutController::class,'update'])->middleware('permission:dienthoai.suaban');
    Route::delete('/mobile-out/{id}', [MobileOutController::class,'destroy'])->middleware('permission:dienthoai.xoaban');

    //Services
    Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:dichvu.xem');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->middleware('permission:dichvu.xem');
    Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:dichvu.them');
    Route::match(['put','patch'], '/services/{service}', [ServiceController::class, 'update'])->middleware('permission:dichvu.sua');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->middleware('permission:dichvu.xoa');

    //Debt
    Route::get('/debts/summary', [DebtController::class, 'summary'])->middleware('permission:congno.xem');
    Route::get('/debts/customer/{customer}', [DebtController::class, 'openDebtsByCustomer'])->middleware('permission:congno.xem');
    Route::post('/debts/{debt}/pay', [DebtController::class, 'payOne'])->middleware('permission:congno.sua');
    Route::post('/debts/settle-customer/{customer}', [DebtController::class, 'settleCustomer'])->middleware('permission:congno.sua');
    Route::get('/debts/{debt}/payments', [DebtController::class, 'paymentsByDebt'])->middleware('permission:congno.xem');

    //Search Imei
    Route::get('/mobile-in/search-imei/{imei}', [MobileInController::class, 'searchImei'])->middleware('permission:checkimei.xem');

    //Report
    Route::get('/reports/profit-daily', [ReportController::class, 'profitDaily'])->middleware('permission:baocaoloinhuan.xem');
    Route::get('/reports/sales-models', [ReportController::class, 'salesModels'])->middleware('permission:baocaosanluong.xem');
    Route::get('/reports/debt-summary', [ReportController::class, 'debtSummary'])->middleware('permission:baocaosanluong.xem');

    //Notifications
    Route::get('/inbox', [InboxController::class, 'index'])->middleware('permission:admin.thongbao');
    Route::post('/inbox', [InboxController::class, 'store'])->middleware('permission:admin.thongbao');
    Route::get('/inbox/{id}', [InboxController::class, 'show'])->middleware('permission:admin.thongbao');
    Route::post('/inbox/{id}/read', [InboxController::class, 'markRead'])->middleware('permission:admin.thongbao');
    Route::post('/inbox/read-all', [InboxController::class, 'readAll'])->middleware('permission:admin.thongbao'); // optional
    Route::post('/inbox/{id}/comment', [InboxController::class, 'comment'])->middleware('permission:admin.thongbao');
    Route::delete('/inbox/read', [InboxController::class, 'destroyRead'])->middleware('permission:admin.thongbao');
    // Purchase Invoices
    //Route::apiResource('purchase-invoices', PurchaseInvoiceController::class)->except(['create','edit']);

});

Route::middleware(['auth:sanctum'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        //Admin -> Users
        Route::apiResource('user', UserController::class)->middleware('permission:admin.users');
        Route::put('user/{id}/active', [UserController::class, 'activeUser'])->middleware('permission:admin.users');
        Route::get('taoquyen', [UserController::class, 'defaultRoleAndPermiss'])->middleware('permission:admin.users.phanquyen');

        //Admin -> Stores
        Route::apiResource('stores', StoreController::class)->middleware('permission:admin.cuahang');

        //Admin -> Customers
        Route::apiResource('customers', CustomerController::class)->middleware('permission:admin.khachhang');
        Route::get('ad-customers', [CustomerController::class, 'indexAdmin'])->middleware('permission:admin.khachhang'); // Admin Customer Page

        //Admin -> Devices
        Route::apiResource('devices', DeviceController::class)->middleware('permission:admin.sanpham');
            //Admin -> Devices -> Storages
            Route::apiResource('storages', DeviceStorageController::class)->middleware('permission:admin.mausanpham');

        //Admin -> Colors
        Route::apiResource('colors', ColorController::class)->middleware('permission:admin.mausanpham');



        //Admin -> Backups
        Route::get('backups', [BackupController::class, 'index'])->middleware('permission:admin.saoluu');
        Route::post('backups', [BackupController::class, 'create'])->middleware('permission:admin.saoluu');
        Route::get('backups/download/{id}', [BackupController::class, 'download'])->middleware('permission:admin.saoluu');
        Route::delete('backups/{id}', [BackupController::class, 'destroy'])->middleware('permission:admin.saoluu');

        // Suppliers
        //Route::apiResource('suppliers', SupplierController::class)->except(['create','edit']);

    });

// routes/web.php (khu admin, đã có auth + can)
Route::prefix('admin/users')->middleware(['auth:sanctum'])->group(function () {
    Route::get('{id}/tokens', [UserTokenController::class, 'index'])->middleware('permission:admin.users');
    Route::post('{id}/tokens', [UserTokenController::class, 'store'])->middleware('permission:admin.users');
    Route::delete('{id}/tokens/{tokenId}', [UserTokenController::class, 'destroy'])->middleware('permission:admin.users');
});

 
