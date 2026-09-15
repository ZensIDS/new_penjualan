<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu jenis komponen hasil bongkar, beserta batch stok baru
 * yang dilahirkannya (dengan HPP hasil pembagian nilai unit utuh).
 */
class StockConversionResult extends Model
{
    protected $fillable = [
        'stock_conversion_id',
        'product_id',
        'stock_batch_id',
        'qty',
        'allocation_percent',
        'estimated_sell_price',
        'buy_price',
        'hpp_total',
    ];

    protected $casts = [
        'qty'                  => 'integer',
        'allocation_percent'   => 'decimal:4',
        'estimated_sell_price' => 'decimal:2',
        'buy_price'            => 'decimal:2',
        'hpp_total'            => 'decimal:2',
    ];

    public function stockConversion()
    {
        return $this->belongsTo(StockConversion::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class);
    }

    /** Qty komponen ini yang sudah terpakai (terjual/dibongkar lagi) */
    public function getQtyUsedAttribute(): int
    {
        $batch = $this->stockBatch;

        return $batch ? ($batch->qty_in - $batch->qty_remaining) : 0;
    }
}