<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ColorUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'vi_name' => ['sometimes','required','string','max:255'],
            'en_name' => ['sometimes','nullable','string','max:255'],
        ];
    }
}
