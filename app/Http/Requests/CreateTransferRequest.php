<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTransferRequest extends FormRequest
{
    public function rules(): array
    {
        $currency = strtoupper((string)$this->input('currency_code'));

        return [
            'from_account_id' => [
                'required', 'uuid',
                Rule::exists('accounts', 'id')->where(fn($q) => $q
                    ->where('currency_code', $currency)
                    ->where('is_system', false)
                ),
            ],
            'to_account_id' => [
                'required', 'uuid', 'different:from_account_id',
                Rule::exists('accounts', 'id')->where(fn($q) => $q
                    ->where('currency_code', $currency)
                    ->where('is_system', false)
                ),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('currency_code')) {
            $this->merge(['currency_code' => strtoupper($this->input('currency_code'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }
}
