<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;                 // <- THÊM DÒNG NÀY
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ServiceStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $rawPhone = (string) ($this->input('phone_number') ?? '');
        $rawNote = (string) ($this->input('note') ?? '');

        $digits = preg_replace('/\D+/', '', $rawPhone);

        $this->merge([
            'phone_number' => $digits !== '' ? $digits : null,
            'note'         => $rawNote !== '' ? $rawNote : null,
            'price'   => (int) preg_replace('/\D+/', '', (string) $this->input('price')   ?: 0),
            'expense' => (int) preg_replace('/\D+/', '', (string) $this->input('expense') ?: 0),
            'debt'    => (int) preg_replace('/\D+/', '', (string) $this->input('debt')    ?: 0),
            'warranty'=> (int) ($this->input('warranty') ?? 0),
        ]);
    }


    public function rules(): array
    {
        return [
            'name'          => ['required','string','max:255'],
            'customer_id'   => ['nullable','integer','exists:customers,id'],
            'customer_name' => ['required_without:customer_id','string','max:255'],
            'phone_number'  => ['required_without:customer_id','string','max:20'],
            'price'         => ['required','integer','min:0'],
            'expense'       => ['required','integer','min:0'],
            'debt'          => ['nullable','integer','min:0'],
            'service_date'  => ['required','date'],
            'warranty'      => ['required','integer'],
            'note'          => ['nullable','string'],
        ];
    }
}
