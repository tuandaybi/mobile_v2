<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use App\Http\Requests\{PurchaseInvoiceStoreRequest, PurchaseInvoiceUpdateRequest};
use App\Http\Resources\PurchaseInvoiceResource;

class PurchaseInvoiceController extends Controller
{
    use ResolvesStore;

    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);
        $q = PurchaseInvoice::with(['supplier:id,name'])->where('store_id',$storeId);

        if ($s = trim((string)$r->input('q'))) {
            $q->where(fn($w)=>$w->where('invoice_no','like',"%{$s}%")->orWhere('note','like',"%{$s}%"));
        }
        if ($f = $r->input('date_from')) $q->whereDate('invoice_date','>=',$f);
        if ($t = $r->input('date_to'))   $q->whereDate('invoice_date','<=',$t);

        $sortable = ['id','invoice_date','invoice_no','total'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'id';
        $sortDir = strtolower($r->input('sortDir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 15), 200));
        return PurchaseInvoiceResource::collection($q->paginate($perPage));
    }

    public function store(PurchaseInvoiceStoreRequest $r)
    {
        $storeId = $this->resolveStoreId($r);
        $inv = PurchaseInvoice::create($r->validated() + ['store_id'=>$storeId]);
        return (new PurchaseInvoiceResource($inv->load('supplier:id,name')))->response()->setStatusCode(201);
    }

    public function show(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $inv = PurchaseInvoice::with(['supplier:id,name','mobileIns:id,purchase_invoice_id,imei'])
            ->where('store_id',$storeId)->findOrFail($id);
        return new PurchaseInvoiceResource($inv);
    }

    public function update(PurchaseInvoiceUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $inv = PurchaseInvoice::where('store_id',$storeId)->findOrFail($id);
        $inv->update($r->validated());
        return new PurchaseInvoiceResource($inv->load('supplier:id,name'));
    }

    public function destroy(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $inv = PurchaseInvoice::where('store_id',$storeId)->findOrFail($id);
        if ($inv->mobileIns()->exists()) {
            return response()->json(['message'=>'Hoá đơn đã gắn máy nhập, không thể xoá.'], 409);
        }
        $inv->delete();
        return response()->json(['message'=>'Đã xoá hoá đơn.']);
    }
}
