<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['expense_category_id', 'purchase_order_id', 'sales_order_id', 'is_paid', 'expense_date', 'amount', 'description'];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
        'is_paid'      => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    // Diisi hanya kalau expense ini berasal dari "Biaya Tambahan PO" (lihat
    // PurchaseOrderService::addExtraCost) — null untuk expense biasa yang
    // diinput dari halaman Pengeluaran.
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // Diisi hanya kalau expense ini berasal dari "Biaya Tambahan SO" (lihat
    // SalesOrderService::addExtraCost) — null untuk expense biasa yang
    // diinput dari halaman Pengeluaran.
    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
}