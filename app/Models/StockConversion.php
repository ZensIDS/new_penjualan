<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Header 1 kali pembongkaran unit utuh menjadi komponen.
 * Lihat komentar lengkap konsepnya di migration create_stock_conversions_tables.
 */
class StockConversion extends Model
{
    protected $fillable = [
        'conversion_number',
        'conversion_date',
        'source_product_id',
        'source_qty',
        'total_hpp',
        'allocation_method',
        'rounding_diff',
        'note',
    ];

    protected $casts = [
        'conversion_date' => 'date',
        'source_qty'      => 'integer',
        'total_hpp'       => 'decimal:2',
        'rounding_diff'   => 'decimal:2',
    ];

    public function sourceProduct()
    {
        return $this->belongsTo(Product::class, 'source_product_id');
    }

    /** Batch-batch sumber yang dipotong FIFO */
    public function sources()
    {
        return $this->hasMany(StockConversionSource::class);
    }

    /** Komponen yang dihasilkan */
    public function results()
    {
        return $this->hasMany(StockConversionResult::class);
    }

    /**
     * Pembongkaran hanya boleh dibatalkan selama SEMUA komponen hasilnya belum
     * tersentuh sama sekali (belum terjual / belum ikut dibongkar lagi).
     */
    public function isReversible(): bool
    {
        foreach ($this->results as $result) {
            $batch = $result->stockBatch;

            if (! $batch || $batch->qty_remaining < $batch->qty_in) {
                return false;
            }
        }

        return true;
    }

    public function getAllocationMethodLabelAttribute(): string
    {
        return [
            'percent' => 'Persentase',
            'market'  => 'Proporsi Harga Jual',
            'manual'  => 'Nominal Manual',
        ][$this->allocation_method] ?? $this->allocation_method;
    }
}