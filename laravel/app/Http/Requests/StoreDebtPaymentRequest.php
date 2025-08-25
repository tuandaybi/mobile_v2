<?php

namespace App\Http\Requests\Admin\Debt;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount'  => ['required','numeric','min:0.01'],
            'paid_at' => ['nullable','date'],
            'note'    => ['nullable','string','max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Vui lòng nhập số tiền thu.',
            'amount.min'      => 'Số tiền thu phải > 0.',
        ];
    }
}
