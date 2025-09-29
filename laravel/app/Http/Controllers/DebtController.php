<?php

namespace App\Http\Controllers;

use App\Http\Controllers;
use App\Models\Debt;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\DebtResource;
use App\Http\Resources\DebtPaymentResource;
use App\Http\Requests\Debt\StoreDebtRequest;
use App\Http\Requests\Debt\UpdateDebtRequest;
use App\Http\Requests\Debt\StoreDebtPaymentRequest;
use App\Http\Requests\Debt\UpdateDebtPaymentRequest;
use App\Http\Controllers\Concerns\ResolvesStore;
use App\Notifications\TelegramNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class DebtController extends Controller
{
    use ResolvesStore;
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
        $storeId   = $this->resolveStoreId($r);
        $s         = trim((string) $r->input('q', ''));
        $principal = $this->debtPrincipalColumn(); // vd: "debt"

        // Mỗi khoản nợ: principal, paid, remaining
        $perDebt = DB::table('debts as d')
            ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
            ->selectRaw("
                d.id,
                d.customer_id,
                d.`$principal` AS principal,
                COALESCE(SUM(p.amount), 0) AS paid,
                (d.`$principal` - COALESCE(SUM(p.amount), 0)) AS remaining
            ")
            ->groupBy('d.id', 'd.customer_id', DB::raw("d.`$principal`"));

        // Chỉ lấy nợ còn lại > 0
        $openDebts = DB::query()
            ->fromSub($perDebt, 'x')
            ->where('x.remaining', '>', 0);

        // Tổng hợp theo khách
        $base = DB::query()
            ->fromSub($openDebts, 'y')
            ->join('customers as c', 'c.id', '=', 'y.customer_id')
            ->where('c.store_id', $storeId)
            ->when($s !== '', function ($qq) use ($s) {
                $like = "%{$s}%";
                $qq->where(function ($w) use ($like) {
                    $w->where('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like);
                });
            })
            ->selectRaw('
                c.id    AS customer_id,
                c.name  AS customer_name,
                c.phone AS phone,
                COUNT(*)         AS number_debt,
                SUM(y.principal) AS debt_total,
                SUM(y.paid)      AS payment_total,
                SUM(y.remaining) AS total
            ')
            ->groupBy('c.id', 'c.name', 'c.phone'); // <-- group theo user_name

        // ---- sort được phép ----
        $sortable = ['customer_name','phone','number_debt','debt_total','payment_total','total'];
        $sortBy   = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'total';
        $sortDir  = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';

        switch ($sortBy) {
            case 'customer_name':
                $base->orderBy('c.name', $sortDir);
                break;
            case 'phone':
                $base->orderBy('c.phone', $sortDir);
                break;
            case 'number_debt':
                $base->orderByRaw("COUNT(*) {$sortDir}");
                break;
            case 'debt_total':
                $base->orderByRaw("SUM(y.principal) {$sortDir}");
                break;
            case 'payment_total':
                $base->orderByRaw("SUM(y.paid) {$sortDir}");
                break;
            case 'total':
            default:
                $base->orderByRaw("SUM(y.remaining) {$sortDir}");
                break;
        }

        // ---- paginate chuẩn ----
        $perPage = max(1, min((int)$r->input('perPage', 14), 200));
        $p = $base->paginate($perPage)->appends($r->query());

        return response()->json($p);
    }


    public function openDebtsByCustomer(Request $r, int $customer)
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
            ->having('remaining', '>', 0)
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
                d.customer_id,
                d.paid_amount,
                d.`$principal` as principal,
                c.store_id,
                (d.`$principal` - COALESCE(SUM(p.amount),0)) as remaining
            ")
            ->where('d.id', $debtId)
            ->groupBy('d.id', "d.$principal", 'c.store_id')
            ->first();
        $paidAmount = $row->paid_amount;
        if (!$row) return response()->json(['message' => 'Không tìm thấy khoản nợ'], 404);

        $storeId = $this->resolveStoreId($r);
        $userId  = $r->user()->id;
        $customerName = DB::table('customers')->where('id', $row->customer_id)->value('name');

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
            'created_by' => auth()->id(),
        ]);
        DB::table('debts')->where('id', $debtId)->update([
            'paid_amount' => $paidAmount + $amount,
            'status' => $row->remaining - $amount <= 0 ? 'paid' : 'partial',
            'last_payment_amount' => $amount,
            'last_payment_at' => now(),
            'updated_at' => now(),
        ]);
        //Tạo thông báo
        $amount = number_format($amount). "đ";

        TelegramNotification::send("Thu nợ:\n- {$amount}\nKhách: {$customerName}\nCửa hàng: ". $this->resolveStoreName($storeId));

        $noti = Notification::create([
            'store_id'   => $storeId,
            'created_by' => $userId,
            'type'       => 'log',
            'title'      => 'Thu nợ',
            'body'       => "Đã thu nợ {$amount} từ khách {$customerName}",
            'ref_type'   => 'debt',
            'ref_id'     => $debtId,
            'priority'   => 'normal',
        ]);

        // Đính kèm recipients: toàn bộ user trong store
        $uids = DB::table('user_in_store')->where('store_id', $storeId)->pluck('user_id')->all();
        DB::table('notification_recipients')->insert(array_map(fn($uid)=>[
            'notification_id' => $noti->id, // nếu cần id thông báo vừa tạo thì lấy từ $noti->id
            'user_id' => $uid,
            'created_at' => now(),
            'updated_at' => now(),
        ], $uids));

        return response()->json(['message' => 'Đã ghi nhận thanh toán']);
    }

    public function settleCustomer(Request $r, int $customerId)
    {
        $storeId   = $this->resolveStoreId($r);
        $userId  = $r->user()->id;
        $principal = $this->debtPrincipalColumn();

        // Khách phải thuộc store hiện tại
        $valid = DB::table('customers')
            ->where('id', $customerId)
            ->where('store_id', $storeId)
            ->exists();
        if (!$valid) {
            return response()->json(['message' => 'Khách không thuộc cửa hàng'], 403);
        }

        //Lấy tên khách ghi Log
        $customerName = DB::table('customers')->where('id', $customerId)->value('name');

        // Tính remaining cho từng debt (dùng subquery để gọn và tránh ONLY_FULL_GROUP_BY)
        $perDebt = DB::table('debts as d')
            ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
            ->selectRaw(
                "d.id,
                d.paid_amount,
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

        DB::transaction(function () use ($openDebts, $userId, $storeId, $customerName) {
            foreach ($openDebts as $d) {
                DB::table('debt_payments')->insert([
                    'debt_id'    => $d->id,
                    'amount'     => (int) $d->remaining,
                    'note'       => 'Tất toán tự động',
                    'paid_at'    => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'created_by' => auth()->id()
                ]);

                DB::table('debts')->where('id', $d->id)->update([
                    'paid_amount' => $d->paid_amount + $d->remaining,
                    'status' => 'paid',
                    'last_payment_amount' => $d->remaining,
                    'last_payment_at' => now(),
                    'updated_at' => now(),
                ]);

                //Tạo thông báo
                $amount = number_format((int) ($d->remaining ?? 0)). "đ";

                TelegramNotification::send("Tất toán công nợ:\n- {$amount}\nKhách: {$customerName}\nCửa hàng: ". $this->resolveStoreName($storeId));

                $noti = Notification::create([
                    'store_id'   => $storeId,
                    'created_by' => $userId,
                    'type'       => 'log',
                    'title'      => 'Thu nợ',
                    'body'       => "Tất toán công nợ {$amount} từ khách {$customerName}",
                    'ref_type'   => 'debt',
                    'ref_id'     => $d->id,
                    'priority'   => 'normal',
                ]);

                // Đính kèm recipients: toàn bộ user trong store
                $uids = DB::table('user_in_store')->where('store_id', $storeId)->pluck('user_id')->all();
                DB::table('notification_recipients')->insert(array_map(fn($uid)=>[
                    'notification_id' => $noti->id, // nếu cần id thông báo vừa tạo thì lấy từ $noti->id
                    'user_id' => $uid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $uids));
            }
        });
        

        return response()->json(['message' => 'Đã tất toán toàn bộ công nợ của khách']);
    }

    public function paymentsByDebt(Request $r, int $debt)
    {
        $storeId = $this->resolveStoreId($r);

        // ✅ Kiểm tra debt thuộc customer nào và customer thuộc store hiện tại
        $debtRow = DB::table('debts as d')
            ->join('customers as c', 'c.id', '=', 'd.customer_id')
            ->where('d.id', $debt)
            ->where('c.store_id', $storeId)
            ->select('d.id')
            ->first();

        if (!$debtRow) {
            return response()->json(['message' => 'Khoản nợ không thuộc cửa hàng'], 403);
        }

        $items = DB::table('debt_payments as dp')
            ->leftJoin('users as u', 'u.id', '=', 'dp.user_id')
            ->selectRaw('
                dp.id,
                dp.amount,
                dp.method,
                dp.note,
                dp.created_at,
                u.name as user_name
            ')
            ->where('dp.debt_id', $debt)
            ->orderBy('dp.created_at', 'asc')
            ->get()
            ->map(fn($p) => [
                'id'         => (int) $p->id,
                'amount'     => (float) $p->amount,
                'method'     => $p->method,
                'note'       => $p->note,
                'created_at' => (string) $p->created_at,
                'user_name'  => $p->user_name,
            ])->values();

        return response()->json($items);
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
        $mobileCol = Schema::hasColumn('debts', 'mobileout_id') ? 'mobileout_id' : null;
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
