<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\Customer;
use App\Models\MobileIn;
use App\Models\MobileOut;
use App\Models\Debt;
use Illuminate\Http\Request;
use App\Http\Requests\{MobileOutStoreRequest, MobileOutUpdateRequest};
use App\Http\Resources\MobileOutResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileOutController extends Controller
{
    use ResolvesStore;

    public function index(Request $r)
    {
        $q = MobileOut::with([
            'mobileIn:id,store_id,device_id,color_id,storage_id,imei,is_sold',
            'mobileIn.device:id,name',
            'mobileIn.color:id,vi_name',
            'mobileIn.storage:id,name,size_gb',
            'user:id,name',
            'customer:id,name,phone'
        ]);

        if ($s = trim((string)$r->input('q'))) {
            $q->whereHas('mobileIn', fn($w)=>$w->where('imei','like',"%{$s}%"));
        }
        if ($r->filled('store_id')) {
            $q->whereHas('mobileIn', fn($w)=>$w->where('store_id', $r->input('store_id')));
        }
        if ($f = $r->input('date_from')) $q->whereDate('export_date','>=',$f);
        if ($t = $r->input('date_to'))   $q->whereDate('export_date','<=',$t);

        $sortable = ['id','export_date','export_price'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 15), 200));
        return MobileOutResource::collection($q->paginate($perPage));
    }

    public function store(MobileOutStoreRequest $r)
    {
        $userId  = $r->user()->id;
        $storeId = $this->resolveStoreId($r);
        $data    = $r->validated();

        $mob = MobileIn::findOrFail($data['mobile_in_id']);

        if ((int)$mob->store_id !== (int)$storeId) {
            return response()->json(['message'=>'Máy không thuộc cửa hàng này'], 403);
        }
        if ($mob->is_sold) {
            return response()->json(['message'=>'Máy đã bán trước đó'], 409);
        }

        // Tạo customer nếu chưa có
        if (empty($data['customer_id'])) {
            $name  = trim($data['customer_name'] ?? '');
            $phone = $data['phone_number'] ?? null;

            if ($name === '')  return response()->json(['message' => 'Thiếu tên khách hàng'], 422);
            if ($phone === '') return response()->json(['message' => 'Thiếu số điện thoại'], 422);

            $attrs = ['store_id' => (int)$storeId, 'name' => $name];
            if ($phone) {
                if (Schema::hasColumn('customers', 'phone')) {
                    $attrs['phone'] = $phone;
                } elseif (Schema::hasColumn('customers', 'phone_number')) {
                    $attrs['phone_number'] = $phone;
                }
            }

            $customer = Customer::where('store_id', $storeId)->where('name', $name)->first();
            if (!$customer) {
                $customer = (new Customer())->forceFill($attrs);
                $customer->save();
            } elseif ($phone) {
                $needSave = false;
                if (Schema::hasColumn('customers', 'phone_number') && empty($customer->phone_number)) {
                    $customer->phone_number = $phone; $needSave = true;
                } elseif (Schema::hasColumn('customers', 'phone') && empty($customer->phone)) {
                    $customer->phone = $phone; $needSave = true;
                }
                if ($needSave) $customer->save();
            }

            $data['customer_id'] = $customer->id;
        }

        unset($data['customer_name'], $data['phone_number']);

        // ====== Transaction: tạo bán + cập nhật is_sold + (tạo Debt nếu có nợ) + cộng dồn nợ vào customers ======
        [$sale, $createdDebt] = DB::transaction(function () use ($data, $userId, $mob, $storeId) {
            // 1) Tạo phiếu bán
            $sale = MobileOut::create($data + ['user_id' => $userId]);

            // 2) Đánh dấu đã bán
            $mob->update(['is_sold' => 1]);

            // 3) Nếu có nợ => tạo bản ghi Debt
            $createdDebt = null;
            $debtToAdd = (float)($data['debt'] ?? 0);
            if ($debtToAdd > 0 && !empty($data['customer_id'])) {
                $saleDate = !empty($data['sold_at'])
                    ? Carbon::parse($data['sold_at'])->toDateString()
                    : now()->toDateString();

                $dueDate  = !empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null;

                $createdDebt = Debt::create([
                    'mobileout_id'        => $sale->id,
                    'service_id'          => null,
                    'customer_id'         => $data['customer_id'],
                    'debt'                => $debtToAdd,
                    'paid_amount'         => 0,
                    'last_payment_amount' => null,
                    'last_payment_at'     => null,
                    'status'              => 'pending',
                    'date'                => $saleDate,
                    'due_date'            => $dueDate,
                    'note'                => 'Nợ phát sinh từ bán máy #'.$sale->id,
                ]);
            }

            return [$sale, $createdDebt];
        });

        // Trả về: hoá đơn bán + (tuỳ chọn) debt vừa tạo
        return (new MobileOutResource(
            $sale->load(['mobileIn.device','user','customer'])
        ))
        ->additional([
            'debt' => $createdDebt ? [
                'id'                 => $createdDebt->id,
                'debt'               => (float)$createdDebt->debt,
                'status'             => $createdDebt->status,
                'remaining'          => (float)$createdDebt->remaining,
                'last_payment_amount'=> $createdDebt->last_payment_amount ? (float)$createdDebt->last_payment_amount : null,
                'last_payment_at'    => optional($createdDebt->last_payment_at)->toIso8601String(),
            ] : null,
            'message' => 'Tạo phiếu bán thành công'.($createdDebt ? ' (đã tạo công nợ)' : ''),
        ])
        ->response()
        ->setStatusCode(201);
    }

    public function show($id)
    {
        // 1) Lấy đơn + quan hệ cần thiết để hiển thị
        $sale = MobileOut::with([
            'mobileIn.device',
            'mobileIn.color',
            'mobileIn.storage',
            'customer',
            'user',
        ])->findOrFail($id);

        // 2) Giá bán/tổng tiền NẰM Ở MOBILE_OUT
        // Ưu tiên cột 'price', sau đó là các cột tổng phổ biến khác nếu schema khác tên
        $salePrice = (int) (
            $sale->export_price
            ?? $sale->total
            ?? $sale->amount
            ?? $sale->subtotal
            ?? 0
        );

        // 3) Đã trả: lấy từ hệ công nợ (debts -> debt_payments) nếu có liên kết
        $paidFromDebts = 0;
        if (Schema::hasTable('debts') && Schema::hasTable('debt_payments')) {
            // tự dò cột liên kết đơn bán: mobile_out_id > sale_id (tuỳ DB thật)
            $refCol = null;
            if (Schema::hasColumn('debts', 'mobile_out_id')) {
                $refCol = 'mobile_out_id';
            } elseif (Schema::hasColumn('debts', 'sale_id')) {
                $refCol = 'sale_id';
            }

            if ($refCol) {
                $paidFromDebts = (int) DB::table('debts as d')
                    ->leftJoin('debt_payments as p', 'p.debt_id', '=', 'd.id')
                    ->where("d.$refCol", $sale->id)
                    ->selectRaw('COALESCE(SUM(p.amount),0) as paid')
                    ->value('paid');
            }
        }

        // fallback nếu chưa dùng hệ công nợ
        $paid = $paidFromDebts > 0
            ? $paidFromDebts
            : (int) ($sale->paid ?? $sale->payment_total ?? 0);

        $debt = max(0, $salePrice - $paid);

        // 4) Items: hiển thị thông tin máy từ mobileIn, NHƯNG price lấy từ MOBILE_OUT
        $items = [];
        if ($sale->mobileIn) {
            $mi = $sale->mobileIn; // belongsTo một máy
            $items[] = [
                'imei'        => $mi->imei ?? $mi->mb_imei ?? '',
                'device_name' => optional($mi->device)->name ?? ($mi->device_name ?? ''),
                'color'       => optional($mi->color)->vi_name
                                ?? ($mi->color->name ?? $mi->color ?? ''),
                'storage'     => optional($mi->storage)->size_gb
                                    ? (optional($mi->storage)->size_gb . ' GB')
                                    : ($mi->storage->name ?? $mi->storage ?? ''),
                // GIÁ lấy từ đơn bán (mobile_out), không lấy từ mobile_in
                'price'       => $salePrice,
            ];
        }
        // Nếu sau này có hasMany items, bạn map từng item và chia giá theo logic của bạn.

        // 5) Thêm thông tin chung
        $code         = $sale->code ?? $sale->order_code ?? ('MO-' . $sale->id);
        $date         = $sale->sale_date ?? $sale->created_at;
        $customerName = optional($sale->customer)->name ?? $sale->customer_name ?? null;

        // 6) Trả về đúng format mà FE (normalizeMobileOut) đang đọc
        return response()->json([
            'id'            => (int) $sale->id,
            'code'          => $code,
            'customer_name' => $customerName,
            'items'         => $items,           // mỗi item.price = giá của mobile_out
            'subtotal'      => $salePrice,       // FE sẽ đọc subtotal/total/amount — ở đây trả về subtotal
            'paid'          => $paid,
            'debt'          => $debt,
            'date'          => $date,
            'note'          => $sale->note ?? null,
            'user_name'     => optional($sale->user)->name ?? null,
        ]);
    }

    public function update(MobileOutUpdateRequest $r, $id)
    {
        $sale = MobileOut::findOrFail($id);
        $sale->update($r->validated());
        return new MobileOutResource($sale->load(['mobileIn.device','user','customer']));
    }

    public function destroy($id)
    {
        $sale = MobileOut::findOrFail($id);
        $mobId = $sale->mobile_in_id;
        $sale->delete();
        $mob = MobileIn::find($mobId);
        if ($mob) { $mob->is_sold = 0; $mob->save(); }
        return response()->json(['message'=>'Đã xoá hoá đơn bán và hoàn trạng thái máy về chưa bán.']);
    }

    private function resolveStoreId(Request $r): int
    {
        // Ưu tiên lấy trực tiếp nếu FE có gửi (route param / input / header)
        if ($id = $r->route('store_id') ?? $r->input('store_id') ?? $r->header('X-Store-Id')) {
            return (int) $id;
        }

        $userId = $r->user()->id;

        // 1) Từ bảng pivot user_in_store (cột đang dùng: id_user, id_store)
        $id = DB::table('user_in_store')->where('user_id', $userId)->value('store_id');
        if ($id) return (int) $id;

        // 2) Fallback: cột store_id trên users (nếu có)
        $id = DB::table('users')->where('id', $userId)->value('store_id');
        if ($id) return (int) $id;

        abort(403, 'Không xác định được cửa hàng cho tài khoản này');
    }
}
