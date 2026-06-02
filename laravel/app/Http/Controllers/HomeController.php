<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\MobileIn;
use App\Models\MobileOut;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Debt;
use App\Models\DebtPayment;

class HomeController extends Controller
{
    use ResolvesStore;

    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        $tblIn   = (new MobileIn)->getTable();
        $tblOut  = (new MobileOut)->getTable();
        $tblSvc  = (new Service)->getTable();
        $tblCus  = (new Customer)->getTable();
        $tblDev  = (new Device)->getTable();
        $tblDebt = (new Debt)->getTable();
        $tblPay  = (new DebtPayment)->getTable();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        /** INVENTORY */
        $inventoryByDevice = DB::table($tblIn.' as mi')
            ->join($tblDev.' as d', 'd.id', '=', 'mi.device_id')
            ->where('mi.store_id', $storeId)
            ->where('mi.is_sold', false) // nếu dùng tinyint thì thay = 0
            ->groupBy('mi.device_id', 'd.name')
            ->select([
                'mi.device_id',
                'd.name as device_name',
                DB::raw('COUNT(*) as qty'),
                DB::raw('COALESCE(SUM(mi.import_price),0) as total_value')
            ])
            ->orderByDesc('qty')
            ->get();

        $inventoryTotalValue = DB::table($tblIn.' as mi')
            ->where('mi.store_id', $storeId)
            ->where('mi.is_sold', false)
            ->sum('mi.import_price');

        $inventoryTotalQty = DB::table($tblIn.' as mi')
            ->where('mi.store_id', $storeId)
            ->where('mi.is_sold', false)
            ->count();

        $topInventoryDevice = DB::table($tblIn.' as mi')
            ->join($tblDev.' as d', 'd.id', '=', 'mi.device_id')
            ->where('mi.store_id', $storeId)
            ->where('mi.is_sold', false)
            ->groupBy('mi.device_id', 'd.name')
            ->select([
                'mi.device_id',
                'd.name as device_name',
                DB::raw('COUNT(*) as qty')
            ])
            ->orderByDesc('qty')
            ->first();

        /** SALES (MOBILE OUT) */
        // all-time
        $totalSales = DB::table($tblOut.' as mo')
            ->join($tblIn.' as mi', 'mi.id', '=', 'mo.mobile_in_id')
            ->where('mi.store_id', $storeId)
            ->sum('mo.export_price');

        $salesProfit = DB::table($tblOut.' as mo')
            ->join($tblIn.' as mi', 'mi.id', '=', 'mo.mobile_in_id')
            ->where('mi.store_id', $storeId)
            ->selectRaw('COALESCE(SUM(mo.export_price - mi.import_price - COALESCE(mo.expense,0)),0) as profit')
            ->value('profit');

        // best seller all-time
        $bestSellerAllTime = DB::table($tblOut.' as mo')
            ->join($tblIn.' as mi', 'mi.id', '=', 'mo.mobile_in_id')
            ->join($tblDev.' as d', 'd.id', '=', 'mi.device_id')
            ->where('mi.store_id', $storeId)
            ->groupBy('mi.device_id', 'd.name')
            ->select([
                'mi.device_id',
                'd.name as device_name',
                DB::raw('COUNT(*) as sold_qty')
            ])
            ->orderByDesc('sold_qty')
            ->first();

        // this month
        $salesThisMonth = [
            'total_revenue' => (float) DB::table($tblOut.' as mo')
                ->join($tblIn.' as mi', 'mi.id', '=', 'mo.mobile_in_id')
                ->where('mi.store_id', $storeId)
                ->whereBetween('mo.export_date', [$startOfMonth, $endOfMonth]) // đổi cột nếu dùng export_date
                ->sum('mo.export_price'),

            'profit' => (float) DB::table($tblOut.' as mo')
                ->join($tblIn.' as mi', 'mi.id', '=', 'mo.mobile_in_id')
                ->where('mi.store_id', $storeId)
                ->whereBetween('mo.export_date', [$startOfMonth, $endOfMonth])
                ->selectRaw('COALESCE(SUM(mo.export_price - mi.import_price - COALESCE(mo.expense,0)),0) as profit')
                ->value('profit'),

            'best_seller' => DB::table($tblOut.' as mo')
                ->join($tblIn.' as mi', 'mi.id', '=', 'mo.mobile_in_id')
                ->join($tblDev.' as d', 'd.id', '=', 'mi.device_id')
                ->where('mi.store_id', $storeId)
                ->whereBetween('mo.export_date', [$startOfMonth, $endOfMonth])
                ->groupBy('mi.device_id', 'd.name')
                ->select([
                    'mi.device_id',
                    'd.name as device_name',
                    DB::raw('COUNT(*) as sold_qty')
                ])
                ->orderByDesc('sold_qty')
                ->first(),
        ];

