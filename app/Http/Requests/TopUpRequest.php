<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopUpRequest extends FormRequest
{
    public function rules(): array
    {
        $currency = strtoupper((string) $this->input('currency_code'));
        return [
            'amount' => ['required','integer','min:1'],
            'account_id'    => [
                'required','uuid',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('currency_code', $currency)),
            ],
            'currency_code' => ['required','string','size:3','exists:currencies,code'],
            'idempotency_key' => ['nullable','string','max:128'],
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
