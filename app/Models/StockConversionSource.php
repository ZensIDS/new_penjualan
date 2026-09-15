<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu potongan qty dari satu stock_batch unit utuh yang dibongkar.
 * Polanya identik dengan SaleItemAllocation (sama-sama hasil FIFO), bedanya
 * hasil potongan ini tidak keluar dari gudang melainkan berubah wujud jadi komponen.
 */
class StockConversionSource extends Model
{
    protected $fillable = [
        'stock_conversion_id',
        'stock_batch_id',
        'qty_taken',
        'buy_price_at_time',
        'hpp_subtotal',
    ];

    protected $casts = [
        'qty_taken'         => 'integer',
        'buy_price_at_time' => 'decimal:2',
        'hpp_subtotal'      => 'decimal:2',
    ];

    public function stockConversion()
    {
        return $this->belongsTo(StockConversion::class);
    }

    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class);
    }
}