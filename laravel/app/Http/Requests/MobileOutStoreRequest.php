<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileOutStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $rawPhone = (string) ($this->input('phone_number') ?? '');

        $digits = preg_replace('/\D+/', '', $rawPhone);

        $this->merge([
            'phone_number' => $digits !== '' ? $digits : null,

            'export_price'   => (int) preg_replace('/\D+/', '', (string) $this->input('export_price')   ?: 0),
            'expense' => (int) preg_replace('/\D+/', '', (string) $this->input('expense') ?: 0),
            'debt_amount'    => (int) preg_replace('/\D+/', '', (string) $this->input('debt_amount')    ?: 0),
            'warranty'=> (int) ($this->input('warranty') ?? 0),
        ]);
    }

    public function rules(): array
    {
        return [
            'mobile_in_id'   => ['required','exists:mobile_in,id'],
            'customer_id'    => ['nullable','exists:customers,id'],
            'customer_name'  => ['required_without:customer_id','string','max:255'],
            'phone_number'   => ['required_without:customer_id','nullable','string','max:20'],
            'export_price'   => ['required','numeric','min:0'],
            'expense'        => ['required','numeric','min:0'],
            'debt_amount'    => ['nullable','numeric','min:0'],
            'payment'        => ['required','integer','in:0,1,2'],
            'export_date'    => ['required','date'],        // frontend gửi Y-m-d
            'warranty'       => ['required','integer','min:0','max:36'],
            'note'           => ['nullable','string'],
        ];
    }
}
