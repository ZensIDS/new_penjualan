<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    // Daftar asal/channel penjualan yang valid — dipakai di validasi Request
    // dan di dropdown form create/edit. Tambah entri di sini kalau ada channel baru.
    public const SOURCES = [
        'offline'  => 'Offline',
        'whatsapp' => 'WhatsApp',
        'shopee'   => 'Shopee',
    ];

    protected $fillable = [
        'so_number',
        'customer_id',
        'so_date',
        'total_amount',
        'total_hpp',
        'paid_amount',
        'payment_status',
        'note',
        'source',
    ];

    protected $casts = [
        'so_date'      => 'date',
        'total_amount' => 'decimal:2',
        'total_hpp'    => 'decimal:2',
        'paid_amount'  => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments()
    {
        return $this->hasMany(SalesPayment::class);
    }

    public function returns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->paid_amount;
    }

    public function getGrossProfitAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->total_hpp;
    }

    // Edit & hapus transaksi hanya boleh selama belum ada pembayaran sama sekali.
    // Begitu ada pembayaran (sekecil apa pun), transaksi dianggap final.
    public function canBeModified(): bool
    {
        return $this->payment_status === 'unpaid' && (float) $this->paid_amount <= 0;
    }
}
