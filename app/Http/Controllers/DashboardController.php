<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function index()
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth   = now()->endOfMonth()->toDateString();

        $profitLoss = $this->reportService->profitLossReport($startOfMonth, $endOfMonth);
        $cashFlow   = $this->reportService->cashFlowReport($startOfMonth, $endOfMonth);

        $totalReceivable = $this->reportService->accountReceivableReport()->sum('remaining_balance');
        $totalPayable    = $this->reportService->accountPayableReport()->sum('remaining_balance');

        $stockValue = Product::query()
            ->join('stock_batches', 'stock_batches.product_id', '=', 'products.id')
            ->where('stock_batches.qty_remaining', '>', 0)
            ->sum(DB::raw('stock_batches.qty_remaining * stock_batches.buy_price'));

        $lowStockProducts = Product::with('category:id,name')
            ->where('is_active', true)
            ->where('qty_on_hand', '<=', 5)
            ->orderBy('qty_on_hand')
            ->limit(5)
            ->get(['id', 'category_id', 'name', 'qty_on_hand']);

        return view('dashboard', [
            'profitLoss'       => $profitLoss,
            'cashFlow'         => $cashFlow,
            'totalReceivable'  => $totalReceivable,
            'totalPayable'     => $totalPayable,
            'stockValue'       => (float) $stockValue,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}
