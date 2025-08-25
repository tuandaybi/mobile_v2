<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        $storeId = (int)($this->input('store_id') ?: 0);
        return [
            'name'        => ['required','string','max:255'],
            'phone'       => ['nullable','string','max:20',
                Rule::unique('customer','phone')->where(fn($q)=>$q->where('store_id',$storeId))],
            'social_link' => ['nullable','string','max:255'],
            'debt'        => ['nullable','numeric','min:0'],
            'note'        => ['nullable','string'],
        ];
    }
}
