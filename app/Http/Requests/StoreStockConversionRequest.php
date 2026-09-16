<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form "Bongkar Unit".
 *
 * Sejak pembagian HPP dibuat otomatis (proporsi harga jual riil dari Sales
 * Order), form ini tidak lagi menerima allocation_method, allocation_percent,
 * estimated_sell_price, maupun hpp_total — user cukup menyatakan komponen apa
 * saja yang keluar dan berapa banyak.
 */
class StoreStockConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pembongkaran mengubah stok & HPP, jadi hanya superadmin.
        return $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'conversion_date'   => ['required', 'date'],
            'source_product_id' => ['required', 'exists:products,id'],
            'source_qty'        => ['required', 'integer', 'min:1'],
            'note'              => ['nullable', 'string', 'max:1000'],

            // 'draft' = belum semua komponen diketahui, boleh dilanjutkan nanti
            // lewat "Lanjutkan Bongkar". Default 'selesai'.
            'status' => ['nullable', 'in:draft,selesai'],

            'components'              => ['required', 'array', 'min:1'],
            'components.*.product_id' => ['required', 'exists:products,id'],
            'components.*.qty'        => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $components = $this->input('components', []);

            // 1 produk komponen = 1 baris
            $ids = collect($components)->pluck('product_id')->filter();
            if ($ids->count() !== $ids->unique()->count()) {
                $validator->errors()->add('components', 'Satu produk komponen tidak boleh dipilih di lebih dari 1 baris.');
            }

            // Komponen tidak boleh sama dengan produk yang dibongkar
            if ($ids->contains($this->input('source_product_id'))) {
                $validator->errors()->add('components', 'Produk hasil bongkar tidak boleh sama dengan produk yang dibongkar.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'components.required'       => 'Minimal harus ada 1 komponen hasil pembongkaran.',
            'components.*.qty.min'      => 'Qty komponen minimal 1.',
            'components.*.product_id.required' => 'Masih ada baris komponen yang produknya belum dipilih.',
        ];
    }
}