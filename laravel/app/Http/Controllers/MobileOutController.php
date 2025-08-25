<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\Customer;
use App\Models\MobileIn;
use App\Models\MobileOut;
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

        // Tạo customer nếu thiếu customer_id (giống như ta đã làm)
        if (empty($data['customer_id'])) {
            $name  = trim($data['customer_name'] ?? '');
            $phone = $data['phone_number'] ?? null;
            if ($name === '') {
                return response()->json(['message' => 'Thiếu tên khách hàng'], 422);
            }
            if ($phone === '') {
                return response()->json(['message' => 'Thiếu số điện thoại'], 422);
            }

            $attrs = ['store_id' => (int)$storeId, 'name' => $name];
            if ($phone) {
                if (Schema::hasColumn('customers', 'phone')) {
                    $attrs['phone'] = $phone;
                }
            }

            $customer = Customer::where('store_id', $storeId)->where('name', $name)->first();
            if (!$customer) {
                $customer = (new Customer())->forceFill($attrs);
                $customer->save();
            } else if ($phone) {
                $needSave = false;
                if (Schema::hasColumn('customer', 'phone_number') && empty($customer->phone_number)) {
                    $customer->phone_number = $phone; $needSave = true;
                } elseif (Schema::hasColumn('customer', 'phone') && empty($customer->phone)) {
                    $customer->phone = $phone; $needSave = true;
                }
                if ($needSave) $customer->save();
            }

            $data['customer_id'] = $customer->id;
        }

        unset($data['customer_name'], $data['phone_number']);

        // ====== Transaction: tạo bán + cập nhật is_sold + cộng dồn debt vào Customer ======
        $sale = DB::transaction(function () use ($data, $userId, $mob, $storeId) {
            // 1) Tạo phiếu bán
            $sale = MobileOut::create($data + ['user_id' => $userId]);

            // 2) Đánh dấu đã bán
            $mob->update(['is_sold' => 1]);

            // 3) Cộng dồn nợ cho khách hàng
            $debtToAdd = (float)($data['debt'] ?? 0);
            if ($debtToAdd > 0 && !empty($data['customer_id'])) {
                // Khoá dòng để cộng dồn an toàn
                $cust = Customer::where('id', $data['customer_id'])
                    ->where('store_id', $storeId)
                    ->lockForUpdate()
                    ->first();

                if ($cust) {
                    // Dùng increment để update trực tiếp
                    $cust->increment('debt', $debtToAdd);
                    // Nếu muốn lưu thời điểm phát sinh nợ:
                    // $cust->last_debt_at = now(); $cust->save();
                }
            }

            return $sale;
        });

        return (new MobileOutResource(
            $sale->load(['mobileIn.device','user','customer'])
        ))->response()->setStatusCode(201);
    }

    public function show($id)
    {
        $sale = MobileOut::with(['mobileIn.device','mobileIn.color','mobileIn.storage','user','customer'])->findOrFail($id);
        return new MobileOutResource($sale);
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
