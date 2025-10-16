<?php
namespace App\Http\Requests;

use App\Http\Controllers\Concerns\ResolvesStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
{
    use ResolvesStore;

    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $sid = $this->resolveStoreId($this);
        if ($sid) $this->merge(['store_id' => $sid]);
    }
    
    public function rules(): array {
        $storeId = (int)($this->input('store_id') ?: 0);
        $id = $this->route('id') ?? $this->route('customer');
        return [
            'name'        => ['sometimes','required','string','max:255'],
            'phone'       => ['sometimes','nullable','string','min:10','max:20',
                Rule::unique('customers','phone')->ignore($id)->where(fn($q)=>$q->where('store_id',$storeId))],
            'social_link' => ['sometimes','nullable','string','max:255'],
            'note'        => ['sometimes','nullable','string'],
        ];
    }
}
