<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        $storeId = (int)($this->input('store_id') ?: 0);
        return [
            'name'     => ['required','string','max:255',
                Rule::unique('suppliers','name')->where(fn($q)=>$q->where('store_id',$storeId))],
            'tax_code' => ['nullable','string','max:50'],
            'phone'    => ['nullable','string','max:20'],
            'email'    => ['nullable','email','max:255'],
            'address'  => ['nullable','string'],
            'note'     => ['nullable','string'],
            'is_active'=> ['nullable','boolean'],
        ];
    }
}
