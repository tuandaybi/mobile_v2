<?php

namespace App\Http\Requests\Admin\Debt;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'mobileout_id' => ['nullable','integer','exists:mobile_outs,id'],
            'service_id'   => ['nullable','integer','exists:services,id'],
            'customer_id'  => ['nullable','integer','exists:customers,id'],
            'debt'         => ['required','numeric','min:0.01'],
            'date'         => ['required','date'],
            'due_date'     => ['nullable','date','after_or_equal:date'],
            'note'         => ['nullable','string','max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (!$this->filled('mobileout_id') && !$this->filled('service_id')) {
                $v->errors()->add('source', 'Cần mobileout_id hoặc service_id.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'debt.required' => 'Vui lòng nhập số tiền nợ.',
            'debt.min'      => 'Số tiền nợ phải lớn hơn 0.',
            'date.required' => 'Vui lòng chọn ngày phát sinh.',
            'due_date.after_or_equal' => 'Hạn trả phải >= ngày phát sinh.',
        ];
    }
}
