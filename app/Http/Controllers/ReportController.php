<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * Pagination & pencarian dilakukan di server supaya halaman ini tetap
     * ringan walau jumlah produk sudah ribuan baris (dulu: semua produk +
     * semua batch di-dump sekaligus ke halaman lalu difilter di JS).
     */
    public function stock(Request $request)
    {
        $search = $request->input('search');

        $data = $this->reportService->stockReportPaginated($search, 10);
        $kpis = $this->reportService->stockKpis();
        $byCategory = $this->reportService->stockValueByCategory();

        return view('reports.stock', compact('data', 'kpis', 'byCategory', 'search'));
    }

    public function profitLoss(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);

        $data = $this->reportService->profitLossReport($start, $end);

        return view('reports.profit-loss', [
            'data'      => $data,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }

    public function cashFlow(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);

        $data = $this->reportService->cashFlowReport($start, $end);

        // Tabel "Rincian Transaksi" dipaginasi terpisah (10/halaman) supaya
        // tidak nge-render seluruh transaksi kas dalam rentang tanggal
        // sekaligus. KPI & grafik di atas tetap pakai $data (agregat semua
        // baris pada rentang tanggal terpilih).
        $details = $this->reportService->cashFlowDetailsPaginated($start, $end, 10);

        return view('reports.cash-flow', [
            'data'      => $data,
            'details'   => $details,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }

    /**
     * Sama seperti stock(): pagination & pencarian di server, bukan dump
     * semua PO belum lunas sekaligus ke halaman.
     */
    public function payable(Request $request)
    {
        $search = $request->input('search');

        $data = $this->reportService->accountPayableReportPaginated($search, 10);
        $kpis = $this->reportService->payableKpis();

        return view('reports.payable', compact('data', 'kpis', 'search'));
    }

    public function receivable(Request $request)
    {
        $search = $request->input('search');

        $data = $this->reportService->accountReceivableReportPaginated($search, 10);
        $kpis = $this->reportService->receivableKpis();

        return view('reports.receivable', compact('data', 'kpis', 'search'));
    }

    public function salesReturn(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $search = $request->input('search');

        $data = $this->reportService->salesReturnReportPaginated($start, $end, $search, 10);
        $kpis = $this->reportService->salesReturnKpis($start, $end);

        return view('reports.sales-return', [
            'data'      => $data,
            'kpis'      => $kpis,
            'search'    => $search,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }

    public function purchaseReturn(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $search = $request->input('search');

        $data = $this->reportService->purchaseReturnReportPaginated($start, $end, $search, 10);
        $kpis = $this->reportService->purchaseReturnKpis($start, $end);

        return view('reports.purchase-return', [
            'data'      => $data,
            'kpis'      => $kpis,
            'search'    => $search,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }

    public function expenses(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $search = $request->input('search');
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        $data = $this->reportService->expenseReportPaginated($start, $end, $categoryId, $search, 10);
        $kpis = $this->reportService->expenseReportKpis($start, $end, $categoryId);
        $categories = \App\Models\ExpenseCategory::orderBy('name')->get(['id', 'name']);

        return view('reports.expenses', [
            'data'       => $data,
            'kpis'       => $kpis,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'search'     => $search,
            'startDate'  => $start,
            'endDate'    => $end,
        ]);
    }

    /**
     * Resolve rentang tanggal dari query string (default: bulan berjalan).
     * Dipakai bareng oleh profitLoss() & cashFlow() supaya perilaku filter konsisten
     * dengan yang ada di Dashboard.
     */
    private function resolveRange(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfMonth();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}
