<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        $storeId = (int)($this->input('store_id') ?: 0);
        $id = $this->route('id') ?? $this->route('supplier');
        return [
            'name'     => ['sometimes','required','string','max:255',
                Rule::unique('suppliers','name')->ignore($id)->where(fn($q)=>$q->where('store_id',$storeId))],
            'tax_code' => ['sometimes','nullable','string','max:50'],
            'phone'    => ['sometimes','nullable','string','max:20'],
            'email'    => ['sometimes','nullable','email','max:255'],
            'address'  => ['sometimes','nullable','string'],
            'note'     => ['sometimes','nullable','string'],
            'is_active'=> ['sometimes','boolean'],
        ];
    }
}
