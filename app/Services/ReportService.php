<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SaleItem;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Laporan Stok: total stok per produk + breakdown per batch.
     */
    public function stockReport()
    {
        return Product::with(['category', 'stockBatches' => function ($q) {
            $q->where('qty_remaining', '>', 0)->orderBy('batch_date');
        }])
            ->get()
            ->map(function (Product $product) {
                return [
                    'product_id'   => $product->id,
                    'sku'          => $product->sku,
                    'name'         => $product->name,
                    'category'     => $product->category->name ?? null,
                    'total_qty'    => $product->qty_on_hand,
                    'stock_value'  => $product->stockBatches->sum(fn($b) => $b->qty_remaining * $b->buy_price),
                    'batches'      => $product->stockBatches->map(fn($b) => [
                        'batch_date'    => $b->batch_date->format('Y-m-d'),
                        'buy_price'     => (float) $b->buy_price,
                        'qty_in'        => $b->qty_in,
                        'qty_remaining' => $b->qty_remaining,
                    ]),
                ];
            });
    }

    /**
     * Laporan Laba Rugi untuk rentang tanggal tertentu.
     * Pendapatan - HPP (FIFO) = Laba Kotor. Laba Kotor - Biaya Operasional = Laba Bersih.
     * Basis: so_date / expense_date (accrual), BUKAN tanggal pembayaran diterima.
     */
    public function profitLossReport(string $startDate, string $endDate): array
    {
        $revenue = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('total_amount');
        $hpp     = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('total_hpp');
        $grossProfit = $revenue - $hpp;

        $operationalExpense = Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');
        $netProfit = $grossProfit - $operationalExpense;

        $expenseByCategory = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total'))
            ->groupBy('expense_categories.name')
            ->get();

        return [
            'period'                => [$startDate, $endDate],
            'revenue'                => (float) $revenue,
            'hpp'                    => (float) $hpp,
            'gross_profit'           => (float) $grossProfit,
            'operational_expense'    => (float) $operationalExpense,
            'expense_by_category'    => $expenseByCategory,
            'net_profit'             => (float) $netProfit,
        ];
    }

    /**
     * Laporan Arus Kas: kas masuk & kas keluar per tanggal, dari ledger cash_flows
     * (sumbernya sudah realisasi bayar, bukan accrual seperti Laba Rugi).
     */
    public function cashFlowReport(string $startDate, string $endDate): array
    {
        $rows = CashFlow::whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get();

        $totalIn  = $rows->where('direction', 'in')->sum('amount');
        $totalOut = $rows->where('direction', 'out')->sum('amount');

        $daily = $rows->groupBy(fn($r) => $r->transaction_date->format('Y-m-d'))
            ->map(function ($group) {
                return [
                    'in'  => (float) $group->where('direction', 'in')->sum('amount'),
                    'out' => (float) $group->where('direction', 'out')->sum('amount'),
                ];
            });

        return [
            'period'    => [$startDate, $endDate],
            'total_in'  => (float) $totalIn,
            'total_out' => (float) $totalOut,
            'net_cash'  => (float) ($totalIn - $totalOut),
            'daily'     => $daily,
            'details'   => $rows,
        ];
    }

    /**
     * Laporan Hutang (Account Payable): PO yang belum lunas + histori pembayaran.
     */
    public function accountPayableReport()
    {
        return PurchaseOrder::with(['supplier', 'payments'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('po_date')
            ->get()
            ->map(fn(PurchaseOrder $po) => [
                'po_number'         => $po->po_number,
                'supplier'          => $po->supplier->name,
                'po_date'           => $po->po_date->format('Y-m-d'),
                'total_amount'      => (float) $po->total_amount,
                'paid_amount'       => (float) $po->paid_amount,
                'remaining_balance' => (float) $po->remaining_balance,
                'payment_status'    => $po->payment_status,
                'payment_history'   => $po->payments,
            ]);
    }

    /**
     * Laporan Piutang (Account Receivable): SO yang belum lunas + histori pembayaran.
     */
    public function accountReceivableReport()
    {
        return SalesOrder::with(['customer', 'payments'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('so_date')
            ->get()
            ->map(fn(SalesOrder $so) => [
                'so_number'         => $so->so_number,
                'customer'          => $so->customer->name,
                'so_date'           => $so->so_date->format('Y-m-d'),
                'total_amount'      => (float) $so->total_amount,
                'paid_amount'       => (float) $so->paid_amount,
                'remaining_balance' => (float) $so->remaining_balance,
                'payment_status'    => $so->payment_status,
                'payment_history'   => $so->payments,
            ]);
    }
}
