<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function stock()
    {
        $data = $this->reportService->stockReport();

        return view('reports.stock', compact('data'));
        // Kalau API: return response()->json($data);
    }

    public function profitLoss(Request $request)
    {
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end   = $request->input('end_date', now()->endOfMonth()->toDateString());

        $data = $this->reportService->profitLossReport($start, $end);

        return view('reports.profit-loss', compact('data'));
    }

    public function cashFlow(Request $request)
    {
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end   = $request->input('end_date', now()->endOfMonth()->toDateString());

        $data = $this->reportService->cashFlowReport($start, $end);

        return view('reports.cash-flow', compact('data'));
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
}
