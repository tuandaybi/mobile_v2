<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Notifications\TelegramNotification;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Requests\{ServiceStoreRequest, ServiceUpdateRequest};
use App\Http\Controllers\Traits\IndexHelpers;
use App\Http\Resources\ServiceResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Notification;
use Carbon\Carbon;



class ServiceController extends Controller
{
    use ResolvesStore;
    use IndexHelpers;

    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        // Join để sort/search theo khách hàng
        $q = Service::query()
            ->with(['customer:id,name,phone', 'user:id,name'])
            ->leftJoin('customers as c', 'c.id', '=', 'services.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'services.user_id')
            ->where('services.store_id', $storeId)
            ->select('services.*');


        $this->applySearch($q, $r->input('q'), [
            'services.name',   
            'services.note',
            'c.name',
            'c.phone',
            'u.name',
        ]);

        // Filter thời gian
        if ($f = $r->input('date_from')) $q->whereDate('services.created_at', '>=', $f);
        if ($t = $r->input('date_to'))   $q->whereDate('services.created_at', '<=', $t);
        if ($r->filled('customer_id'))   $q->where('services.customer_id', $r->input('customer_id'));

        // Whitelist sort map: FE key -> DB column
        $sortMap = [
            'id'            => 'services.id',
            'name'          => 'services.name',
            'price'         => 'services.price',
            'cost'          => 'services.expense',
            'date'          => 'services.created_at',
            'customer_name' => 'c.name',
            'phone'         => 'c.phone',
            'user_name'     => 'u.name',
        ];
        $this->applySort($q, $r, $sortMap, 'date', 'desc');

        $paginator = $q->paginate($this->perPage($r))->appends($r->query());

        return ServiceResource::collection($paginator);
    }

