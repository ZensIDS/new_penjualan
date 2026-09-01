<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function stock()
    {
        $data = $this->reportService->stockReport();

        return view('reports.stock', compact('data'));
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

        return view('reports.cash-flow', [
            'data'      => $data,
            'startDate' => $start,
            'endDate'   => $end,
        ]);
    }

    public function payable()
    {
        $data = $this->reportService->accountPayableReport();

        return view('reports.payable', compact('data'));
    }

    public function receivable()
    {
        $data = $this->reportService->accountReceivableReport();

        return view('reports.receivable', compact('data'));
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
