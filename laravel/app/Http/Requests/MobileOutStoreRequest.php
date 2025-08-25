<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileOutStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'mobile_in_id'   => ['required','exists:mobile_in,id'],
            'customer_id'    => ['nullable','exists:customers,id'],
            'customer_name'  => ['required_without:customer_id','string','max:255'],
            'phone_number'   => ['required_without:customer_id','nullable','string','max:20'],
            'export_price'   => ['required','numeric','min:0'],
            'expense'        => ['required','numeric','min:0'],
            'debt'           => ['nullable','numeric','min:0'],
            'payment'        => ['required','integer','in:0,1,2'],
            'export_date'    => ['required','date'],        // frontend gửi Y-m-d
            'warranty'       => ['required','integer','min:0','max:12'],
            'note'           => ['nullable','string'],
        ];
    }
}
