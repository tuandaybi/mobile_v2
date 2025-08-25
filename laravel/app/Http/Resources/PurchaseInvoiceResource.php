<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'store_id'=>$this->store_id,
            'supplier'=> new SupplierResource($this->whenLoaded('supplier')),
            'invoice_no'=>$this->invoice_no,
            'invoice_date'=>$this->invoice_date? $this->invoice_date->toDateString(): null,
            'subtotal'=>$this->subtotal,
            'tax_vat'=>$this->tax_vat,
            'discount'=>$this->discount,
            'shipping_fee'=>$this->shipping_fee,
            'total'=>$this->total,
            'currency'=>$this->currency,
            'attachment_url'=>$this->attachment_url,
            'note'=>$this->note,
            'created_at'=>optional($this->created_at)->toISOString(),
        ];
    }
}
