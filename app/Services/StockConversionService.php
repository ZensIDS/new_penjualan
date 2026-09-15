<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockConversion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Otak fitur "Bongkar Unit".
 *
 * Alur 1 kali pembongkaran:
 *   1. Ambil N unit produk utuh dari stok secara FIFO (persis seperti penjualan),
 *      sehingga dapat TOTAL HPP riil dari batch-batch yang dipotong.
 *   2. Bagi total HPP itu ke komponen-komponen hasil bongkar, pakai salah satu
 *      dari 3 metode (percent / market / manual).
 *   3. Buat batch stok baru untuk tiap komponen dengan buy_price = HPP hasil bagi.
 *
 * Prinsip yang dijaga mati-matian:
 * - TIDAK ADA uang keluar/masuk -> tidak menyentuh cash_flow & tidak bikin laba/rugi.
 *   Laba baru muncul nanti saat komponennya dijual lewat Sales Order biasa.
 * - Total HPP sebelum = total HPP sesudah. Sisa pembulatan (paling banter
 *   beberapa rupiah) dibuang ke komponen terakhir dan dicatat di rounding_diff.
 */
class StockConversionService
{
    public function __construct(
        protected StockService $stockService,
        protected DocumentNumberService $numberService,
    ) {}

    /**
     * @param array $data       ['conversion_date', 'source_product_id', 'source_qty',
     *                           'allocation_method', 'note']
     * @param array $components [['product_id', 'qty', 'allocation_percent'?,
     *                            'estimated_sell_price'?, 'hpp_total'?], ...]
     */
    public function create(array $data, array $components): StockConversion
    {
        return DB::transaction(function () use ($data, $components) {
            $sourceProduct = Product::lockForUpdate()->findOrFail($data['source_product_id']);

            $this->guardComponents($sourceProduct, $components);

            $conversion = StockConversion::create([
                'conversion_number' => $data['conversion_number']
                    ?? $this->numberService->generate('BK', StockConversion::class, 'conversion_number'),
                'conversion_date'   => $data['conversion_date'],
                'source_product_id' => $sourceProduct->id,
                'source_qty'        => $data['source_qty'],
                'allocation_method' => $data['allocation_method'],
                'total_hpp'         => 0, // diisi setelah FIFO
                'rounding_diff'     => 0,
                'note'              => $data['note'] ?? null,
            ]);

            // --- Langkah 1: potong stok unit utuh secara FIFO ---
            $allocations = $this->stockService->allocateFifoForConversion(
                $sourceProduct,
                (int) $data['source_qty'],
                $data['conversion_date'],
                $conversion
            );

            $totalHpp = 0;
            foreach ($allocations as $alloc) {
                $conversion->sources()->create($alloc);
                $totalHpp += $alloc['hpp_subtotal'];
            }

            $conversion->update(['total_hpp' => $totalHpp]);

            // --- Langkah 2: bagi total HPP ke komponen ---
            $shares = $this->splitHpp($totalHpp, $components, $data['allocation_method']);

            // --- Langkah 3: lahirkan batch baru per komponen ---
            $distributed = 0;

            foreach ($components as $i => $component) {
                $hppTotal = $shares[$i];
                $qty      = (int) $component['qty'];
                $buyPrice = round($hppTotal / $qty, 2);

                // buy_price dibulatkan ke 2 desimal, jadi nilai batch yang benar-benar
                // tersimpan adalah buy_price * qty — itu yang dicatat sebagai hpp_total
                // supaya angka di laporan bongkar SAMA PERSIS dengan nilai stok riil.
                $hppTotal = round($buyPrice * $qty, 2);
                $distributed += $hppTotal;

                $batch = $this->stockService->receiveFromConversion(
                    $conversion,
                    (int) $component['product_id'],
                    $qty,
                    $buyPrice
                );

                $conversion->results()->create([
                    'product_id'           => $component['product_id'],
                    'stock_batch_id'       => $batch->id,
                    'qty'                  => $qty,
                    'allocation_percent'   => $component['allocation_percent'] ?? null,
                    'estimated_sell_price' => $component['estimated_sell_price'] ?? null,
                    'buy_price'            => $buyPrice,
                    'hpp_total'            => $hppTotal,
                ]);
            }

            // Selisih pembulatan disimpan, bukan disembunyikan. Nilainya sangat kecil
            // (maksimal beberapa rupiah) dan akan muncul sebagai beda HPP tipis saat
            // komponen terjual habis — ini wajar dan bisa ditelusuri dari sini.
            $conversion->update(['rounding_diff' => round($totalHpp - $distributed, 2)]);

            return $conversion->fresh(['sources.stockBatch', 'results.product', 'sourceProduct']);
        });
    }

