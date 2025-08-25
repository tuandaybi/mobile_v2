<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseInvoiceUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        $storeId = (int)($this->input('store_id') ?: 0);
        $id = $this->route('id') ?? $this->route('purchase_invoice');
        return [
            'supplier_id'  => ['sometimes','required','exists:suppliers,id'],
            'invoice_no'   => ['sometimes','nullable','string','max:100',
                Rule::unique('purchase_invoices','invoice_no')->ignore($id)->where(fn($q)=>$q->where('store_id',$storeId))],
            'invoice_date' => ['sometimes','nullable','date'],
            'subtotal'     => ['sometimes','nullable','numeric','min:0'],
            'tax_vat'      => ['sometimes','nullable','numeric','min:0'],
            'discount'     => ['sometimes','nullable','numeric','min:0'],
            'shipping_fee' => ['sometimes','nullable','numeric','min:0'],
            'total'        => ['sometimes','required','numeric','min:0'],
            'currency'     => ['sometimes','nullable','string','max:10'],
            'attachment_url'=>['sometimes','nullable','string','max:255'],
            'note'         => ['sometimes','nullable','string'],
        ];
    }
}
