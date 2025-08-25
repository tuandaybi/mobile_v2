<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileOutUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'customer_id'  => ['sometimes','required','exists:customer,id'],
            'export_date'  => ['sometimes','nullable','date'],
            'export_price' => ['sometimes','required','numeric','min:0'],
            'expense'      => ['sometimes','nullable','numeric','min:0'],
            'warranty'     => ['sometimes','nullable','numeric','min:0'],
        ];
    }
}
