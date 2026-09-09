<?php

namespace App\Services;

use App\Models\ProfitShareDistribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfitShareDistributionService
{
    public function __construct(protected CashFlowService $cashFlowService) {}

    /**
     * Catat satu transaksi distribusi bagi hasil (header + rincian per orang),
     * lalu catat SATU baris kas keluar sebesar total_amount di ledger
     * cash_flows. Sengaja TIDAK menyentuh apa pun terkait Laporan Laba Rugi
     * — lihat komentar di migration create_profit_share_distributions_table.
     *
     * $data['items'] = [['profit_share_id' => ?, 'name' => ..., 'percentage' => ?, 'amount' => ...], ...]
     */
    public function create(array $data): ProfitShareDistribution
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];
            $totalAmount = collect($items)->sum(fn($item) => (float) $item['amount']);

            $distribution = ProfitShareDistribution::create([
                'distribution_date' => $data['distribution_date'],
                'total_amount'      => $totalAmount,
                'note'              => $data['note'] ?? null,
            ]);

            foreach ($items as $item) {
                $distribution->items()->create([
                    'profit_share_id' => $item['profit_share_id'] ?? null,
                    'name'            => $item['name'],
                    'percentage'      => $item['percentage'] ?? null,
                    'amount'          => $item['amount'],
                ]);
            }

            $this->cashFlowService->recordOut(
                $data['distribution_date'],
                $totalAmount,
                $distribution,
                $this->buildDescription($distribution, $items)
            );

            return $distribution->load('items');
        });
    }

    // Hapus transaksi distribusi + rincian (cascade lewat FK) + baris kas
    // keluar terkait, supaya ledger arus kas tidak menyisakan entry hantu.
    public function delete(ProfitShareDistribution $distribution): void
    {
        DB::transaction(function () use ($distribution) {
            $this->cashFlowService->deleteForSource($distribution);
            $distribution->delete();
        });
    }

    // Keterangan singkat yang tampil di Laporan Arus Kas, mis.
    // "Bagi hasil: Budi (Rp2.000.000), Ani (Rp1.500.000)" — dipotong kalau
    // orangnya banyak supaya kolom Keterangan tidak meluber.
    protected function buildDescription(ProfitShareDistribution $distribution, array $items): string
    {
        $names = collect($items)->pluck('name')->implode(', ');
        $prefix = "Bagi hasil ke " . count($items) . ' orang: ';
        $desc = $prefix . $names;

        return Str::limit($desc, 250);
    }
}
