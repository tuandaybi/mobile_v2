<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MobileInUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'device_id'  => ['sometimes','integer','exists:devices,id'],
            'color_id'   => ['sometimes','integer','exists:colors,id'],
            'storage_id' => ['sometimes','integer','exists:storages,id'],
            'imei'       => ['sometimes','string','max:32'],
            'battery_capacity' => ['nullable','integer','between:0,100'],
            'country_code'     => ['nullable','string','max:10'],
            'supplier'         => ['nullable','string','max:191'], // ✅
            'import_price'     => ['nullable','integer','min:0'],
            'import_date'      => ['sometimes','date'],
            'import_note'      => ['nullable','string','max:500'],
        ];
    }
}
