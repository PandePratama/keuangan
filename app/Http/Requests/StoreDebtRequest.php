<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:debt,receivable'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
