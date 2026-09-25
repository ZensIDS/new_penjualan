<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu jenis komponen hasil bongkar, beserta batch stok baru
 * yang dilahirkannya (dengan HPP hasil pembagian nilai unit utuh).
 *
 * buy_price & hpp_total di sini BUKAN angka mati: selama masih ada sisa stok
 * komponen yang belum terjual, keduanya dihitung ulang otomatis tiap kali ada
 * penjualan yang memberi informasi harga jual baru (lihat
 * StockConversionService::rebalance()). Yang sudah terlanjur terjual tidak ikut
 * berubah karena HPP-nya sudah tersnapshot di SaleItemAllocation.
 */
class StockConversionResult extends Model
{
    protected $fillable = [
        'stock_conversion_id',
        'product_id',
        'stock_batch_id',
        'qty',
        'ref_sell_price',
        'buy_price',
        'hpp_total',
    ];

    protected $casts = [
        'qty'            => 'integer',
        'ref_sell_price' => 'decimal:2',
        'buy_price'      => 'decimal:2',
        'hpp_total'      => 'decimal:2',
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

    /**
     * HPP yang sudah "keluar" dari batch ini karena qty-nya terjual/terpakai.
     * Dipakai rebalance() supaya nilai yang sudah mengalir ke transaksi lain
     * tidak ikut dibagi ulang.
     */
    public function getHppReleasedAttribute(): float
    {
        $batch = $this->stockBatch;
        $remaining = $batch ? (int) $batch->qty_remaining : 0;

        return round((float) $this->hpp_total - $remaining * (float) $this->buy_price, 2);
    }
}