<?php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
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
        $q = Customer::query()->where('store_id', $storeId);

        $search = trim((string) $r->query('search', $r->query('q', ''))); // ưu tiên ?search=..., fallback ?q=...
        $limit  = (int) $r->query('limit', 15);
        $limit  = max(1, min($limit, 15)); // giới hạn an toàn

        $q = Customer::query()
        ->where('store_id', $storeId)
        ->when($search !== '', function ($w) use ($search) {
            $like = "%{$search}%";
            $w->where(function ($x) use ($like) {
                $x->where('name',  'like', $like)
                  ->orWhere('phone','like', $like);
            });
        })
        ->orderByDesc('id')
        ->limit($limit);

        // --- sort ---
        $sortable = ['id','name','phone','created_at'];
        $sortBy  = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';


        // --- paginate bình thường ---
        $perPage = max(1, min((int) $r->input('perPage', 15), 200));
        return response()->json(
            $q->paginate($perPage)
        );
    }

    // POST /customers
    public function store(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        $data = $r->validate([
            'name'        => ['required','string','max:255'],
            'phone'       => ['nullable','string','max:20',
                Rule::unique('customers','phone')->where(fn($q)=>$q->where('store_id',$storeId))],
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
        if ($c->mobileOuts()->exists() || $c->services()->exists()) {
            return response()->json(['message'=>'Khách hàng đang được sử dụng. Không thể xoá.'], 409);
        }

        $c->delete();
        return response()->json(['message'=>'Đã xoá.']);
    }
}
