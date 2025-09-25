<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileInStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'device_id'  => ['required','integer','exists:devices,id'],
            'color_id'   => ['required','integer','exists:colors,id'],
            'storage_id' => ['required','integer','exists:storages,id'],
            'imei'       => ['required','string','max:17', 'regex:/^\d{15,17}$/'],
            'battery_capacity' => ['nullable','integer','between:0,100'],
            'country_code'     => ['nullable','string','max:10'],
            'supplier'         => ['nullable','string','max:191'], // ✅ varchar note
            'import_price'     => ['nullable','integer','min:0'],
            'import_date'      => ['required','date'],
            'import_note'      => ['nullable','string','max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'imei' => (string) $this->input('imei'),
        ]);
    }
}
