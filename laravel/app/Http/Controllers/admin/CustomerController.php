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
                Rule::unique('customers','phone')->ignore($c->id)->where(fn($q)=>$q->where('store_id',$storeId))],
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

    public function indexAdmin(Request $r)
    {

        $search  = trim((string) $r->query('search', $r->query('q', '')));
        $perPage = max(1, min((int) $r->query('perPage', 15), 200));
        $page    = max(1, (int) $r->query('page', 1));

        $sortable = ['id','name','phone','created_at','spent_total','debt_total'];
        $sortBy   = in_array($r->query('sortBy'), $sortable, true) ? $r->query('sortBy') : 'id';
        $sortDir  = strtolower($r->query('sortDir')) === 'asc' ? 'asc' : 'desc';

        $q = Customer::query()
            ->select(['id','store_id','name','phone','social_link','created_at'])
            ->with(['store:id,name'])

            // --- Tổng chi tiêu (giữ nguyên như trước) ---
            ->withSum(['mobileOuts as spent_mobileout'], 'export_price')
            ->withSum(['services as spent_service'], 'price')

            // --- Tổng nợ từ bảng debts ---
            // đổi 'debt' -> 'amount' nếu cột số tiền nợ tên khác
            ->withSum(['debtsMobileOut as debt_mobileout'], 'debt')
            ->withSum(['debtsService as debt_service'], 'debt')

            // --- Tổng đã thanh toán ---
            ->withSum(['paidsMobileOut as paid_mobileout'], 'paid_amount')
            ->withSum(['paidsService as paid_service'], 'paid_amount')

            // --- Tìm kiếm name/phone/social_link ---
            ->when($search !== '', function ($w) use ($search) {
                $like = "%{$search}%";
                $w->where(function ($x) use ($like) {
                    $x->where('name','like',$like)
                    ->orWhere('phone','like',$like)
                    ->orWhere('social_link','like',$like);
                });
            });

        // Sort theo tổng cộng ảo
        if ($sortBy === 'spent_total') {
            $q->orderByRaw('(COALESCE(spent_mobileout,0)+COALESCE(spent_service,0)) '.$sortDir);
        } elseif ($sortBy === 'debt_total') {
            $q->orderByRaw('(COALESCE(debt_mobileout,0)+COALESCE(debt_service,0)) '.$sortDir);
        } else {
            $q->orderBy($sortBy, $sortDir);
        }

        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(function ($c) {
            $spent_mobileout = (float) ($c->spent_mobileout ?? 0);
            $spent_service   = (float) ($c->spent_service ?? 0);
            $debt_mobileout  = (float) ($c->debt_mobileout ?? 0);
            $debt_service    = (float) ($c->debt_service ?? 0);
            $paid_mobileout  = (float) ($c->paid_mobileout ?? 0);
            $paid_service    = (float) ($c->paid_service ?? 0);

            return [
                'id'          => $c->id,
                'name'        => $c->name,
                'phone'       => $c->phone,
                'social_link' => $c->social_link,
                'store'       => [
                    'id'   => $c->store?->id,
                    'name' => $c->store?->name,
                ],
                'spent' => [
                    'mobileout' => $spent_mobileout,
                    'service'   => $spent_service,
                    'total'     => $spent_mobileout + $spent_service,
                ],
                'debt'  => [
                    'mobileout' => $debt_mobileout - $paid_mobileout,
                    'service'   => $debt_service - $paid_service,
                    'total'     => $debt_mobileout + $debt_service - $paid_mobileout - $paid_service,
                ],
                'created_at' => $c->created_at,
            ];
        });

        return response()->json($paginator);
    }

}
