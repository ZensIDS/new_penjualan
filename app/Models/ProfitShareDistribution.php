<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfitShareDistribution extends Model
{
    protected $fillable = ['distribution_date', 'total_amount', 'note'];

    protected $casts = [
        'distribution_date' => 'date',
        'total_amount'      => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProfitShareDistributionItem::class);
    }

    // Sumber kas keluar terkait di ledger cash_flows (relasi manual mengikuti
    // pola source_type/source_id, sama seperti Expense/SalesPayment/dll —
    // lihat CashFlow::source() & CashFlowService).
    public function cashFlow()
    {
        return $this->morphOne(CashFlow::class, 'source', 'source_type', 'source_id');
    }
}
