<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'qty',
        'sell_price',
        'subtotal',
        'hpp_subtotal',
    ];

    protected $casts = [
        'qty'          => 'integer',
        'sell_price'   => 'decimal:2',
        'subtotal'     => 'decimal:2',
        'hpp_subtotal' => 'decimal:2',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations()
    {
        return $this->hasMany(SaleItemAllocation::class);
    }

    public function getMarginAttribute(): float
    {
        return (float) $this->subtotal - (float) $this->hpp_subtotal;
    }
}
