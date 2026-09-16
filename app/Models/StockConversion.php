<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Header 1 kali pembongkaran unit utuh menjadi komponen.
 * Lihat komentar lengkap konsepnya di migration create_stock_conversions_tables.
 *
 * Catatan sejak migration simplify_stock_conversion_allocation:
 * tidak ada lagi pilihan metode pembagian. HPP unit SELALU dibagi ke komponen
 * secara proporsional terhadap nilai jualnya (relative sales value), dan harga
 * jual yang dipakai adalah harga jual RIIL terakhir dari Sales Order — bukan
 * tebakan yang diketik user saat membongkar.
 */
class StockConversion extends Model
{
    protected $fillable = [
        'conversion_number',
        'conversion_date',
        'source_product_id',
        'source_qty',
        'total_hpp',
        'status',
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
     * Berlaku sama untuk transaksi draft maupun yang sudah selesai.
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

    /** Belum semua komponen diketahui — masih boleh ditambah lewat "Lanjutkan Bongkar". */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Semua komponen sudah tercatat. */
    public function isComplete(): bool
    {
        return $this->status === 'selesai';
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->isDraft() ? 'Belum Selesai' : 'Selesai';
    }
}