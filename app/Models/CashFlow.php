<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    protected $fillable = [
        'transaction_date',
        'direction',
        'amount',
        'source_type',
        'source_id',
        'description',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
    ];

    public function source()
    {
        return $this->morphTo();
    }

    /**
     * Label singkat buat badge "Tipe Transaksi" di Rincian Arus Kas, supaya
     * kelihatan jelas mana yang pengeluaran biasa vs biaya tambahan PO vs
     * pembayaran PO ke supplier vs sumber lain (income, retur, dll).
     *
     * Butuh relasi 'source' (dan 'source.purchaseOrder' kalau sourcenya
     * Expense/PurchasePayment) sudah di-load supaya tidak N+1 — lihat
     * ReportService::cashFlowDetailsPaginated().
     */
    public function getSourceLabelAttribute(): string
    {
        return match ($this->source_type) {
            Expense::class => $this->source?->purchase_order_id ? 'Biaya PO' : 'Pengeluaran',
            PurchasePayment::class => 'Pembayaran PO',
            Income::class => 'Pemasukan Lain',
            SalesOrder::class => 'Pembayaran Penjualan',
            SalesReturn::class => 'Retur Penjualan',
            PurchaseReturn::class => 'Retur Pembelian',
            ProfitShare::class => 'Bagi Hasil',
            default => 'Lainnya',
        };
    }

    /**
     * PurchaseOrder terkait (kalau ada) supaya baris di Rincian Arus Kas bisa
     * di-klik langsung ke halaman detail PO tersebut — baik untuk biaya
     * tambahan PO (Expense::purchaseOrder) maupun pembayaran PO
     * (PurchasePayment::purchaseOrder).
     */
    public function getRelatedPurchaseOrderAttribute(): ?PurchaseOrder
    {
        return match ($this->source_type) {
            Expense::class, PurchasePayment::class => $this->source?->purchaseOrder,
            default => null,
        };
    }
}