<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfitShareDistributionRequest;
use App\Models\ProfitShare;
use App\Models\ProfitShareDistribution;
use App\Services\ProfitShareDistributionService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ProfitShareDistributionController extends Controller
{
    public function __construct(
        protected ProfitShareDistributionService $service,
        protected ReportService $reportService
    ) {}

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $search    = trim((string) $request->input('search', ''));

        $distributions = ProfitShareDistribution::query()
            ->with('items')
            ->when($startDate, fn($q) => $q->whereDate('distribution_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('distribution_date', '<=', $endDate))
            ->when($search !== '', function ($q) use ($search) {
                // Cari di keterangan/catatan, atau di nama salah satu penerima pada baris item-nya.
                $q->where(function ($q) use ($search) {
                    $q->where('note', 'like', "%{$search}%")
                        ->orWhereHas('items', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('distribution_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        // Total keseluruhan yang PERNAH dibagikan sepanjang masa (bukan cuma
        // periode filter) — supaya angka ringkasan di atas selalu jadi
        // jawaban langsung untuk "sudah berapa total yang pernah dibagikan".
        $totalAllTime = ProfitShareDistribution::sum('total_amount');

        // Master orang bagi hasil yang aktif, dipakai front-end (Alpine) untuk
        // auto-hitung pembagian per orang saat user mengisi jumlah yang mau
        // dibagi di modal "Bagikan Sekarang".
        $activeProfitShares = ProfitShare::where('is_active', true)
            ->orderByDesc('percentage')
            ->get(['id', 'name', 'percentage']);

        if ($request->ajax()) {
            return view('profit-share-distributions._table', compact('distributions'));
        }

        return view('profit-share-distributions.index', compact(
            'distributions',
            'totalAllTime',
            'activeProfitShares',
            'startDate',
            'endDate',
            'search'
        ));
    }

    public function store(StoreProfitShareDistributionRequest $request)
    {
        $distribution = $this->service->create($request->validated());

        return response()->json([
            'message' => 'Bagi hasil berhasil dicatat & kas sudah diperbarui.',
            'data'    => $distribution,
        ]);
    }

    /**
     * Dipanggil (AJAX) dari modal "Bagikan Sekarang": user pilih rentang
     * tanggal, lalu kita hitung Laba Bersih periode itu pakai rumus yang
     * SAMA PERSIS dengan Laporan Laba Rugi (ReportService::profitLossReport),
     * supaya angkanya selalu konsisten dengan laporan. Hasilnya cuma jadi
     * ISIAN AWAL di modal — tetap bisa diedit manual sebelum disimpan.
     */
    public function netProfit(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $report = $this->reportService->profitLossReport($request->start_date, $request->end_date);

        // Info tambahan: total yang SUDAH pernah dibagikan pada rentang tanggal
        // yang sama, supaya user tidak tanpa sadar membagikan laba periode yang
        // sama dua kali.
        $alreadyDistributed = ProfitShareDistribution::whereBetween('distribution_date', [$request->start_date, $request->end_date])
            ->sum('total_amount');

        return response()->json([
            'net_profit'                 => $report['net_profit'],
            'profit_shares'              => $report['profit_shares'],
            'total_profit_share_amount'  => $report['total_profit_share_amount'],
            'already_distributed'        => (float) $alreadyDistributed,
        ]);
    }

    public function destroy(ProfitShareDistribution $profitShareDistribution)
    {
        $this->service->delete($profitShareDistribution);

        return response()->json([
            'message' => 'Catatan distribusi bagi hasil berhasil dihapus, kas dikembalikan.',
        ]);
    }
}
