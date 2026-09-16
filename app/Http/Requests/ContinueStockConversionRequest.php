<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi untuk "Lanjutkan Bongkar" — menambah komponen baru dan/atau menambah
 * qty komponen lama pada pembongkaran yang statusnya masih 'draft'.
 *
 * Pembagian HPP-nya otomatis (proporsi harga jual riil), jadi form ini hanya
 * berurusan dengan komponen & qty. Aturan bisnis yang tidak bisa diwakili di
 * sini (qty tidak boleh dikurangi di bawah yang sudah terjual, total_hpp tetap)
 * ditegakkan di StockConversionService::continueConversion().
 */
class ContinueStockConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'mark_complete' => ['nullable', 'boolean'],

            // Baris komponen LAMA (qty boleh ditambah)
            'existing'             => ['nullable', 'array'],
            'existing.*.result_id' => ['required_with:existing', 'integer'],
            'existing.*.qty'       => ['required_with:existing', 'integer', 'min:1'],

            // Baris komponen BARU
            'components'              => ['nullable', 'array'],
            'components.*.product_id' => ['required_with:components', 'exists:products,id'],
            'components.*.qty'        => ['required_with:components', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasExisting = ! empty($this->input('existing', []));
            $hasNew = collect($this->input('components', []))
                ->filter(fn($c) => ! empty($c['product_id']))
                ->isNotEmpty();

            if (! $hasExisting && ! $hasNew) {
                $validator->errors()->add('components', 'Tidak ada perubahan untuk disimpan — tambah minimal 1 komponen baru atau ubah qty yang sudah ada.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'components.*.qty.min' => 'Qty komponen minimal 1.',
            'existing.*.qty.min'   => 'Qty komponen minimal 1.',
        ];
    }
}