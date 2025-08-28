<?php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Requests\{SupplierStoreRequest, SupplierUpdateRequest};
use App\Http\Resources\SupplierResource;

class SupplierController extends Controller
{
    use ResolvesStore;

    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);
        $q = Supplier::where('store_id',$storeId);

        if ($s = trim((string)$r->input('q'))) {
            $q->where(fn($w)=>$w->where('name','like',"%{$s}%")
                                ->orWhere('phone','like',"%{$s}%")
                                ->orWhere('email','like',"%{$s}%"));
        }
        if ($r->filled('active')) $q->where('is_active', (int)$r->input('active') ? 1 : 0);

        $sortable = ['id','name'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 15), 200));
        return SupplierResource::collection($q->paginate($perPage));
    }

    public function store(SupplierStoreRequest $r)
    {
        $storeId = $this->resolveStoreId($r);
        $sup = Supplier::create($r->validated() + ['store_id'=>$storeId]);
        return (new SupplierResource($sup))->response()->setStatusCode(201);
    }

    public function show(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $sup = Supplier::where('store_id',$storeId)->findOrFail($id);
        return new SupplierResource($sup);
    }

    public function update(SupplierUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $sup = Supplier::where('store_id',$storeId)->findOrFail($id);
        $sup->update($r->validated());
        return new SupplierResource($sup);
    }

    public function destroy(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $sup = Supplier::where('store_id',$storeId)->findOrFail($id);

        if ($sup->mobileIns()->exists() || $sup->purchaseInvoices()->exists()) {
            return response()->json(['message'=>'Nhà cung cấp đang được sử dụng. Hãy vô hiệu hoá thay vì xoá.'], 409);
        }
        $sup->delete();
        return response()->json(['message'=>'Đã xoá.']);
    }
}
