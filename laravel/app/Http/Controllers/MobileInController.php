<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\MobileIn;
use Illuminate\Http\Request;
use App\Http\Requests\{MobileInStoreRequest, MobileInUpdateRequest};
use App\Http\Resources\MobileInResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileInController extends Controller
{
    use ResolvesStore;

    public function index(Request $r)
    {
        // Lấy user + storeIds từ pivot user_in_store (user_id/store_id)
        $user = $r->user('sanctum') ?? $r->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $storeIds = DB::table('user_in_store')
            ->where('user_id', $user->id)
            ->pluck('store_id')
            ->all();

        if (empty($storeIds)) {
            return response()->json(['message' => 'User chưa được gán cửa hàng'], 403);
        }

        $q = MobileIn::select([
                'id','user_id','store_id','device_id','color_id','storage_id',
                'imei','country_code','battery_capacity','supplier',
                'import_price','import_date','import_note','is_sold','created_at'
            ])
            ->with([
                'user:id,name,email',
                'store:id,name,phone,address',
                'device:id,name,code',
                'color:id,vi_name,en_name',
                'storage:id,name,size_gb',
                'mobileOut:id,mobile_in_id,export_date,export_price',
            ])
            ->whereIn('store_id', $storeIds)->where('is_sold', 0);

        // Search text
        if ($s = trim((string)$r->input('q'))) {
            $q->where(function($w) use ($s) {
                $w->where('imei','like',"%{$s}%")
                  ->orWhere('country_code','like',"%{$s}%")
                  ->orWhere('import_note','like',"%{$s}%")
                  ->orWhere('supplier','like',"%{$s}%");
            });
        }

        // Chỉ cho phép filter các cột KHÔNG liên quan user/store
        foreach (['device_id','color_id','storage_id'] as $col) {
            if ($r->filled($col)) $q->where($col, $r->input($col));
        }
        if ($r->filled('supplier')) {
            $q->where('supplier','like','%'.$r->input('supplier').'%');
        }
        if ($r->filled('sold')) {
            $q->where('is_sold', (int)$r->input('sold') ? 1 : 0);
        }
        if ($f = $r->input('date_from')) $q->whereDate('import_date','>=',$f);
        if ($t = $r->input('date_to'))   $q->whereDate('import_date','<=',$t);

        $sortable = ['id','imei','import_date','import_price','is_sold'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 15), 200));
        return MobileInResource::collection($q->paginate($perPage));
    }

    public function show(Request $r, $id)
    {
        [$user, $storeIds] = $this->resolveUserAndStores($r);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        if (empty($storeIds)) return response()->json(['message' => 'User chưa được gán cửa hàng'], 403);

        $data = MobileIn::with([
                'user:id,name,email',
                'store:id,name,phone,address',
                'device:id,name',
                'color:id,vi_name,en_name',
                'storage:id,name,size_gb',
                'mobileOut:id,mobile_in_id,export_date,export_price',
            ])
            ->whereIn('store_id', $storeIds)
            ->findOrFail($id);

        return new MobileInResource($data);
    }

    public function store(MobileInStoreRequest $r)
    {
        [$user, $storeIds] = $this->resolveUserAndStores($r);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        if (empty($storeIds)) return response()->json(['message' => 'User chưa được gán cửa hàng'], 403);

        $storeId = method_exists($this, 'resolveStoreId') ? $this->resolveStoreId($r) : $storeIds[0];
        if (!in_array($storeId, $storeIds, true)) return response()->json(['message' => 'Cửa hàng không hợp lệ'], 422);

        $data = $r->validated();

        // Chuẩn hóa ngày
        if (!empty($data['import_date'])) {
            $data['import_date'] = \Carbon\Carbon::parse($data['import_date'])->format('Y-m-d');
        }

        // Chặn IMEI trùng khi chưa bán (is_sold=0)
        if (!empty($data['imei'])) {
            $dup = MobileIn::where('imei', $data['imei'])->where('is_sold', 0)->exists();
            if ($dup) return response()->json(['message'=>'IMEI đã tồn tại ở máy chưa bán'], 422);
        }

        // Không cho FE chèn các trường bảo vệ
        unset($data['user_id'], $data['store_id'], $data['is_sold']);

        $mob = \DB::transaction(function () use ($data, $user, $storeId) {
            return MobileIn::create($data + [
                'user_id'  => $user->id,
                'store_id' => $storeId,
                'is_sold'  => 0,
            ])->load(['device','color','storage','store','user','mobileOut']);
        });

        return (new MobileInResource($mob))->response()->setStatusCode(201);
    }

    public function update(MobileInUpdateRequest $r, $id)
    {
        [$user, $storeIds] = $this->resolveUserAndStores($r);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        if (empty($storeIds)) return response()->json(['message' => 'User chưa được gán cửa hàng'], 403);

        $mob = MobileIn::findOrFail($id);
        if (!in_array($mob->store_id, $storeIds, true)) return response()->json(['message' => 'Forbidden'], 403);

        $data = $r->validated();
        unset($data['user_id'], $data['store_id'], $data['is_sold']);

        if (array_key_exists('import_date', $data) && !empty($data['import_date'])) {
            $data['import_date'] = \Carbon\Carbon::parse($data['import_date'])->format('Y-m-d');
        }

        if (array_key_exists('imei', $data) && $data['imei'] !== '') {
            $dup = MobileIn::where('imei', $data['imei'])
                ->where('is_sold', 0)
                ->where('id','<>',$mob->id)
                ->exists();
            if ($dup) return response()->json(['message'=>'IMEI đã tồn tại ở máy chưa bán'], 422);
        }

        $mob->update($data);

        return new MobileInResource(
            $mob->load(['device','color','storage','store','user','mobileOut'])
        );
    }

    public function destroy(Request $r, $id)
    {
        [$user, $storeIds] = $this->resolveUserAndStores($r);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        if (empty($storeIds)) return response()->json(['message' => 'User chưa được gán cửa hàng'], 403);

        $mob = MobileIn::with('mobileOut')->findOrFail($id);
        if (!in_array($mob->store_id, $storeIds, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($mob->is_sold || $mob->mobileOut) {
            return response()->json(['message'=>'Không thể xoá vì máy đã được bán hoặc có bản ghi liên kết.'], 409);
        }

        $mob->delete();
        return response()->json(['message'=>'Đã xoá thành công.']);
    }

    public function toggleSold(Request $r, $id)
    {
        [$user, $storeIds] = $this->resolveUserAndStores($r);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        if (empty($storeIds)) return response()->json(['message' => 'User chưa được gán cửa hàng'], 403);

        $mob = MobileIn::with('mobileOut')->findOrFail($id);
        if (!in_array($mob->store_id, $storeIds, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Nếu đã có phiếu xuất thì không cho đổi trạng thái bán
        if ($mob->mobileOut && $mob->is_sold == 1) {
            return response()->json(['message' => 'Máy đã có phiếu xuất, không thể đổi trạng thái.'], 409);
        }
        if ($mob->mobileOut && $mob->is_sold == 0) {
            return response()->json(['message' => 'Máy đã có phiếu xuất, không thể chuyển về chưa bán.'], 409);
        }

        $mob->is_sold = $mob->is_sold ? 0 : 1;
        $mob->save();

        return response()->json(['id'=>$mob->id,'is_sold'=>(bool)$mob->is_sold]);
    }

    public function searchImei(Request $r, string $term)
{
    $user = $r->user('sanctum') ?? $r->user();
    if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

    // dò tên cột thật trong pivot
    $userCol  = Schema::hasColumn('user_in_store','user_id')  ? 'user_id'  : 'id_user';
    $storeCol = Schema::hasColumn('user_in_store','store_id') ? 'store_id' : 'id_store';

    $storeIds = DB::table('user_in_store')
        ->where($userCol, $user->id)
        ->pluck($storeCol)->all();

    if (empty($storeIds)) {
        return response()->json(['message' => 'User chưa được gán cửa hàng'], 403);
    }

    $term = trim($term);

    $q = MobileIn::query()
        ->whereIn('store_id', $storeIds)
        ->when($term !== '', fn($w) => $w->where('imei', 'like', '%'.$term))
        // ✅ ghép mobile_out + customer
        ->with([
            'device'  => fn($q)=>$q->select('id','name','code'),
            'color'   => fn($q)=>$q->select('id','vi_name','en_name'),
            'storage' => fn($q)=>$q->select('id','name','size_gb'),
            'mobileOut' => fn($q)=>$q
                ->select('id','mobile_in_id','customer_id','export_date','export_price','payment','warranty','note')
                ->with(['customer' => fn($c)=>$c->select('id','name','phone')]),
        ]);

    // tuỳ chọn: ?sold=1|0
    if ($r->filled('sold')) {
        $q->where('is_sold', (int)$r->input('sold') ? 1 : 0);
    }

    $list = $q->orderByDesc('id')->limit(100)->get();

    // Tạm thời trả thẳng JSON để dễ debug FE
    return response()->json($list);

    // Khi ổn rồi có thể bật Resource nếu muốn:
    // return MobileInResource::collection($list);
}
}