    /**
     * Batalkan pembongkaran: komponen ditarik kembali dari stok, unit utuh
     * dikembalikan ke batch asalnya. Hanya boleh kalau SEMUA komponen hasil
     * bongkar masih utuh (belum terjual, belum dibongkar lagi) — kalau sudah
     * kepakai, nilai HPP-nya sudah terlanjur mengalir ke transaksi lain dan
     * tidak bisa ditarik balik tanpa merusak laporan.
     */
    public function delete(StockConversion $conversion): void
    {
        DB::transaction(function () use ($conversion) {
            $conversion->load(['results.stockBatch', 'results.product', 'sources']);

            foreach ($conversion->results as $result) {
                $batch = $result->stockBatch;

                if (! $batch) {
                    continue;
                }

                if ($batch->qty_remaining < $batch->qty_in) {
                    $used = $batch->qty_in - $batch->qty_remaining;

                    throw new RuntimeException(
                        "Pembongkaran {$conversion->conversion_number} tidak bisa dibatalkan: komponen \"{$result->product->name}\" sudah terpakai {$used} dari {$batch->qty_in}. Batalkan/retur dulu transaksi yang memakainya."
                    );
                }
            }

            // Tarik semua batch komponen (aman, sudah dipastikan belum tersentuh)
            foreach ($conversion->results as $result) {
                if ($result->stockBatch) {
                    $this->stockService->removeConversionBatch($result->stockBatch);
                }
            }

            // Kembalikan qty unit utuh ke batch asalnya
            foreach ($conversion->sources as $source) {
                $this->stockService->reverseConversionSource(
                    $source,
                    now()->toDateString(),
                    "Pembatalan pembongkaran {$conversion->conversion_number}"
                );
            }

            $conversion->delete(); // sources & results ikut terhapus (cascade)
        });
    }

    /**
     * Bagi total HPP ke tiap komponen sesuai metode yang dipilih.
     * Mengembalikan array nominal HPP, indeksnya sejajar dengan $components.
     *
     * @return array<int, float>
     */
    protected function splitHpp(float $totalHpp, array $components, string $method): array
    {
        if ($method === 'manual') {
            $sum = round(array_sum(array_map(fn($c) => (float) $c['hpp_total'], $components)), 2);

            if (abs($sum - round($totalHpp, 2)) > 0.01) {
                throw new RuntimeException(
                    'Total HPP komponen (Rp ' . number_format($sum, 0, ',', '.') . ') harus sama persis dengan HPP unit yang dibongkar (Rp ' . number_format($totalHpp, 0, ',', '.') . ').'
                );
            }

            return array_map(fn($c) => round((float) $c['hpp_total'], 2), $components);
        }

        // percent -> bobot = persentase; market -> bobot = estimasi harga jual x qty
        $weights = array_map(function ($c) use ($method) {
            return $method === 'percent'
                ? (float) $c['allocation_percent']
                : (float) $c['estimated_sell_price'] * (int) $c['qty'];
        }, $components);

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw new RuntimeException(
                $method === 'percent'
                    ? 'Total persentase komponen harus lebih dari 0.'
                    : 'Estimasi harga jual komponen harus diisi (minimal satu komponen bernilai lebih dari 0).'
            );
        }

        $shares = [];
        $running = 0;
        $lastIndex = count($components) - 1;

        foreach ($weights as $i => $weight) {
            if ($i === $lastIndex) {
                // Komponen terakhir menerima SISA, bukan hasil hitung ulang —
                // ini yang menjamin total pembagian tidak pernah meleset dari total HPP.
                $shares[$i] = round($totalHpp - $running, 2);
                break;
            }

            $shares[$i] = round($totalHpp * ($weight / $totalWeight), 2);
            $running += $shares[$i];
        }

        return $shares;
    }

    /**
     * Validasi yang tidak bisa diwakili aturan FormRequest biasa.
     */
    protected function guardComponents(Product $sourceProduct, array $components): void
    {
        if (empty($components)) {
            throw new RuntimeException('Minimal harus ada 1 komponen hasil pembongkaran.');
        }

        $productIds = array_map(fn($c) => (int) $c['product_id'], $components);

        if (in_array($sourceProduct->id, $productIds, true)) {
            throw new RuntimeException(
                "Produk hasil bongkar tidak boleh sama dengan produk yang dibongkar (\"{$sourceProduct->name}\"). Buat produk komponen tersendiri di master Produk."
            );
        }

        if (count($productIds) !== count(array_unique($productIds))) {
            throw new RuntimeException('Satu produk komponen tidak boleh muncul di lebih dari 1 baris.');
        }
    }

    /**
     * Estimasi HPP per unit produk utuh berdasarkan batch FIFO yang akan kepakai
     * duluan. Dipakai form (JS) untuk menampilkan perkiraan nilai yang akan dibagi
     * SEBELUM disimpan. Angka finalnya tetap dihitung ulang server-side saat simpan.
     */
    public function estimateSourceHpp(Product $product, int $qty): float
    {
        $remaining = $qty;
        $total = 0.0;

        foreach ($product->availableBatches()->get() as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($batch->qty_remaining, $remaining);
            $total += $take * (float) $batch->buy_price;
            $remaining -= $take;
        }

        return round($total, 2);
    }
}