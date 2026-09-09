<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitShareDistributionItem extends Model
{
    protected $fillable = [
        'profit_share_distribution_id',
        'profit_share_id',
        'name',
        'percentage',
        'amount',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount'     => 'decimal:2',
    ];

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(ProfitShareDistribution::class, 'profit_share_distribution_id');
    }

    // Nullable: bisa null kalau orangnya sudah dihapus dari master profit_shares
    // (lihat komentar di migration) — name/percentage di baris ini tetap valid.
    public function profitShare(): BelongsTo
    {
        return $this->belongsTo(ProfitShare::class);
    }
}
