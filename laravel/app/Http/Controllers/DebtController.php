<?php

namespace App\Http\Controllers;

use App\Http\Controllers;
use App\Models\Debt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\DebtResource;
use App\Http\Resources\DebtPaymentResource;
use App\Http\Requests\Debt\StoreDebtRequest;
use App\Http\Requests\Debt\UpdateDebtRequest;
use App\Http\Requests\Debt\StoreDebtPaymentRequest;
use App\Http\Requests\Debt\UpdateDebtPaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebtController extends Controller
{
    protected function resolveStoreId(Request $r): int
    {
        return (int) DB::table('user_in_store')
            ->where('user_id', $r->user()->id)
            ->value('store_id');
    }

    public function index(Request $r)
    {
        $q = Debt::query()->orderByDesc('date')->orderByDesc('id');

        if ($r->filled('customer_id')) $q->where('customer_id', $r->integer('customer_id'));
        if ($r->filled('status'))      $q->where('status', $r->input('status'));
        if ($r->filled('from'))        $q->whereDate('date', '>=', $r->date('from'));
        if ($r->filled('to'))          $q->whereDate('date', '<=', $r->date('to'));
        if ($r->filled('q'))           $q->where('note', 'like', '%'.$r->input('q').'%');

        $per = max(1, (int) $r->input('per_page', 50));
        $page = $q->paginate($per);

        // Resource sẽ giữ nguyên cấu trúc paginate (links, meta) + map data
        return DebtResource::collection($page);
    }

    public function show(Debt $debt)
    {
        $debt->load(['customers','services','mobileOut']);
        return new DebtResource($debt);
    }

    public function store(StoreDebtRequest $req)
    {
        $data = $req->validated();

        $debt = Debt::create([
            'mobileout_id' => $data['mobileout_id'] ?? null,
            'service_id'   => $data['service_id'] ?? null,
            'customer_id'  => $data['customer_id'] ?? null,
            'debt'         => $data['debt'],
            'paid_amount'  => 0,
            'status'       => 'pending',
            'date'         => $data['date'],
            'due_date'     => $data['due_date'] ?? null,
            'note'         => $data['note'] ?? null,
        ]);

        return (new DebtResource($debt))
            ->additional(['message' => 'Tạo công nợ thành công'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDebtRequest $req, Debt $debt)
    {
        $data = $req->validated();
        $debt->fill($data)->save();

        if (array_key_exists('debt', $data)) {
            $debt->recalcCache();
        }

        return (new DebtResource($debt->fresh()))
            ->additional(['message' => 'Cập nhật công nợ thành công']);
    }

    public function destroy(Debt $debt)
    {
        $debt->delete();
        return response()->json(['message' => 'Đã xoá công nợ']);
    }

    public function summary(Request $r)
    {
        $storeId = $this->resolveStoreId($r);
        $q = trim((string) $r->input('q'));

        $principal = $this->debtPrincipalColumn(); // ví dụ "debt"

        $perDebt = DB::table('debts as d')
            ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
            ->selectRaw(
                "d.id, d.customer_id, d.`$principal` as principal,
                COALESCE(SUM(p.amount),0) as paid,
                (d.`$principal` - COALESCE(SUM(p.amount),0)) as remaining"
            )
            ->groupBy('d.id', 'd.customer_id', "d.$principal");

        $openDebts = DB::query()
            ->fromSub($perDebt, 'x')
            ->where('x.remaining', '>', 0);

        $rows = DB::query()
            ->fromSub($openDebts, 'y')
            ->join('customers as c', 'c.id', '=', 'y.customer_id')
            ->where('c.store_id', $storeId)
            ->when($q !== '', function ($qq) use ($q) {
                $like = "%{$q}%";
                $qq->where(function ($w) use ($like) {
                    $w->where('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like);
                });
            })
            ->selectRaw('
                c.id as customer_id,
                c.name as customer_name,
                c.phone as phone,
                COUNT(*) as number_debt,
                SUM(y.principal) as debt_total,
                SUM(y.paid) as payment_total,
                SUM(y.remaining) as total
            ')
            ->groupBy('c.id', 'c.name', 'c.phone')
            ->orderByDesc('total')
            ->get();

        return response()->json($rows);
    }

    public function openDebtsByCustomer(Request $r, int $customer)
    {
        $storeId   = $this->resolveStoreId($r);
        $principal = $this->debtPrincipalColumn();

        $valid = DB::table('customers')
            ->where('id', $customer)
            ->where('store_id', $storeId)
            ->exists();
        if (!$valid) return response()->json(['message' => 'Khách không thuộc cửa hàng'], 403);

        $originSelects = $this->originCaseSelect(); // 3 selectRaw strings
        $originSql     = implode(", ", $originSelects);

        $perDebt = DB::table('debts as d')
            ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
            ->selectRaw(
                "d.id,
                d.note,
                d.created_at,
                d.`$principal` as amount,
                COALESCE(SUM(p.amount),0) as paid,
                (d.`$principal` - COALESCE(SUM(p.amount),0)) as remaining,
                $originSql"
            )
            ->where('d.customer_id', $customer)
            ->groupBy('d.id', 'd.note', 'd.created_at', "d.$principal"
                // group by các cột origin nếu DB cần (MySQL thường không bắt buộc với function CASE/COALESCE constant),
                // nhưng để chắc chắn, thêm:
                // , DB::raw(str_replace(' as ', ', ', $originSql)) // <- không khuyến nghị vì phức tạp
            )
            ->having('remaining', '>', 0)
            ->orderBy('d.created_at', 'asc')
            ->get();

        return response()->json($perDebt);
    }

    public function payOne(Request $r, int $debtId)
    {
        $amount = (int) $r->input('amount', 0);
        $note   = (string) $r->input('note', '');

        if ($amount <= 0) {
            return response()->json(['message' => 'Số tiền không hợp lệ'], 422);
        }

        $principal = $this->debtPrincipalColumn();

        // Lấy thông tin khoản nợ + phần còn lại, kiểm tra store
        $row = DB::table('debts as d')
            ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
            ->join('customers as c', 'c.id', '=', 'd.customer_id')
            ->selectRaw("
                d.id,
                d.`$principal` as principal,
                c.store_id,
                (d.`$principal` - COALESCE(SUM(p.amount),0)) as remaining
            ")
            ->where('d.id', $debtId)
            ->groupBy('d.id', "d.$principal", 'c.store_id')
            ->first();

        if (!$row) return response()->json(['message' => 'Không tìm thấy khoản nợ'], 404);

        $storeId = $this->resolveStoreId($r);
        if ((int) $row->store_id !== (int) $storeId) {
            return response()->json(['message' => 'Khoản nợ không thuộc cửa hàng của bạn'], 403);
        }

        if ($row->remaining <= 0) {
            return response()->json(['message' => 'Khoản nợ đã tất toán'], 409);
        }
        if ($amount > $row->remaining) {
            return response()->json(['message' => 'Số tiền vượt quá phần còn lại'], 422);
        }

        DB::table('debt_payments')->insert([
            'debt_id'    => $debtId,
            'amount'     => $amount,
            'note'       => $note,
            'paid_at'    => now(),  
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Đã ghi nhận thanh toán']);
    }

    public function settleCustomer(Request $r, int $customerId)
    {
        $storeId   = $this->resolveStoreId($r);
        $principal = $this->debtPrincipalColumn();

        // Khách phải thuộc store hiện tại
        $valid = DB::table('customers')
            ->where('id', $customerId)
            ->where('store_id', $storeId)
            ->exists();
        if (!$valid) {
            return response()->json(['message' => 'Khách không thuộc cửa hàng'], 403);
        }

        // Tính remaining cho từng debt (dùng subquery để gọn và tránh ONLY_FULL_GROUP_BY)
        $perDebt = DB::table('debts as d')
            ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
            ->selectRaw(
                "d.id,
                d.`$principal` as principal,
                COALESCE(SUM(p.amount),0) as paid,
                (d.`$principal` - COALESCE(SUM(p.amount),0)) as remaining"
            )
            ->where('d.customer_id', $customerId)
            ->groupBy('d.id', DB::raw("d.`$principal`"));

        $openDebts = DB::query()
            ->fromSub($perDebt, 'x')
            ->where('x.remaining', '>', 0)
            ->orderBy('x.id', 'asc')
            ->get();

        if ($openDebts->isEmpty()) {
            return response()->json(['message' => 'Không còn nợ để tất toán'], 409);
        }

        DB::transaction(function () use ($openDebts) {
            foreach ($openDebts as $d) {
                DB::table('debt_payments')->insert([
                    'debt_id'    => $d->id,
                    'amount'     => (int) $d->remaining,
                    'note'       => 'Tất toán tự động',
                    'paid_at'    => now(),       // ⚠️ bắt buộc nếu cột NOT NULL
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['message' => 'Đã tất toán toàn bộ công nợ của khách']);
    }

    private function debtPrincipalColumn(): string
    {
        foreach (['amount','debt','total_amount','total'] as $col) {
            if (Schema::hasColumn('debts', $col)) return $col;
        }
        abort(500, "Bảng debts không có cột gốc (amount/debt/total_amount/total).");
    }

    private function originCaseSelect(): array
    {
        // Hỗ trợ 2 kiểu phổ biến: debts.mobile_out_id, debts.service_id
        $mobileCol = Schema::hasColumn('debts', 'mobileout_id') ? 'mobileout_id' : (Schema::hasColumn('debts', 'sale_id') ? 'sale_id' : null);
        $serviceCol = Schema::hasColumn('debts', 'service_id') ? 'service_id' : null;

        if ($mobileCol || $serviceCol) {
            $typeCase = "CASE ".
                ($mobileCol ? "WHEN d.$mobileCol IS NOT NULL THEN 'mobile' " : "").
                ($serviceCol ? "WHEN d.$serviceCol IS NOT NULL THEN 'service' " : "").
                "ELSE 'unknown' END";

            $idCoalesce = "COALESCE(".
                ($mobileCol ? "d.$mobileCol, " : "").
                ($serviceCol ? "d.$serviceCol, " : "").
                "NULL)";

            $labelCase = "CASE ".
                ($mobileCol ? "WHEN d.$mobileCol IS NOT NULL THEN CONCAT('Bán máy - #', d.$mobileCol) " : "").
                ($serviceCol ? "WHEN d.$serviceCol IS NOT NULL THEN CONCAT('Dịch vụ - #', d.$serviceCol) " : "").
                "ELSE '—' END";

            return [
                "$typeCase as origin_type",
                "$idCoalesce as origin_id",
                "$labelCase as origin_label",
            ];
        }

        // fallback: không có cột nhận diện nguồn
        return [
            "'unknown' as origin_type",
            "NULL as origin_id",
            "'—' as origin_label",
        ];
    }
}
