<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kasir & admin boleh input penjualan
        return in_array($this->user()->role, ['admin', 'kasir']);
    }

    public function rules(): array
    {
        return [
            'customer_id'   => ['required', 'exists:customers,id'],
            'so_date'       => ['required', 'date'],
            'note'          => ['nullable', 'string', 'max:1000'],

            'items'                    => ['required', 'array', 'min:1'],
            'items.*.product_id'       => ['required', 'exists:products,id'],
            'items.*.qty'              => ['required', 'integer', 'min:1'],
            'items.*.sell_price'       => ['required', 'numeric', 'min:0'],

            'initial_payment'          => ['nullable', 'numeric', 'min:0'],
            'payment_method'           => ['nullable', 'in:cash,transfer,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'  => 'Minimal harus ada 1 item barang dalam transaksi.',
            'items.*.qty.min' => 'Qty barang minimal 1.',
        ];
    }
}
