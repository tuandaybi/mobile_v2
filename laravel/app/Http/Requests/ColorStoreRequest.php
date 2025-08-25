<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ColorStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'vi_name' => ['required','string','max:255'],
            'en_name' => ['nullable','string','max:255'],
        ];
    }
}
