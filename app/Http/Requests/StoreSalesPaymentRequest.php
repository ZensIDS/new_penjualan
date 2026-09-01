<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'kasir', 'finance']);
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'amount'       => ['required', 'numeric', 'min:0.01'],
            'method'       => ['required', 'in:cash,transfer,other'],
            'note'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
