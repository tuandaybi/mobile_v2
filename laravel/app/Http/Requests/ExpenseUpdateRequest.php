<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $rawNote = (string) ($this->input('note') ?? '');
        $rawAmount = (string) ($this->input('amount') ?? '');
        $cleanAmount = preg_replace('/[^\d.]/', '', $rawAmount);

        $this->merge([
            'amount' => $cleanAmount !== '' ? (float)$cleanAmount : 0,
            'note'   => $rawNote !== '' ? $rawNote : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'date'        => ['required', 'date'],
            'note'        => ['nullable', 'string'],
        ];
    }
}
