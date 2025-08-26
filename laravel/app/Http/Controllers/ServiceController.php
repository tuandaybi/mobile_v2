<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Requests\{ServiceStoreRequest, ServiceUpdateRequest};
use App\Http\Resources\ServiceResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Customer;
use App\Models\Debt;
use Carbon\Carbon;


class ServiceController extends Controller
{
    use ResolvesStore;

    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        $q = Service::with(['customer:id,name,phone','user:id,name'])
            ->where('store_id', $storeId);

        if ($s = trim((string)$r->input('q'))) {
            $q->where(fn($w)=>$w->where('name','like',"%{$s}%")->orWhere('note','like',"%{$s}%"));
        }
        if ($r->filled('customer_id')) $q->where('customer_id',$r->input('customer_id'));
        if ($f = $r->input('date_from')) $q->whereDate('created_at','>=',$f);
        if ($t = $r->input('date_to'))   $q->whereDate('created_at','<=',$t);

        $sortable = ['id','name','price','created_at'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 15), 200));
        return ServiceResource::collection($q->paginate($perPage));
    }

    public function store(ServiceStoreRequest $r)
    {
        $userId  = $r->user()->id;
        $storeId = $this->resolveStoreId($r);
        $data    = $r->validated();

        // Chuẩn hoá tên dịch vụ: nhận name hoặc service_name
        $data['name'] = $data['name'] ?? ($data['service_name'] ?? null);
        if (empty($data['name'])) {
            return response()->json(['message' => 'Thiếu tên dịch vụ'], 422);
        }

        // === Tạo customer nếu thiếu customer_id (theo đúng pattern MobileOut) ===
        if (empty($data['customer_id'])) {
            $name  = trim($data['customer_name'] ?? '');
            $phone = $data['phone_number'] ?? null;

            if ($name === '')  return response()->json(['message' => 'Thiếu tên khách hàng'], 422);
            if ($phone === '' || $phone === null) return response()->json(['message' => 'Thiếu số điện thoại'], 422);

            $customerTable = (new Customer)->getTable(); // tránh hard-code

            $attrs = ['store_id' => (int)$storeId, 'name' => $name];
            if ($phone) {
                if (Schema::hasColumn($customerTable, 'phone')) {
                    $attrs['phone'] = $phone;
                } elseif (Schema::hasColumn($customerTable, 'phone_number')) {
                    $attrs['phone_number'] = $phone;
                }
            }

            $customer = Customer::where('store_id', $storeId)->where('name', $name)->first();
            if (!$customer) {
                $customer = (new Customer())->forceFill($attrs);
                $customer->save();
            } elseif ($phone) {
                $needSave = false;
                if (Schema::hasColumn($customerTable, 'phone_number') && empty($customer->phone_number)) {
                    $customer->phone_number = $phone; $needSave = true;
                } elseif (Schema::hasColumn($customerTable, 'phone') && empty($customer->phone)) {
                    $customer->phone = $phone; $needSave = true;
                }
                if ($needSave) $customer->save();
            }

            $data['customer_id'] = $customer->id;
        }

        // Bỏ field phụ không thuộc bảng services
        unset($data['customer_name'], $data['phone_number'], $data['service_name']);

        // Chuẩn hoá số
        foreach (['service_price', 'expense', 'warranty', 'debt'] as $numField) {
            if (array_key_exists($numField, $data) && $data[$numField] !== null) {
                $data[$numField] = (float)str_replace(',', '', (string)$data[$numField]);
            }
        }

        // Không cho client set store_id
        unset($data['store_id']);

        // Gán store_id & user_id
        $data['store_id'] = $storeId;
        $data['user_id']  = $data['user_id'] ?? $userId;

        // ====== Transaction: tạo service + tạo Debt nếu có nợ (KHÔNG cộng dồn customers.debt) ======
        [$service, $createdDebt] = DB::transaction(function () use ($data) {

            // 1) Tạo service
            $service = Service::create($data);

            // 2) Nếu có nợ => tạo bản ghi Debt (không đụng customers.debt)
            $createdDebt = null;
            $debtToAdd   = (float)($data['debt'] ?? 0);
            if ($debtToAdd > 0 && !empty($data['customer_id'])) {
                $serviceDate = !empty($data['service_date'])
                    ? Carbon::parse($data['service_date'])->toDateString()
                    : now()->toDateString();

                $dueDate = !empty($data['due_date'])
                    ? Carbon::parse($data['due_date'])->toDateString()
                    : null;

                $createdDebt = Debt::create([
                    'mobileout_id'        => null,
                    'service_id'          => $service->id,
                    'customer_id'         => $data['customer_id'],
                    'debt'                => $debtToAdd,
                    'paid_amount'         => 0,
                    'last_payment_amount' => null,
                    'last_payment_at'     => null,
                    'status'              => 'pending',
                    'date'                => $serviceDate,
                    'due_date'            => $dueDate,
                    'note'                => 'Nợ phát sinh từ dịch vụ #'.$service->id,
                ]);
            }

            return [$service, $createdDebt];
        });

        // Trả resource + info về debt vừa tạo (nếu có)
        return (new ServiceResource(
            $service->load(['user:id,name', 'customer:id,name'])
        ))
        ->additional([
            'debt' => $createdDebt ? [
                'id'                  => $createdDebt->id,
                'debt'                => (float)$createdDebt->debt,
                'status'              => $createdDebt->status,
                'remaining'           => (float)$createdDebt->remaining, // accessor nếu có
                'last_payment_amount' => $createdDebt->last_payment_amount ? (float)$createdDebt->last_payment_amount : null,
                'last_payment_at'     => optional($createdDebt->last_payment_at)->toIso8601String(),
            ] : null,
            'message' => 'Tạo dịch vụ thành công'.($createdDebt ? ' (đã tạo công nợ)' : ''),
        ])
        ->response()
        ->setStatusCode(201);
    }

    public function show($id)
    {
        $s = Service::with('customer')->findOrFail($id);

        // tuỳ schema thực tế, map về payload FE đang đọc
        return response()->json([
            'id'            => $s->id,
            'service_name'  => $s->service_name ?? $s->name,
            'customer_name' => optional($s->customer)->name,
            'service_date'  => $s->service_date ?? $s->created_at,
            'service_price' => (int) ($s->service_price ?? $s->price ?? $s->amount),
            'expense'       => (int) ($s->expense ?? 0),
            'paid'          => (int) ($s->paid ?? $s->payment_total ?? 0),
            'debt'          => max(0, (int) ($s->service_price ?? $s->price ?? $s->amount) - (int) ($s->paid ?? 0)),
            'warranty'      => (int) ($s->warranty ?? 0),
            'note'          => $s->note,
        ]);
    }

    public function update(ServiceUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);

        // Tìm service đúng cửa hàng
        $svc = Service::where('store_id', $storeId)->findOrFail($id);

        $data = $r->validated();

        // Không cho client sửa các field sau ở màn edit
        unset($data['debt'], $data['store_id']);

        // Chuẩn hoá số: tuỳ DB của bạn là int/decimal mà cast cho hợp
        foreach (['service_price', 'expense', 'warranty'] as $numField) {
            if (array_key_exists($numField, $data) && $data[$numField] !== null && $data[$numField] !== '') {
                // giá & expense có thể là decimal, đổi sang số sạch (loại dấu phẩy/thousand)
                $clean = preg_replace('/[^\d.-]/', '', (string)$data[$numField]);
                // warranty thường là ngày (int)
                $data[$numField] = $numField === 'warranty'
                    ? (int) $clean
                    : (float) $clean;
            }
        }

        // Chuẩn hoá ngày
        if (array_key_exists('service_date', $data) && $data['service_date']) {
            try {
                $data['service_date'] = Carbon::parse($data['service_date'])->toDateString();
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Ngày dịch vụ không hợp lệ'], 422);
            }
        }

        DB::transaction(function () use ($r, $storeId, $svc, &$data) {
            // 1) Nếu client gửi customer_id → validate thuộc đúng store
            if (array_key_exists('customer_id', $data) && $data['customer_id']) {
                $ok = Customer::where('store_id', $storeId)
                    ->where('id', (int)$data['customer_id'])
                    ->exists();

                if (!$ok) {
                    abort(response()->json(['message' => 'Khách hàng không thuộc cửa hàng này'], 422));
                }
            } else {
                // 2) Không có customer_id → thử tìm/tao theo name/phone
                $name  = trim((string) $r->input('customer_name', ''));
                $phone = trim((string) $r->input('phone_number', ''));

                if ($name !== '' || $phone !== '') {
                    $query = Customer::where('store_id', $storeId);
                    if ($phone !== '') {
                        $query->where('phone', $phone);
                    } elseif ($name !== '') {
                        $query->where('name', $name);
                    }
                    $customer = $query->first();

                    if (!$customer) {
                        $customer = Customer::create([
                            'store_id' => $storeId,
                            'name'     => $name !== '' ? $name : 'Khách lẻ',
                            'phone'    => $phone !== '' ? $phone : null,
                        ]);
                    }

                    $data['customer_id'] = $customer->id;
                }
            }

            // 3) Dữ liệu chỉ phục vụ nhập liệu → không lưu
            unset($data['customer_name'], $data['phone_number']);

            // (tuỳ) lưu user thực hiện sửa
            if ($r->user()) {
                $data['user_id'] = $r->user()->id;
            }

            // 4) Cập nhật
            $svc->update($data);
        });

        return new ServiceResource(
            $svc->fresh()->load([
                'customer:id,name,phone',
                'user:id,name',
            ])
        );
    }

    public function destroy(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $svc = Service::where('store_id',$storeId)->findOrFail($id);
        $svc->delete();
        return response()->json(['message'=>'Đã xoá.']);
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
