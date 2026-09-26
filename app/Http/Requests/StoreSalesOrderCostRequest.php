<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sama seperti pembayaran SO & expense biasa: hanya superadmin yang
        // boleh input/kelola biaya tambahan SO. Viewer hanya boleh melihat.
        return $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            // Tidak ada 'expense_date' — tanggal biaya tambahan SO selalu
            // ikut tanggal SO (lihat SalesOrderService::addExtraCost()).
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'description'         => ['nullable', 'string', 'max:255'],
        ];
    }
}