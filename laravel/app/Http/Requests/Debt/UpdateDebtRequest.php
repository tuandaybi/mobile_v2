<?php

namespace App\Http\Requests\Admin\Debt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDebtRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'mobileout_id' => ['sometimes','nullable','integer','exists:mobile_outs,id'],
            'service_id'   => ['sometimes','nullable','integer','exists:services,id'],
            'customer_id'  => ['sometimes','nullable','integer','exists:customers,id'],
            'user_id'      => ['sometimes','nullable','integer','exists:users,id'],
            'debt'         => ['sometimes','numeric','min:0.01'],
            'date'         => ['sometimes','date'],
            'due_date'     => ['sometimes','nullable','date','after_or_equal:date'],
            'status'       => ['sometimes', Rule::in(['pending','partial','paid'])],
            'note'         => ['sometimes','nullable','string','max:500'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            // Không cho xoá cả 2 nguồn nếu bản ghi hiện tại cũng không có
            if (
                $this->has('mobileout_id') && $this->input('mobileout_id') === null &&
                $this->has('service_id')   && $this->input('service_id')   === null
            ) {
                $v->errors()->add('source', 'Cần mobileout_id hoặc service_id.');
            }
        });
    }
}
