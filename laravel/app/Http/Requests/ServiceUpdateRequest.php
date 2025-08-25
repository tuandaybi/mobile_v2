<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'customer_id'   => ['sometimes','nullable','exists:customers,id'],
            'customer_name' => ['sometimes','required','string'],
            'phone'         => ['sometimes','required','numeric'],
            'name'          => ['sometimes','required','string','max:255'],
            'price'         => ['sometimes','required','numeric','min:0'],
            'expense'       => ['sometimes','nullable','numeric','min:0'],
            'warranty'      => ['sometimes','required','numeric','min:0'],
            'user_id'       => ['sometimes','nullable','exists:users,id'],
            'note'          => ['sometimes','nullable','string'],
        ];
    }
}
