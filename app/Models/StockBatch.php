<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    protected $fillable = [
        'product_id',
        'purchase_order_item_id',
        'origin_type',
        'batch_date',
        'buy_price',
        'qty_in',
        'qty_remaining',
    ];

    protected $casts = [
        'batch_date'    => 'date',
        'buy_price'     => 'decimal:2',
        'qty_in'        => 'integer',
        'qty_remaining' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function allocations()
    {
        return $this->hasMany(SaleItemAllocation::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Kalau batch ini lahir dari pembongkaran unit utuh, ini baris komponennya
     * (berisi jejak dari bongkar mana dan berapa HPP yang dialokasikan).
     */
    public function conversionResult()
    {
        return $this->hasOne(StockConversionResult::class);
    }

    /** Potongan-potongan batch ini yang dipakai sebagai bahan pembongkaran */
    public function conversionSources()
    {
        return $this->hasMany(StockConversionSource::class);
    }

    public function isFromConversion(): bool
    {
        return $this->origin_type === 'conversion';
    }

    /**
     * Label asal batch untuk ditampilkan di layar stok, supaya user langsung
     * paham kenapa ada batch dengan HPP "aneh" (hasil pecahan unit utuh).
     */
    public function getOriginLabelAttribute(): string
    {
        if ($this->isFromConversion()) {
            $number = $this->conversionResult?->stockConversion?->conversion_number;

            return $number ? "Bongkar {$number}" : 'Hasil bongkar';
        }

        $number = $this->purchaseOrderItem?->purchaseOrder?->po_number;

        return $number ? "PO {$number}" : 'Pembelian';
    }
}