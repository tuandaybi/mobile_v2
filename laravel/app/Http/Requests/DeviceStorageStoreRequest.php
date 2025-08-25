<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeviceStorageStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'name'     => ['required','string','max:255'],
            'size_gb'  => ['required','integer','min:1'],
            'is_active'=> ['nullable','boolean'],
        ];
    }
}