    public function store(ServiceStoreRequest $r)
    {
        $userId  = $r->user()->id;
        $storeId = $this->resolveStoreId($r);
        $data    = $r->validated();

        // Chuẩn hoá tên dịch vụ: nhận name hoặc service_name
        $data['name'] = $data['name'] ?? null;
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
                }
            }

            $customer = Customer::where('store_id', $storeId)->where('phone', $phone)->first();
            if (!$customer) {
                $customer = (new Customer())->forceFill($attrs);
                $customer->save();
            } else if ($phone) {
                $needSave = false;
                if (Schema::hasColumn($customerTable, 'phone') && empty($customer->phone)) {
                    $customer->phone = $phone; $needSave = true;
                }
                if ($needSave) $customer->save();
            }

            $data['customer_id'] = $customer->id;
        }

        // Bỏ field phụ không thuộc bảng services
        unset($data['customer_name'], $data['phone_number'], $data['service_name']);

        // Chuẩn hoá số
        foreach (['price', 'expense', 'warranty', 'debt'] as $numField) {
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
                    'customer_id'         => $service->customer_id,
                    'user_id'             => $service->user_id,
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

            //Tạo thông báo
            $serviceName = $service->name ?? '';
            $customerName = optional($service->customer)->name ?? '';
            $salePrice = number_format((int) ($service->price ?? 0)). "đ";
            $notiDebt = $debtToAdd > 0 ? " (nợ lại ". number_format((float)$debtToAdd)."đ)" : '';

            TelegramNotification::send("Tạo dịch vụ:\n- {$serviceName}\nKhách: {$customerName}\nGiá: {$salePrice}{$notiDebt}\nCửa hàng: ". $this->resolveStoreName($service->store_id));

            $noti = Notification::create([
                'store_id'   => $service->store_id,
                'created_by' => $service->user_id,
                'type'       => 'log',
                'title'      => 'Dịch vụ',
                'body'       => "{$serviceName} cho khách {$customerName} với giá {$salePrice} {$notiDebt}",
                'ref_type'   => 'service',
                'ref_id'     => $service->id,
                'priority'   => 'normal',
            ]);

            // Đính kèm recipients: toàn bộ user trong store
            $uids = DB::table('user_in_store')->where('store_id', $service->store_id)->pluck('user_id')->all();
            DB::table('notification_recipients')->insert(array_map(fn($uid)=>[
                'notification_id' => $noti->id, // nếu cần id thông báo vừa tạo thì lấy từ $noti->id
                'user_id' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $uids));

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
        $s = Service::with(['customer', 'user'])->findOrFail($id);

        // Số nợ thực tế từ bảng debts (cho form sửa "Nợ lại")
        $debtAmount = (float) (Debt::where('service_id', $s->id)->sum('debt') ?? 0);

        // tuỳ schema thực tế, map về payload FE đang đọc
        return response()->json([
            'id'            => $s->id,
            'service_name'  => $s->service_name ?? $s->name,
            'customer_id'   => $s->customer_id,
            'customer_name' => optional($s->customer)->name,
            'phone_number'  => optional($s->customer)->phone,
            'user_name'     => optional($s->user)->name,
            'service_date'  => $s->service_date ?? $s->created_at,
            'service_price' => (int) ($s->service_price ?? $s->price ?? $s->amount),
            'expense'       => (int) ($s->expense ?? 0),
            'paid'          => (int) ($s->paid ?? $s->payment_total ?? 0),
            'debt'          => $debtAmount,
            'warranty'      => (int) ($s->warranty ?? 0),
            'note'          => $s->note,
        ]);
    }

    
    public function update(ServiceUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $userId  = $r->user()->id;

        // Service thuộc đúng store
        $svc = Service::with(['customer', 'user'])->where('store_id', $storeId)->findOrFail($id);

        $data = $r->validated();

        // Chuẩn hoá số
        foreach (['service_price', 'expense', 'warranty'] as $numField) {
            if (array_key_exists($numField, $data) && $data[$numField] !== null && $data[$numField] !== '') {
                $clean = preg_replace('/[^\d.-]/', '', (string)$data[$numField]);
                $data[$numField] = $numField === 'warranty' ? (int)$clean : (float)$clean;
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

        // Chuẩn hoá debt (nếu có)
        $incomingDebt = null;
        if ($r->has('debt')) {
            $val = trim((string)$r->input('debt', ''));
            $incomingDebt = $val === '' ? 0.0 : (float)preg_replace('/[^\d.-]/', '', $val);
            if ($incomingDebt < 0) $incomingDebt = 0.0;
        }

        DB::transaction(function () use ($r, $storeId, $svc, &$data, $incomingDebt, $userId) {
            // ===== CUSTOMER =====

            if ($r->filled('customer_id')) {
                // Có customer_id -> cho phép cập nhật name/phone của chính khách đó
                $customer = Customer::where('store_id', $storeId)
                    ->where('id', (int)$r->input('customer_id'))
                    ->first();

                if (!$customer) {
                    abort(response()->json(['message' => 'Khách hàng không thuộc cửa hàng này'], 422));
                }

                // Cập nhật nếu có thay đổi
                $name  = trim((string)$r->input('customer_name', ''));
                $phone = trim((string)$r->input('phone_number', ''));
                $patch = [];
                if ($name !== '' && $name !== $customer->name) $patch['name'] = $name;
                if ($phone !== '' && $phone !== (string)$customer->phone) $patch['phone'] = $phone;
                if ($patch) $customer->update($patch);

                $data['customer_id'] = $customer->id;
            } else {
                // KHÔNG có customer_id:
                // - Nếu có phone -> tìm đúng phone trong store. Nếu thấy: dùng lại (KHÔNG cập nhật). Không thấy: tạo mới.
                // - Nếu không có phone -> luôn tạo mới (không ghép theo name để tránh ghi đè).
                $name  = trim((string)$r->input('customer_name', ''));
                $phone = trim((string)$r->input('phone_number', ''));

                if ($phone !== '') {
                    $customer = Customer::where('store_id', $storeId)
                        ->where('phone', $phone) // chỉ match CHÍNH XÁC phone
                        ->first();

                    if ($customer) {
                        if ($name !== '' && $name !== $customer->name) {
                            $customer->update(['name' => $name]);
                        }
                        $data['customer_id'] = $customer->id;
                    } else {
                        // Không tìm thấy theo phone -> TẠO MỚI
                        $customer = Customer::create([
                            'store_id' => $storeId,
                            'name'     => $name !== '' ? $name : 'Khách lẻ',
                            'phone'    => $phone,
                        ]);
                        $data['customer_id'] = $customer->id;
                    }
                } else {
                    return response()->json(['message' => 'Chưa nhập sđt khách hàng'], 422);
                }
            }

            // Ghi user sửa
            if ($r->user()) $data['user_id'] = $r->user()->id;

            // Không cho sửa store_id
            unset($data['store_id']);

            // ===== UPDATE SERVICE =====
            $svc->update($data);

            // ===== DEBT (chỉ bảng debts) =====
            if ($incomingDebt !== null) {
                $customerId = $data['customer_id'] ?? $svc->customer_id;

                if ($incomingDebt > 0) {
                    // Chuẩn hoá ngày nợ
                    $debtDate = $r->filled('debt_date')
                        ? Carbon::parse($r->input('debt_date'))->toDateString()
                        : now();
                    $payload = [
                        'customer_id' => $customerId,
                        'service_id'  => $svc->id,
                        'user_id'     => $data['user_id'] ?? null,
                        'debt'        => $incomingDebt,
                        'date'        => $debtDate,
                        'note'        => $r->input('debt_note') ?: ('Nợ phát sinh từ dịch vụ #' . $svc->id),
                    ];

                    $existing = Debt::withTrashed()->where('service_id', $svc->id)->first();
                    if ($existing) {
                        if ($existing->trashed()) $existing->restore();
                        $existing->update($payload);
                    } else {
                        Debt::create($payload);
                    }
                } else {
                    // debt = 0 -> soft-delete TẤT CẢ debts đang active của service này
                    Debt::where('service_id', $svc->id)->delete();
                }
            }
            //Tạo thông báo
            $serviceName = $svc->name ?? '';
            $customerName = optional($svc->customer)->name ?? '';
            $salePrice = number_format((int) ($svc->price ?? 0)). "đ";
            
            TelegramNotification::send("Sửa dịch vụ:\n- {$serviceName}\nKhách: {$customerName}\nGiá: {$salePrice}\nCửa hàng: ". $this->resolveStoreName($svc->store_id));

            $noti = Notification::create([
                'store_id'   => $storeId,
                'created_by' => $userId,
                'type'       => 'log',
                'title'      => 'Sửa dịch vụ',
                'body'       => "Sửa {$serviceName} của khách {$customerName} với giá {$salePrice} người bán #".optional($svc->user)->name,
                'ref_type'   => 'service',
                'ref_id'     => $svc->id,
                'priority'   => 'normal',
            ]);

            // Đính kèm recipients: toàn bộ user trong store
            $uids = DB::table('user_in_store')->where('store_id', $svc->store_id)->pluck('user_id')->all();
            DB::table('notification_recipients')->insert(array_map(fn($uid)=>[
                'notification_id' => $noti->id, // nếu cần id thông báo vừa tạo thì lấy từ $noti->id
                'user_id' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $uids));

        });

        return new ServiceResource(
            $svc->fresh()->load([
                'customer:id,name,phone',
                'user:id,name',
                // 'debt' // nếu có quan hệ hasOne trên Service
            ])
        );
    }

    public function destroy(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $svc = Service::with(['customer', 'user'])->where('store_id',$storeId)->findOrFail($id);
        $userId  = $r->user()->id;
        
        DB::transaction(function () use ($svc, $userId, $storeId) {
            // 1) Xoá công nợ liên quan nếu có
            if (Schema::hasTable('debts')) {
                // Tự dò cột liên kết đến mobile_out: ưu tiên mobileout_id > sale_id
                $refCol = null;
                if (Schema::hasColumn('debts', 'service_id')) {
                    $refCol = 'service_id';
                }

                if ($refCol) {
                    // Lấy tất cả debts liên quan
                    $debtIds = DB::table('debts')->where($refCol, $svc->id)->pluck('id');
                    if ($debtIds->isNotEmpty()) {
                        // Xoá payments trước, rồi delete debts
                        if (Schema::hasTable('debt_payments')) {
                            DB::table('debt_payments')->whereIn('debt_id', $debtIds)->delete();
                        }
                        DB::table('debts')->whereIn('id', $debtIds)->delete();
                    }
                }
            }
            
            //Tạo thông báo
            $serviceName = $svc->name ?? '';
            $customerName = optional($svc->customer)->name ?? '';
            $salePrice = number_format((int) ($svc->price ?? 0)). "đ";

            TelegramNotification::send("Xoá dịch vụ:\n- {$serviceName}\nKhách: {$customerName}\nGiá: {$salePrice} người bán #".optional($svc->user)->name."\nCửa hàng: ". $this->resolveStoreName($svc->store_id));

            $noti = Notification::create([
                'store_id'   => $storeId,
                'created_by' => $userId,
                'type'       => 'log',
                'title'      => 'Xóa dịch vụ',
                'body'       => "Xóa {$serviceName} của khách {$customerName} với giá {$salePrice} người bán #".optional($svc->user)->name,
                'ref_type'   => 'service',
                'ref_id'     => $svc->id,
                'priority'   => 'normal',
            ]);

            // Đính kèm recipients: toàn bộ user trong store
            $uids = DB::table('user_in_store')->where('store_id', $svc->store_id)->pluck('user_id')->all();
            DB::table('notification_recipients')->insert(array_map(fn($uid)=>[
                'notification_id' => $noti->id, // nếu cần id thông báo vừa tạo thì lấy từ $noti->id
                'user_id' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ], $uids));

            $svc->delete();
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