        /** SERVICE */
        // all-time
        $totalServiceRevenue = DB::table($tblSvc.' as s')
            ->where('s.store_id', $storeId)
            ->sum('s.price');

        $serviceProfit = DB::table($tblSvc.' as s')
            ->where('s.store_id', $storeId)
            ->selectRaw('COALESCE(SUM(s.price - COALESCE(s.expense,0)),0) as profit')
            ->value('profit');

        // this month
        $serviceThisMonth = [
            'total_revenue' => (float) DB::table($tblSvc.' as s')
                ->where('s.store_id', $storeId)
                ->whereBetween('s.created_at', [$startOfMonth, $endOfMonth]) // nếu có service_date thì đổi
                ->sum('s.price'),

            'profit' => (float) DB::table($tblSvc.' as s')
                ->where('s.store_id', $storeId)
                ->whereBetween('s.created_at', [$startOfMonth, $endOfMonth])
                ->selectRaw('COALESCE(SUM(s.price - COALESCE(s.expense,0)),0) as profit')
                ->value('profit'),
        ];

        /** CUSTOMERS & DEBTS */
        $totalCustomers = DB::table($tblCus.' as c')
            ->where('c.store_id', $storeId)
            ->count();

        /* Tổng nợ gốc (theo khách thuộc store, bỏ qua debt đã soft-delete) */
        $totalDebt = (float) DB::table($tblDebt.' as d')
            ->join($tblCus.' as c', 'c.id', '=', 'd.customer_id')
            ->whereNull('d.deleted_at')
            ->where('c.store_id', $storeId)
            ->selectRaw('COALESCE(SUM(d.debt),0) as total_debt')
            ->value('total_debt');

        /* Tổng tiền đã trả (join payments → debts → customers, bỏ qua debt đã soft-delete) */
        $totalPaid = (float) DB::table($tblPay.' as p')
            ->join($tblDebt.' as d', 'd.id', '=', 'p.debt_id')
            ->join($tblCus.' as c', 'c.id', '=', 'd.customer_id')
            ->whereNull('d.deleted_at')
            ->where('c.store_id', $storeId)
            ->selectRaw('COALESCE(SUM(p.amount),0) as total_paid') // nếu cột là paid_amount thì đổi ở đây
            ->value('total_paid');

        /* Nợ còn lại = Nợ gốc - Đã trả */
        $outstandingDebt = $totalDebt - $totalPaid;

        return response()->json([
            'inventory' => [
                'by_device'    => $inventoryByDevice,
                'total_value'  => (float)$inventoryTotalValue,
                'total_qty'    => (int)$inventoryTotalQty,
                'top_device'   => $topInventoryDevice,
            ],
            'sales' => [
                'total_revenue'         => (float)$totalSales,     // all-time
                'profit'                => (float)$salesProfit,    // all-time
                'best_seller_all_time'  => $bestSellerAllTime,
                'this_month'            => $salesThisMonth,        // { total_revenue, profit, best_seller }
            ],
            'service' => [
                'total_revenue' => (float)$totalServiceRevenue,    // all-time
                'profit'        => (float)$serviceProfit,          // all-time
                'this_month'    => $serviceThisMonth,              // { total_revenue, profit }
            ],
            'customers' => [
                'total_customers'  => (int)$totalCustomers,
                'total_debt'       => (float)$totalDebt,
                'total_paid'       => (float)$totalPaid,
                'outstanding_debt' => (float)$outstandingDebt,
            ],
        ]);
    }
}
