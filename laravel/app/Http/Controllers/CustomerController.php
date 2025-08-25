<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Requests\{CustomerStoreRequest, CustomerUpdateRequest};
use App\Http\Resources\CustomerResource;

class CustomerController extends Controller
{
    use ResolvesStore;

    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);
        $q = Customer::where('store_id', $storeId);

        if ($s = trim((string)$r->input('q'))) {
            $q->where(fn($w)=>$w->where('name','like',"%{$s}%")->orWhere('phone','like',"%{$s}%"));
        }
        if ($r->filled('debt_min')) $q->where('debt','>=',(float)$r->input('debt_min'));
        if ($r->filled('debt_max')) $q->where('debt','<=',(float)$r->input('debt_max'));

        if ($f = $r->input('date_from')) $q->whereDate('created_at','>=',$f);
        if ($t = $r->input('date_to'))   $q->whereDate('created_at','<=',$t);

        $sortable = ['id','name','phone','debt','created_at'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 15), 200));
        return CustomerResource::collection($q->paginate($perPage));
    }

    public function store(CustomerStoreRequest $r)
    {
        $storeId = $this->resolveStoreId($r);
        $data = $r->validated();
        $c = Customer::create($data + ['store_id'=>$storeId]);
        return (new CustomerResource($c))->response()->setStatusCode(201);
    }

    public function show(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $c = Customer::where('store_id',$storeId)->findOrFail($id);
        return new CustomerResource($c);
    }

    public function update(CustomerUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $c = Customer::where('store_id',$storeId)->findOrFail($id);
        $c->update($r->validated());
        return new CustomerResource($c);
    }

    public function destroy(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $c = Customer::where('store_id',$storeId)->findOrFail($id);

        if ($c->mobileOuts()->exists() || $c->services()->exists() || $c->tradeInMobileIns()->exists()) {
            return response()->json(['message'=>'Khách hàng đang được sử dụng. Không thể xoá.'], 409);
        }
        $c->delete();
        return response()->json(['message'=>'Đã xoá.']);
    }
}
