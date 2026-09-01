<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(protected CashFlowService $cashFlowService) {}

    public function create(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $expense = Expense::create($data);

            $this->cashFlowService->recordOut(
                $data['expense_date'],
                $data['amount'],
                $expense,
                $expense->description ?? "Biaya operasional: {$expense->category->name}"
            );

            return $expense;
        });
    }
}
