<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sama seperti pembayaran PO & expense biasa: hanya superadmin yang
        // boleh edit biaya tambahan PO. Viewer hanya boleh melihat.
        return $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date'        => ['required', 'date'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'description'         => ['nullable', 'string', 'max:255'],
        ];
    }
}