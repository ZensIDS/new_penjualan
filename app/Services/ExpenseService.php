<?php

namespace App\Services;

use App\Models\CashFlow;
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

    /**
     * Catat Expense TANPA langsung membuat entry cash_flow & tanpa dianggap
     * lunas (is_paid=false) — dipakai khusus untuk "Biaya Tambahan PO"
     * (lihat PurchaseOrderService::addExtraCost), supaya biaya tersebut
     * belum ikut ke Laporan Pengeluaran/Laba Rugi/arus kas sampai user
     * menandainya "Lunas" lewat markPaid().
     */
    public function createUnpaid(array $data): Expense
    {
        return Expense::create([...$data, 'is_paid' => false]);
    }

    /**
     * Tandai Expense yang sebelumnya belum lunas (createUnpaid) sebagai
     * lunas: baru di titik inilah entry cash_flow dibuat, sehingga baru
     * dari sini biaya tersebut ikut ke Laporan Pengeluaran, Laba Rugi,
     * dan ledger arus kas.
     */
    public function markPaid(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense) {
            if ($expense->is_paid) {
                return $expense;
            }

            $expense->update(['is_paid' => true]);

            $this->cashFlowService->recordOut(
                $expense->expense_date->toDateString(),
                (float) $expense->amount,
                $expense,
                $expense->description ?? "Biaya operasional: {$expense->category->name}"
            );

            return $expense->fresh();
        });
    }

    // Update expense + sinkronkan cash_flow terkait (biar ledger kas tetap konsisten).
    public function update(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            $expense->update($data);
            $expense->refresh();

            $cashFlow = $this->findCashFlow($expense);

            if ($cashFlow) {
                $cashFlow->update([
                    'transaction_date' => $expense->expense_date,
                    'amount'           => $expense->amount,
                    'description'      => $expense->description ?? "Biaya operasional: {$expense->category->name}",
                ]);
            }

            return $expense;
        });
    }

    // Hapus expense + cash_flow terkait sekaligus, supaya tidak ada ledger nyangkut.
    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $this->findCashFlow($expense)?->delete();
            $expense->delete();
        });
    }

    protected function findCashFlow(Expense $expense): ?CashFlow
    {
        return CashFlow::where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->first();
    }
}