<?php

namespace App\Http\Controllers;

use App\Services\ReportService;

class StockController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * Halaman cek stok cepat (real-time): total stok + breakdown batch per
     * produk, dengan pencarian di sisi client. Beda dari reports.stock yang
     * diperlakukan sebagai laporan formal — data sumbernya sama-sama dari
     * ReportService::stockReport(), cuma tampilannya dibuat untuk pengecekan
     * harian yang cepat (expand/collapse per produk, search instan).
     */
    public function index()
    {
        $stock = $this->reportService->stockReport();

        return view('stock.index', compact('stock'));
    }
}
