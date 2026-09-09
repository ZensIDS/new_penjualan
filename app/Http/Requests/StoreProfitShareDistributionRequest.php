<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfitShareDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'distribution_date'          => ['required', 'date'],
            'note'                       => ['nullable', 'string', 'max:255'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.profit_share_id'    => ['nullable', 'exists:profit_shares,id'],
            'items.*.name'               => ['required', 'string', 'max:255'],
            'items.*.percentage'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.amount'             => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'      => 'Minimal harus ada 1 orang yang menerima bagi hasil.',
            'items.*.amount.min'  => 'Jumlah untuk setiap orang harus lebih dari 0.',
        ];
    }
}
