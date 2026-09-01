<?php

namespace App\Services;

use App\Models\CashFlow;
use Illuminate\Database\Eloquent\Model;

class CashFlowService
{
    public function recordIn(string $date, float $amount, Model $source, string $description): CashFlow
    {
        return CashFlow::create([
            'transaction_date' => $date,
            'direction'        => 'in',
            'amount'           => $amount,
            'source_type'      => get_class($source),
            'source_id'        => $source->getKey(),
            'description'      => $description,
        ]);
    }

    public function recordOut(string $date, float $amount, Model $source, string $description): CashFlow
    {
        return CashFlow::create([
            'transaction_date' => $date,
            'direction'        => 'out',
            'amount'           => $amount,
            'source_type'      => get_class($source),
            'source_id'        => $source->getKey(),
            'description'      => $description,
        ]);
    }
}
