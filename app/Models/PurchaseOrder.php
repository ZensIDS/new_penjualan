<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'supplier_id',
        'po_date',
        'total_amount',
        'paid_amount',
        'payment_status',
        'note',
    ];

    protected $casts = [
        'po_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function returns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->paid_amount;
    }

    // Edit & hapus PO hanya boleh selama belum ada pembayaran sama sekali.
    // Begitu ada pembayaran (sekecil apa pun), PO dianggap final.
    public function canBeModified(): bool
    {
        return $this->payment_status === 'unpaid' && (float) $this->paid_amount <= 0;
    }
}
