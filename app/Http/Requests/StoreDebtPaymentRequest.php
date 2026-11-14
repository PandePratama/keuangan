<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('debt'));
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'mark_as_settled' => ['nullable', 'boolean'],
        ];
    }
}
