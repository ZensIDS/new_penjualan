<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'qty', 'buy_price', 'subtotal'];

    protected $casts = [
        'qty'       => 'integer',
        'buy_price' => 'decimal:2',
        'subtotal'  => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockBatch()
    {
        return $this->hasOne(StockBatch::class);
    }
}
