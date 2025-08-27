<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
                'name'            => ['required','string','max:255'],
                'customer_id'     => ['nullable','exists:customers,id'],
                'customer_name'   => ['required_without:customer_id','string','max:255'],
                'phone_number'    => ['required_without:customer_id','string','max:20'],
                'price'           => ['required','numeric','min:0'],
                'expense'         => ['required','numeric','min:0'],
                'debt'            => ['nullable','numeric','min:0'],
                'service_date'    => ['required','date'],
                'warranty'        => ['required','integer','min:0','max:12'],
                'note'            => ['nullable','string'],
            ];
    }
}
