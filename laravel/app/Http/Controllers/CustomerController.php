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
        $q = Customer::query()->where('store_id', $storeId);

        // Nhận nhiều key tìm kiếm cho linh hoạt FE
        $term = trim((string) ($r->input('q') ?? $r->input('search') ?? $r->input('term') ?? ''));

        // Lọc theo từ khoá (name/phone)
        if ($term !== '') {
            // escape ký tự wildcard nếu cần
            $like = str_replace(['%', '_'], ['\%', '\_'], $term);
            $q->where(function ($w) use ($like) {
                $w->where('name', 'like', "%{$like}%")
                ->orWhere('phone', 'like', "%{$like}%");
            });
        }


        // Lọc theo ngày tạo
        if ($f = $r->input('date_from')) $q->whereDate('created_at', '>=', $f);
        if ($t = $r->input('date_to'))   $q->whereDate('created_at', '<=', $t);

        // Sắp xếp
        $sortable = ['id', 'name', 'phone', 'debt', 'created_at'];
        $sortBy   = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir  = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        // Nếu có 'limit' (AutoComplete) -> trả list rút gọn, KHÔNG paginate
        if ($r->filled('limit')) {
            $limit = max(1, min((int) $r->input('limit'), 50));

            // Nếu không có term mà FE vẫn ping autocomplete -> trả rỗng cho nhẹ
            if ($term === '') {
                return CustomerResource::collection(collect());
            }

            // Chỉ chọn các cột cần thiết cho gợi ý
            $rows = $q->limit($limit)
                    ->get(['id', 'name', 'phone', 'created_at']);

            return CustomerResource::collection($rows);
        }

        // Mặc định: phân trang cho trang danh sách
        $perPage = max(1, min((int) $r->input('perPage', 15), 200));
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
