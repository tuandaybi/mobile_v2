<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Concerns\ResolvesStore;

class CustomerStoreRequest extends FormRequest
{
    use ResolvesStore;

    protected function prepareForValidation(): void
    {
        $sid = $this->resolveStoreId($this);
        if ($sid) $this->merge(['store_id' => $sid]);
    }

    public function authorize(): bool { return true; }
    public function rules(): array {
        $storeId = (int) $this->input('store_id');
        return [
            'name'        => ['required','string','max:255'],
            'phone'       => ['nullable','string','max:20',
                Rule::unique('customers','phone')->where(fn($q)=>$q->where('store_id',$storeId))],
            'social_link' => ['nullable','string','max:255'],
            'note'        => ['nullable','string'],
        ];
    }
}
