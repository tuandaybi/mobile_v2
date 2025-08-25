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

        // Chuẩn hoá tên dịch vụ
        $data['name'] = $data['name'] ?? ($data['service_name'] ?? null);
        if (empty($data['name'])) {
            return response()->json(['message' => 'Thiếu tên dịch vụ'], 422);
        }

        // === Tạo customer nếu thiếu customer_id ===
        if (empty($data['customer_id'])) {
            $name  = trim($data['customer_name'] ?? '');
            $phone = $data['phone_number'] ?? null;

            if ($name === '')   return response()->json(['message' => 'Thiếu tên khách hàng'], 422);
            if ($phone === '' || $phone === null) return response()->json(['message' => 'Thiếu số điện thoại'], 422);

            // Lấy tên bảng từ Model để tránh hard-code
            $customerTable = (new Customer)->getTable(); // => 'customers'

            $attrs = ['store_id' => (int)$storeId, 'name' => $name];
            if ($phone) {
                if (Schema::hasColumn($customerTable, 'phone_number')) {
                    $attrs['phone_number'] = $phone;
                } elseif (Schema::hasColumn($customerTable, 'phone')) {
                    $attrs['phone'] = $phone;
                }
            }

            $customer = Customer::where('store_id', $storeId)->where('name', $name)->first();

            if (!$customer) {
                // bỏ qua mass-assignment để chắc set đủ cột
                $customer = (new Customer())->forceFill($attrs);
                $customer->save();
            } else {
                // nếu có phone mới và cột phone đang trống -> cập nhật nhẹ
                $needSave = false;
                if ($phone) {
                    if (Schema::hasColumn($customerTable, 'phone_number') && empty($customer->phone_number)) {
                        $customer->phone_number = $phone; $needSave = true;
                    } elseif (Schema::hasColumn($customerTable, 'phone') && empty($customer->phone)) {
                        $customer->phone = $phone; $needSave = true;
                    }
                    if ($needSave) $customer->save();
                }
            }

            $data['customer_id'] = $customer->id;
        }

        // Dọn field không thuộc bảng services
        unset($data['customer_name'], $data['phone_number'], $data['service_name']);

        // Bổ sung store_id & user_id cho services
        $data['store_id'] = $storeId;
        $data['user_id']  = $data['user_id'] ?? $userId;

        // Transaction: tạo service + cộng dồn nợ vào customers.debt
        $service = DB::transaction(function () use ($data, $storeId) {
            // 1) Tạo dịch vụ
            $svc = Service::create($data);

            // 2) Cộng dồn nợ cho khách (nếu có)
            $debtToAdd = (float)($data['debt'] ?? 0);
            if ($debtToAdd > 0 && !empty($data['customer_id'])) {
                Customer::where('id', $data['customer_id'])
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('debt', $debtToAdd);
            }

            return $svc;
        });

        // Trả resource (tuỳ cột phone hay phone_number của bạn)
        return (new ServiceResource(
            $service->load(['user:id,name', 'customer:id,name'])
        ))->response()->setStatusCode(201);
    }

    public function show(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $svc = Service::with(['customers:id,name,phone','user:id,name'])->where('store_id',$storeId)->findOrFail($id);
        return new ServiceResource($svc);
    }

    public function update(ServiceUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $svc = Service::where('store_id',$storeId)->findOrFail($id);
        $svc->update($r->validated());
        return new ServiceResource($svc->load(['customers:id,name,phone','user:id,name']));
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
