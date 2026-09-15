<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pembongkaran mengubah stok & HPP, jadi hanya superadmin.
        return $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        $method = $this->input('allocation_method');

        return [
            'conversion_date'   => ['required', 'date'],
            'source_product_id' => ['required', 'exists:products,id'],
            'source_qty'        => ['required', 'integer', 'min:1'],
            'allocation_method' => ['required', 'in:percent,market,manual'],
            'note'              => ['nullable', 'string', 'max:1000'],

            'components'              => ['required', 'array', 'min:1'],
            'components.*.product_id' => ['required', 'exists:products,id'],
            'components.*.qty'        => ['required', 'integer', 'min:1'],

            // Kolom nilai hanya wajib sesuai metode yang dipilih
            'components.*.allocation_percent' => [
                $method === 'percent' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100',
            ],
            'components.*.estimated_sell_price' => [
                $method === 'market' ? 'required' : 'nullable', 'numeric', 'min:0',
            ],
            'components.*.hpp_total' => [
                $method === 'manual' ? 'required' : 'nullable', 'numeric', 'min:0',
            ],
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

            // Metode persentase: total harus 100% (toleransi 0,01 untuk pembulatan input)
            if ($this->input('allocation_method') === 'percent') {
                $total = collect($components)->sum(fn($c) => (float) ($c['allocation_percent'] ?? 0));

                if (abs($total - 100) > 0.01) {
                    $validator->errors()->add(
                        'components',
                        'Total persentase semua komponen harus 100% (sekarang ' . rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',') . '%).'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'components.required'  => 'Minimal harus ada 1 komponen hasil pembongkaran.',
            'components.*.qty.min' => 'Qty komponen minimal 1.',
            'components.*.allocation_percent.required'   => 'Persentase tiap komponen wajib diisi.',
            'components.*.estimated_sell_price.required' => 'Estimasi harga jual tiap komponen wajib diisi.',
            'components.*.hpp_total.required'            => 'Nominal HPP tiap komponen wajib diisi.',
        ];
    }
}