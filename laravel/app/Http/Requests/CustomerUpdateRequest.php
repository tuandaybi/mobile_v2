<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        $storeId = (int)($this->input('store_id') ?: 0);
        $id = $this->route('id') ?? $this->route('customer');
        return [
            'name'        => ['sometimes','required','string','max:255'],
            'phone'       => ['sometimes','nullable','string','max:20',
                Rule::unique('customer','phone')->ignore($id)->where(fn($q)=>$q->where('store_id',$storeId))],
            'social_link' => ['sometimes','nullable','string','max:255'],
            'note'        => ['sometimes','nullable','string'],
        ];
    }
}
