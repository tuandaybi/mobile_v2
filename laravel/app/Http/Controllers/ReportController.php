<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\ResolvesStore;

class ReportController extends Controller
{
    /**
     * GET /reports/profit-daily?years=2025,2024&months=7,8
     * Trả dữ liệu tổng hợp theo NGÀY cho 2 loại:
     *  - phone:   từ bảng mobile_out (doanh thu, lợi nhuận)
     *  - service: từ bảng services (doanh thu, lợi nhuận)
     *
     * Dạng trả về:
     * {
     *   "phone":   [{year, month, day, revenue, profit}, ...],
     *   "service": [{year, month, day, revenue, profit}, ...]
     * }
     */
    use ResolvesStore;

    public function profitDaily(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        // Parse params
        $years  = $this->parseIntList($r->query('years'));
        $months = $this->parseIntList($r->query('months'));

        if (empty($years))  $years  = [now()->year];
        if (empty($months)) $months = [now()->month];

        // MOBILE OUT
        // Giả định:
        // - Doanh thu:  export_price (fallback price/subtotal nếu có)
        // - Lợi nhuận:  export_price - expense
        // - Ngày bán:   export_date (fallback date)
        // - Lọc store:  join sang mobile_in để lấy mi.store_id
        $phone = DB::table('mobile_out as mo')
            ->join('mobile_in as mi', 'mi.id', '=', 'mo.mobile_in_id')
            ->selectRaw("
                YEAR(COALESCE(mo.export_date, mo.created_at))  as year,
                MONTH(COALESCE(mo.export_date, mo.created_at)) as month,
                DAY(COALESCE(mo.export_date, mo.created_at))   as day,
                SUM(COALESCE(mo.export_price, 0)) as revenue,
                SUM(
                    COALESCE(mo.export_price, 0)
                - COALESCE(mi.import_price, 0)
                - COALESCE(mo.expense, 0)
                ) as profit
            ")
            ->where('mi.store_id', $storeId)
            ->whereIn(DB::raw('YEAR(COALESCE(mo.export_date, mo.created_at))'), $years)
            ->whereIn(DB::raw('MONTH(COALESCE(mo.export_date, mo.created_at))'), $months)
            ->groupBy('year', 'month', 'day')
            ->orderBy('year')->orderBy('month')->orderBy('day')
            ->get()
            ->map(fn($r) => [
                'year'    => (int) $r->year,
                'month'   => (int) $r->month,
                'day'     => (int) $r->day,
                'revenue' => (float) $r->revenue,
                'profit'  => (float) $r->profit,
            ])->values();

        // SERVICE
        // Giả định:
        // - Doanh thu: service_price
        // - Lợi nhuận: service_price - expense
        // - Ngày:      service_date (fallback created_at)
        $service = DB::table('services as s')
            ->selectRaw("
                YEAR(COALESCE(s.created_at))  as year,
                MONTH(COALESCE(s.created_at)) as month,
                DAY(COALESCE(s.created_at))   as day,
                SUM(COALESCE(s.price, 0))                                                 as revenue,
                SUM(COALESCE(s.price, 0) - COALESCE(s.expense, 0))                        as profit
            ")
            ->where('s.store_id', $storeId)
            ->whereIn(DB::raw("YEAR(COALESCE(s.created_at))"), $years)
            ->whereIn(DB::raw("MONTH(COALESCE(s.created_at))"), $months)
            ->groupBy('year', 'month', 'day')
            ->orderBy('year')->orderBy('month')->orderBy('day')
            ->get()
            ->map(fn($r) => [
                'year'    => (int) $r->year,
                'month'   => (int) $r->month,
                'day'     => (int) $r->day,
                'revenue' => (float) $r->revenue,
                'profit'  => (float) $r->profit,
            ])->values();

        // EXPENSE
        // Tổng chi phí chung của cửa hàng theo ngày (bảng expenses)
        $expense = DB::table('expenses as e')
            ->selectRaw("
                YEAR(e.date)  as year,
                MONTH(e.date) as month,
                DAY(e.date)   as day,
                SUM(COALESCE(e.amount, 0)) as amount
            ")
            ->where('e.store_id', $storeId)
            ->whereIn(DB::raw('YEAR(e.date)'), $years)
            ->whereIn(DB::raw('MONTH(e.date)'), $months)
            ->groupBy('year', 'month', 'day')
            ->orderBy('year')->orderBy('month')->orderBy('day')
            ->get()
            ->map(fn($r) => [
                'year'   => (int) $r->year,
                'month'  => (int) $r->month,
                'day'    => (int) $r->day,
                'amount' => (float) $r->amount,
            ])->values();

        return response()->json([
            'phone'   => $phone,
            'service' => $service,
            'expense' => $expense,
        ]);
    }

    /** Parse "1,2,3" thành [1,2,3] */
    private function parseIntList($val): array
    {
        if (is_array($val)) {
            return array_values(array_filter(array_map('intval', $val), fn($x) => $x > 0));
        }
        if (is_string($val)) {
            return array_values(array_filter(array_map('intval', explode(',', $val)), fn($x) => $x > 0));
        }
        return [];
    }

    public function salesModels(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        $years  = $this->parseIntList($r->query('years'));
        $months = $this->parseIntList($r->query('months'));

        if (empty($years))  { $years  = [now()->year]; }
        if (empty($months)) { $months = [now()->month]; }

        // ✅ Lấy sản lượng bán theo model
        // Giả định:
        //   - Ngày bán:   COALESCE(mo.export_date, mo.created_at)
        //   - Lọc store:  join sang mobile_in (mi.store_id)
        //   - Model name: từ bảng devices (cột name). Nếu schema khác (device_name), thay dv.name -> dv.device_name
        $rows = \DB::table('mobile_out as mo')
            ->join('mobile_in as mi', 'mi.id', '=', 'mo.mobile_in_id')
            ->leftJoin('devices as dv', 'dv.id', '=', 'mi.device_id')
            ->selectRaw("
                COALESCE(dv.name, CONCAT('Device #', mi.device_id)) as model,
                COUNT(*) as quantity
            ")
            ->where('mi.store_id', $storeId)
            ->whereIn(\DB::raw('YEAR(COALESCE(mo.export_date, mo.created_at))'), $years)
            ->whereIn(\DB::raw('MONTH(COALESCE(mo.export_date, mo.created_at))'), $months)
            ->groupBy('model')
            ->orderByDesc('quantity')
            ->get()
            ->map(fn($r) => [
                'model'    => (string) $r->model,
                'quantity' => (int) $r->quantity,
            ])->values();

        return response()->json($rows);
    }

    public function debtSummary(Request $r, int $customer)
    {
        $storeId   = $this->resolveStoreId($r);
        $principal = 'debt'; // cột gốc nợ của bạn

        // ✅ Kiểm tra customer thuộc store
        $valid = DB::table('customers')
            ->where('id', $customer)
            ->where('store_id', $storeId)
            ->exists();
        if (!$valid) {
            return response()->json(['message' => 'Khách không thuộc cửa hàng'], 403);
        }

        $perDebt = DB::table('debts as d')
            ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
            ->whereNull('d.deleted_at')
            ->selectRaw(
                "d.id,
                d.note,
                d.created_at,
                d.`$principal` as amount,
                COALESCE(SUM(p.amount),0) as paid,
                (d.`$principal` - COALESCE(SUM(p.amount),0)) as remaining,
                CASE
                WHEN d.mobileout_id IS NOT NULL THEN 'mobile'
                WHEN d.service_id   IS NOT NULL THEN 'service'
                ELSE 'unknown'
                END as origin_type,
                COALESCE(d.mobileout_id, d.service_id, NULL) as origin_id,
                CASE
                WHEN d.mobileout_id IS NOT NULL THEN CONCAT('Bán máy - #', d.mobileout_id)
                WHEN d.service_id   IS NOT NULL THEN CONCAT('Dịch vụ - #', d.service_id)
                ELSE '—'
                END as origin_label"
            )
            ->where('d.customer_id', $customer)
            ->groupBy('d.id', 'd.note', 'd.created_at', "d.$principal")
            ->orderBy('d.created_at', 'asc')
            ->get();

        $debts = $perDebt->map(fn($row) => [
            'id'           => (int) $row->id,
            'note'         => $row->note,
            'created_at'   => (string) $row->created_at,
            'amount'       => (float) $row->amount,
            'paid'         => (float) $row->paid,
            'remaining'    => (float) $row->remaining,
            'origin_type'  => $row->origin_type ?? 'unknown',
            'origin_id'    => isset($row->origin_id) ? (int) $row->origin_id : null,
            'origin_label' => $row->origin_label ?? null,
        ])->values();

        // Nếu muốn trả kèm payments theo từng debt (1 call):
        if ($r->boolean('include_payments')) {
            $ids = $debts->pluck('id')->all();
            if (!empty($ids)) {
                $payments = DB::table('debt_payments as dp')
                    ->leftJoin('users as u', 'u.id', '=', 'dp.created_by')
                    ->selectRaw('
                        dp.id,
                        dp.debt_id,
                        dp.amount,
                        dp.note,
                        dp.created_at,
                        u.name as user_name
                    ')
                    ->whereIn('dp.debt_id', $ids)
                    ->orderBy('dp.created_at', 'asc')
                    ->get()
                    ->groupBy('debt_id');

                $debts = $debts->map(function ($d) use ($payments) {
                    $items = ($payments->get($d['id']) ?? collect())->map(fn($p) => [
                        'id'         => (int) $p->id,
                        'amount'     => (float) $p->amount,
                        'note'       => $p->note,
                        'created_at' => (string) $p->created_at,
                        'user_name'  => $p->user_name,
                    ])->values();

                    $d['payments'] = $items;
                    return $d;
                })->values();
            } else {
                $debts = $debts->map(function ($d) {
                    $d['payments'] = [];
                    return $d;
                })->values();
            }
        }

        return response()->json($debts);
    }

}
