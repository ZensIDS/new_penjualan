<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['expense_category_id', 'purchase_order_id', 'expense_date', 'amount', 'description'];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
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
}