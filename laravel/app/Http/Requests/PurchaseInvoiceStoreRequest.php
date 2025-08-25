<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseInvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        $storeId = (int)($this->input('store_id') ?: 0);
        return [
            'supplier_id'  => ['required','exists:suppliers,id'],
            'invoice_no'   => ['nullable','string','max:100',
                Rule::unique('purchase_invoices','invoice_no')->where(fn($q)=>$q->where('store_id',$storeId))],
            'invoice_date' => ['nullable','date'],
            'subtotal'     => ['nullable','numeric','min:0'],
            'tax_vat'      => ['nullable','numeric','min:0'],
            'discount'     => ['nullable','numeric','min:0'],
            'shipping_fee' => ['nullable','numeric','min:0'],
            'total'        => ['required','numeric','min:0'],
            'currency'     => ['nullable','string','max:10'],
            'attachment_url'=>['nullable','string','max:255'],
            'note'         => ['nullable','string'],
        ];
    }
}
