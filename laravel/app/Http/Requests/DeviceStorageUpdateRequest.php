<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceStorageUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'name'     => ['sometimes','required','string','max:255'],
            'size_gb'  => ['sometimes','required','integer','min:1'],
            'is_active'=> ['sometimes','boolean'],
        ];
    }
}
