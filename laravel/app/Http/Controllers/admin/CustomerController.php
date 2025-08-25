<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use ResolvesStore;

    // GET /customers
    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        $q = Customer::where('store_id', $storeId);

        if ($s = trim((string)$r->input('q'))) {
            $q->where(function($w) use ($s) {
                $w->where('name','like',"%{$s}%")
                  ->orWhere('phone','like',"%{$s}%");
            });
        }

        if ($r->filled('debt_min')) $q->where('debt', '>=', (float)$r->input('debt_min'));
        if ($r->filled('debt_max')) $q->where('debt', '<=', (float)$r->input('debt_max'));

        if ($f = $r->input('date_from')) $q->whereDate('created_at','>=',$f);
        if ($t = $r->input('date_to'))   $q->whereDate('created_at','<=',$t);

        $sortable = ['id','name','phone','debt','created_at'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 15), 200));
        return response()->json($q->paginate($perPage));
    }

    // POST /customers
    public function store(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        $data = $r->validate([
            'name'        => ['required','string','max:255'],
            'phone'       => ['nullable','string','max:20',
                Rule::unique('customer','phone')->where(fn($q)=>$q->where('store_id',$storeId))],
            'social_link' => ['nullable','string','max:255'],
            'debt'        => ['nullable','numeric','min:0'],
            'note'        => ['nullable','string'],
        ]);

        $c = Customer::create($data + ['store_id'=>$storeId]);
        return response()->json($c, 201);
    }

    // GET /customers/{id}
    public function show(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $c = Customer::where('store_id',$storeId)->findOrFail($id);
        return response()->json($c);
    }

    // PATCH /customers/{id}
    public function update(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $c = Customer::where('store_id',$storeId)->findOrFail($id);

        $data = $r->validate([
            'name'        => ['sometimes','required','string','max:255'],
            'phone'       => ['sometimes','nullable','string','max:20',
                Rule::unique('customer','phone')->ignore($c->id)->where(fn($q)=>$q->where('store_id',$storeId))],
            'social_link' => ['sometimes','nullable','string','max:255'],
            'debt'        => ['sometimes','nullable','numeric','min:0'],
            'note'        => ['sometimes','nullable','string'],
        ]);

        $c->update($data);
        return response()->json($c);
    }

    // DELETE /customers/{id}
    public function destroy(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $c = Customer::where('store_id',$storeId)->findOrFail($id);

        // Nếu còn liên kết -> không xoá
        if ($c->mobileOuts()->exists() || $c->services()->exists() || $c->tradeInMobileIns()->exists()) {
            return response()->json(['message'=>'Khách hàng đang được sử dụng. Không thể xoá.'], 409);
        }

        $c->delete();
        return response()->json(['message'=>'Đã xoá.']);
    }
}
