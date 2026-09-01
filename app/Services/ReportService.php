<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
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
        $revenuePaid = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('paid_amount');
        $revenuePending = $revenue - $revenuePaid; // "Pendapatan Tertahan": bagian penjualan yang belum dibayar (sudah masuk piutang)
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
            'revenue_paid'           => (float) $revenuePaid,
            'revenue_pending'        => (float) $revenuePending,
            'hpp'                    => (float) $hpp,
            'gross_profit'           => (float) $grossProfit,
            'operational_expense'    => (float) $operationalExpense,
            'expense_by_category'    => $expenseByCategory,
            'net_profit'             => (float) $netProfit,
        ];
    }

    /**
     * Ringkasan penjualan & pembelian (accrual, berdasar tanggal transaksi) untuk suatu rentang.
     * Dipakai di dashboard: "Penjualan Bulan Ini" (semua, termasuk yang belum dibayar)
     * dan "Total Pembelian Bulan Ini".
     */
    public function salesPurchaseSummary(string $startDate, string $endDate): array
    {
        $totalSales    = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('total_amount');
        $totalPurchase = PurchaseOrder::whereBetween('po_date', [$startDate, $endDate])->sum('total_amount');

        return [
            'total_sales'    => (float) $totalSales,
            'total_purchase' => (float) $totalPurchase,
        ];
    }

    /**
     * Pengeluaran kas riil (dari ledger cash_flows) pada rentang tanggal, dipisah
     * antara Biaya Operasional (Expense) dan Biaya Pembelian (pembayaran PO ke supplier).
     */
    public function expenseBreakdown(string $startDate, string $endDate): array
    {
        $rows = CashFlow::where('direction', 'out')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('source_type', DB::raw('SUM(amount) as total'))
            ->groupBy('source_type')
            ->pluck('total', 'source_type');

        $operational = (float) ($rows[Expense::class] ?? 0);
        $purchase    = (float) ($rows[PurchasePayment::class] ?? 0);

        return [
            'operational' => $operational,
            'purchase'    => $purchase,
            'total'       => $operational + $purchase,
        ];
    }

    /**
     * Produk terlaris dalam suatu rentang tanggal, diurutkan dari qty terjual terbanyak.
     */
    public function topSellingProducts(string $startDate, string $endDate, int $limit = 5)
    {
        return SaleItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sale_items.sales_order_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales_orders.so_date', [$startDate, $endDate])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(sale_items.qty) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();
    }

    /**
     * Tren penjualan harian (accrual, berdasar so_date) dalam suatu rentang tanggal.
     * Dipakai untuk grafik di dashboard.
     */
    public function dailySalesTrend(string $startDate, string $endDate)
    {
        return SalesOrder::whereBetween('so_date', [$startDate, $endDate])
            ->select('so_date', DB::raw('SUM(total_amount) as total'))
            ->groupBy('so_date')
            ->orderBy('so_date')
            ->get()
            ->mapWithKeys(fn($row) => [$row->so_date->format('Y-m-d') => (float) $row->total]);
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
