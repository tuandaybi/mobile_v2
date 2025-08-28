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
        $q     = trim((string) $r->input('q', ''));
        $limit = max(1, (int) $r->input('limit', 50));
        $storeId = $this->resolveStoreId($r);

        $rows = MobileOut::query()
            ->with(['mobileIn.device', 'mobileIn.color', 'mobileIn.storage', 'customer'])
            // lọc theo store_id trong bảng mobile_in
            ->whereHas('mobileIn', fn($qq) => $qq->where('store_id', $storeId))
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(function ($w) use ($like) {
                    $w->whereHas('mobileIn.device', fn($qq) => $qq->where('name', 'like', $like))
                    ->orWhereHas('customer', fn($qq) => $qq->where('name', 'like', $like)
                                                            ->orWhere('phone', 'like', $like))
                    ->orWhere('code', 'like', $like)
                    ->orWhere('note', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        // Chuẩn hoá trả về theo FE
        $data = $rows->map(function (MobileOut $m) {
            $mi = $m->mobileIn;
            $price = (int) ($m->export_price ?? $m->total ?? $m->amount ?? 0);
            $warranty = (int) ($m->warranty ?? 0);
            return [
                'id'             => (int) $m->id,
                'device_name'    => optional($mi?->device)->name ?? '',
                'country_code'   => $mi->country_code ?? optional($mi?->device)->country_code,
                'storage_gb'     => (int) ($mi?->storage?->size_gb ?? 0),
                'color_name'     => $mi?->color?->vi_name ?? $mi?->color?->name,
                'imei'           => $mi->imei ?? $mi->imei ?? '',
                'customer_name'  => optional($m->customer)->name,
                'customer_phone' => optional($m->customer)->phone,
                'sale_date'      => $m->sale_date ?? $m->created_at,
                'price'          => $price,
                'note'           => $m->note,
                'warranty'      => $warranty,
                // optional raw nếu muốn FE dùng lại:
                // 'mobile_in_id' => $m->mobile_in_id,
                // 'code'         => $m->code,
            ];
        });

        return response()->json($data);
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
        $exspense     = (int) ($sale->expense ?? 0);
        $warranty     = (int) ($sale->warranty ?? 0);
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
            'expense'      => $exspense,
            'date'          => $date,
            'warranty'     => $warranty,
            'note'          => $sale->note ?? null,
            'user_name'     => optional($sale->user)->name ?? null,
        ]);
    }

    public function update(MobileOutUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);

        // Phiếu bán thuộc đúng store
        $sale = MobileOut::with([
            'mobileIn.device',
            'mobileIn.color',
            'mobileIn.storage',
            'customer',
            'user',
        ])->findOrFail($id);


        $data = $r->validated();

        // ===== CHUẨN HOÁ SỐ: chỉ export_price =====
        if (array_key_exists('export_price', $data)) {
            $v = $data['export_price'];
            if ($v !== null && $v !== '') {
                $clean = preg_replace('/[^\d.-]/', '', (string)$v);
                if ($clean === '' || !is_numeric($clean)) {
                    return response()->json(['message' => 'Giá xuất không hợp lệ'], 422);
                }
                $data['export_price'] = (float) $clean; // đổi (int) nếu cột dùng INT VND
            } else {
                $data['export_price'] = null; // tuỳ schema
            }
        }

        // ===== CHUẨN HOÁ export_date =====
        if (array_key_exists('export_date', $data) && $data['export_date'] !== null && $data['export_date'] !== '') {
            try {
                $v = $data['export_date'];

                if ($v instanceof \DateTimeInterface) {
                    $data['export_date'] = \Carbon\Carbon::instance($v)->toDateString();
                } elseif (is_int($v) || (is_string($v) && ctype_digit(trim($v)))) {
                    $data['export_date'] = \Carbon\Carbon::createFromTimestamp((int)$v, config('app.timezone'))->toDateString();
                } else {
                    $s = trim((string)$v);
                    try {
                        $dt = \Carbon\Carbon::createFromFormat('Y-m-d', $s, config('app.timezone'));
                        if ($dt && $dt->format('Y-m-d') === $s) {
                            $data['export_date'] = $dt->toDateString();
                        } else {
                            $data['export_date'] = \Carbon\Carbon::parse($s, config('app.timezone'))->toDateString();
                        }
                    } catch (\Throwable $e) {
                        $data['export_date'] = \Carbon\Carbon::parse($s, config('app.timezone'))->toDateString();
                    }
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Ngày xuất không hợp lệ',
                    'input'   => ['export_date' => $data['export_date']],
                ], 422);
            }
        }

        // ===== Chuẩn hoá debt (nếu có) =====
        $incomingDebt = null;
        if ($r->has('debt')) {
            $val = trim((string)$r->input('debt', ''));
            $incomingDebt = $val === '' ? 0.0 : (float)preg_replace('/[^\d.-]/', '', $val);
            if ($incomingDebt < 0) $incomingDebt = 0.0;
        }

        \DB::transaction(function () use ($r, $storeId, $sale, &$data, $incomingDebt) {
            // ===== CUSTOMER =====
            if ($r->filled('customer_id')) {
                $customer = Customer::where('store_id', $storeId)
                    ->where('id', (int)$r->input('customer_id'))
                    ->first();

                if (!$customer) {
                    abort(response()->json(['message' => 'Khách hàng không thuộc cửa hàng này'], 422));
                }

                $name  = trim((string)$r->input('customer_name', ''));
                $phone = trim((string)$r->input('phone_number', ''));
                $patch = [];
                if ($name !== ''  && $name  !== $customer->name)           $patch['name']  = $name;
                if ($phone !== '' && $phone !== (string)$customer->phone)  $patch['phone'] = $phone;
                if ($patch) $customer->update($patch);

                $data['customer_id'] = $customer->id;
            } else {
                $name  = trim((string)$r->input('customer_name', ''));
                $phone = trim((string)$r->input('phone_number', ''));

                if ($phone === '') {
                    abort(response()->json(['message' => 'Khách hàng không có số điện thoại'], 422));
                }

                $customer = Customer::where('store_id', $storeId)
                    ->where('phone', $phone)
                    ->first();

                if ($customer) {
                    // Dùng lại; cập nhật name nếu khác
                    if ($name !== '' && $name !== $customer->name) {
                        $customer->update(['name' => $name]);
                    }
                } else {
                    // Không tìm thấy theo phone -> tạo mới
                    $customer = Customer::create([
                        'store_id' => $storeId,
                        'name'     => $name !== '' ? $name : 'Khách lẻ',
                        'phone'    => $phone,
                    ]);
                }

                $data['customer_id'] = $customer->id;
            }

            // Ghi nhận user sửa
            if ($r->user()) $data['user_id'] = $r->user()->id;

            // Không cho sửa các field nhạy cảm từ client
            unset($data['store_id'], $data['mobile_in_id']);

            // ===== UPDATE MOBILE OUT =====
            $sale->update($data);

            // ===== DEBT (mỗi mobile_out_id tối đa 1 khoản nợ) =====
            if ($incomingDebt !== null) {
                // Xoá nợ cũ theo mobile_out_id
                Debt::where('mobileout_id', $sale->id)->delete();

                if ($incomingDebt > 0) {
                    $debtDate = $r->filled('debt_date')
                        ? \Carbon\Carbon::parse($r->input('debt_date'))->toDateString()
                        : \Carbon\Carbon::now()->toDateString(); // nếu cột 'date' là DATE

                    Debt::create([
                        'customer_id'   => $data['customer_id'] ?? $sale->customer_id,
                        'mobileout_id' => $sale->id,
                        'debt'          => $incomingDebt,
                        'date'          => $debtDate,
                        'note'          => $r->input('debt_note') ?: ('Nợ phát sinh từ bán máy #' . $sale->id),
                    ]);
                }
            }
        });

        return new MobileOutResource(
            $sale->fresh()->load([
                'mobileIn.device',
                'mobileIn.color',
                'mobileIn.storage',
                'user:id,name',
                'customer:id,name,phone',
                // 'debt' // nếu có quan hệ hasOne trên MobileOut
            ])
        );
    }

    public function destroy($id)
    {
        $out = MobileOut::with('mobileIn')->findOrFail($id);

        DB::transaction(function () use ($out) {
            // 1) Xoá công nợ liên quan nếu có
            if (Schema::hasTable('debts')) {
                // Tự dò cột liên kết đến mobile_out: ưu tiên mobile_out_id > sale_id
                $refCol = null;
                if (Schema::hasColumn('debts', 'mobile_out_id')) {
                    $refCol = 'mobile_out_id';
                } elseif (Schema::hasColumn('debts', 'sale_id')) {
                    $refCol = 'sale_id';
                }

                if ($refCol) {
                    // Lấy tất cả debts liên quan
                    $debtIds = DB::table('debts')->where($refCol, $out->id)->pluck('id');
                    if ($debtIds->isNotEmpty()) {
                        // Xoá payments trước, rồi delete debts
                        if (Schema::hasTable('debt_payments')) {
                            DB::table('debt_payments')->whereIn('debt_id', $debtIds)->delete();
                        }
                        DB::table('debts')->whereIn('id', $debtIds)->delete();
                    }
                }
            }

            // 2) Khôi phục trạng thái Mobile_In (nếu có)
            if ($out->mobileIn) {
                $miId = $out->mobileIn->id;
                $imei = $out->mobileIn->imei ?? $out->mobileIn->mb_imei ?? null;

                $update = ['is_sold' => 0];

                DB::table('mobile_in')->where('id', $miId)->update($update);
            }

            // 3) Xoá đơn bán
            $out->delete();
        });

        return response()->json(['message' => 'Đã xoá đơn bán và công nợ liên quan.']);
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
