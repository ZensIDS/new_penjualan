<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya superadmin yang boleh input PO. Viewer hanya boleh melihat.
        return $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'supplier_id'   => ['required', 'exists:suppliers,id'],
            'po_date'       => ['required', 'date'],
            'note'          => ['nullable', 'string', 'max:1000'],

            'items'                    => ['required', 'array', 'min:1'],
            'items.*.product_id'       => ['required', 'exists:products,id'],
            'items.*.qty'              => ['required', 'integer', 'min:1'],
            'items.*.buy_price'        => ['required', 'numeric', 'min:0'],

            'initial_payment'          => ['nullable', 'numeric', 'min:0'],
            'payment_method'           => ['nullable', 'in:cash,transfer,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'   => 'Minimal harus ada 1 item barang dalam PO.',
            'items.*.qty.min'  => 'Qty barang minimal 1.',
        ];
    }
}
