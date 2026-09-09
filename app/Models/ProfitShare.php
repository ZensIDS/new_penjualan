<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfitShare extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'percentage', 'is_active'];

    protected $casts = [
        'percentage' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    // Histori baris distribusi milik orang ini (lihat komentar snapshot di
    // migration profit_share_distribution_items — tetap tersambung selama
    // orangnya belum dihapus dari master).
    public function distributionItems(): HasMany
    {
        return $this->hasMany(ProfitShareDistributionItem::class);
    }
}
