<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleItem;
use App\Models\StockConversion;
use App\Models\StockConversionResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Otak fitur "Bongkar Unit".
 *
 * ================== CARA HPP DIBAGI (versi sekarang) ==================
 * Cuma ADA SATU cara, tidak ada lagi pilihan metode: HPP unit utuh dibagi ke
 * komponen secara PROPORSIONAL TERHADAP NILAI JUALNYA (relative sales value —
 * metode paling lazim untuk memecah biaya gabungan). Komponen yang harga
 * jualnya lebih tinggi menyerap HPP lebih besar.
 *
 * Bedanya dengan versi lama: harga jual yang dipakai TIDAK DIKETIK USER saat
 * membongkar. Harga jual di bisnis ini fluktuatif dan tergantung negosiasi,
 * jadi menebaknya di muka cuma bikin HPP meleset. Sistem memakai harga jual
 * RIIL terakhir dari Sales Order untuk tiap komponen, dan menghitung ulang
 * pembagian di balik layar setiap kali ada penjualan baru
 * (lihat syncAfterSale() yang dipanggil SalesOrderService).
 *
 * Urutan acuan harga jual per komponen:
 *   1. Harga jual terakhir komponen itu di Sales Order (yang paling sahih).
 *   2. Kalau komponen itu belum pernah terjual: rata-rata harga jual komponen
 *      lain dalam pembongkaran yang sama yang sudah diketahui.
 *   3. Kalau belum ada satu pun yang pernah terjual: dibagi rata per unit.
 * Begitu penjualan pertama terjadi, angka tebakan itu langsung tergantikan
 * angka riil dan HPP sisa stok menyesuaikan sendiri.
 *
 * ================== YANG TETAP DIJAGA ==================
 * - Pembongkaran BUKAN penjualan/pembelian: tidak ada uang keluar-masuk, tidak
 *   menyentuh cash flow, tidak melahirkan laba/rugi.
 * - total_hpp FINAL sejak FIFO jalan di langkah pertama dan tidak pernah berubah.
 *   Semua perhitungan ulang cuma memecah ULANG angka yang sama itu.
 * - Perhitungan ulang HANYA menyentuh sisa stok yang belum terjual. HPP yang
 *   sudah terlanjur keluar lewat penjualan sudah tersnapshot di
 *   SaleItemAllocation.buy_price_at_time, jadi laporan laba rugi transaksi lama
 *   TIDAK pernah berubah di belakang punggung user.
 * - Mode bertahap (draft) tetap ada, tapi TANPA ribet: seluruh HPP unit selalu
 *   dibagi habis ke komponen yang sudah tercatat. Kalau besok ketemu komponen
 *   lain, HPP tinggal dibagi ulang ke semua komponen — user tidak perlu
 *   menghitung atau menyisihkan apa pun.
 */
class StockConversionService
{
    public function __construct(
        protected StockService $stockService,
        protected DocumentNumberService $numberService,
    ) {}

    /**
     * @param array $data       ['conversion_date', 'source_product_id', 'source_qty',
     *                           'note', 'status'?]
     *                          'status' = 'draft' kalau belum semua komponen diketahui
     *                          (masih boleh ditambah lewat "Lanjutkan Bongkar").
     * @param array $components [['product_id', 'qty'], ...]
     */
    public function create(array $data, array $components): StockConversion
    {
        return DB::transaction(function () use ($data, $components) {
            $sourceProduct = Product::lockForUpdate()->findOrFail($data['source_product_id']);
            $isDraft = ($data['status'] ?? 'selesai') === 'draft';

            $this->guardComponents($sourceProduct, $components);

            $conversion = StockConversion::create([
                'conversion_number' => $data['conversion_number']
                    ?? $this->numberService->generate('BK', StockConversion::class, 'conversion_number'),
                'conversion_date'   => $data['conversion_date'],
                'source_product_id' => $sourceProduct->id,
                'source_qty'        => $data['source_qty'],
                'status'            => $isDraft ? 'draft' : 'selesai',
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

            // total_hpp final di sini dan TIDAK PERNAH berubah lagi.
            $conversion->update(['total_hpp' => $totalHpp]);

            // --- Langkah 2: lahirkan batch komponen (harga menyusul di rebalance) ---
            foreach ($components as $component) {
                $batch = $this->stockService->receiveFromConversion(
                    $conversion,
                    (int) $component['product_id'],
                    (int) $component['qty'],
                    0
                );

                $conversion->results()->create([
                    'product_id'     => $component['product_id'],
                    'stock_batch_id' => $batch->id,
                    'qty'            => (int) $component['qty'],
                    'ref_sell_price' => null,
                    'buy_price'      => 0,
                    'hpp_total'      => 0,
                ]);
            }

            // --- Langkah 3: bagi HPP proporsional terhadap harga jual acuan ---
            $this->rebalance($conversion);

            return $conversion->fresh(['sources.stockBatch', 'results.product', 'results.stockBatch', 'sourceProduct']);
        });
    }

    /**
     * Lanjutkan pembongkaran yang statusnya masih 'draft': tambah komponen baru
     * dan/atau tambah qty komponen yang sudah ada, lalu bagi ulang total_hpp
     * (yang nilainya tetap) ke semua komponen.
     *
     * @param array $meta         ['mark_complete'?]
     * @param array $existingRows [['result_id', 'qty'], ...]
     * @param array $newComponents [['product_id', 'qty'], ...]
     *
     * @throws RuntimeException kalau conversion sudah selesai atau qty dikurangi
     *                          di bawah yang sudah terjual.
     */
    public function continueConversion(
        StockConversion $conversion,
        array $meta,
        array $existingRows,
        array $newComponents
    ): StockConversion {
        return DB::transaction(function () use ($conversion, $meta, $existingRows, $newComponents) {
            $conversion = StockConversion::where('id', $conversion->id)->lockForUpdate()->firstOrFail();
            $conversion->load(['results.stockBatch', 'results.product', 'sourceProduct']);

            if (! $conversion->isDraft()) {
                throw new RuntimeException(
                    "Pembongkaran {$conversion->conversion_number} sudah selesai (semua komponen sudah tercatat), tidak bisa dilanjutkan lagi."
                );
            }

            $markComplete = (bool) ($meta['mark_complete'] ?? false);

            // Gabungkan daftar komponen lama + baru cuma untuk divalidasi bentuknya.
            $merged = [];
            foreach ($conversion->results as $result) {
                $merged[] = ['product_id' => $result->product_id, 'qty' => $result->qty];
            }
            foreach ($newComponents as $component) {
                $merged[] = $component;
            }

            $this->guardComponents($conversion->sourceProduct, $merged);

            // --- Komponen lama: qty boleh naik, tidak boleh turun di bawah terjual ---
            $editsByResultId = collect($existingRows)->keyBy('result_id');

            foreach ($conversion->results as $result) {
                $edit = $editsByResultId->get($result->id);

                if (! $edit || ! array_key_exists('qty', $edit)) {
                    continue;
                }

                $newQty = (int) $edit['qty'];

                if ($newQty === (int) $result->qty) {
                    continue;
                }

                $batch = $result->stockBatch;
                $used  = $batch ? ($batch->qty_in - $batch->qty_remaining) : 0;

                if ($newQty < $used) {
                    throw new RuntimeException(
                        "Qty komponen \"{$result->product->name}\" tidak bisa dikurangi sampai di bawah {$used} unit yang sudah terjual/terpakai. Boleh ditambah, tidak boleh dikurangi."
                    );
                }

                // HPP yang sudah keluar lewat penjualan dipertahankan apa adanya;
                // qty tambahan untuk sementara dinilai dengan harga yang berlaku
                // sekarang, lalu langsung dihitung ulang oleh rebalance() di bawah.
                $released = $result->hpp_released;

                $this->stockService->adjustConversionBatch(
                    $batch,
                    $newQty,
                    (float) $result->buy_price,
                    $conversion->conversion_date
                );

                $batch->refresh();

                $result->update([
                    'qty'       => $newQty,
                    'hpp_total' => round($released + $batch->qty_remaining * (float) $result->buy_price, 2),
                ]);
            }

            // --- Komponen baru: batch baru, harga menyusul di rebalance ---
            foreach ($newComponents as $component) {
                $batch = $this->stockService->receiveFromConversion(
                    $conversion,
                    (int) $component['product_id'],
                    (int) $component['qty'],
                    0
                );

                $conversion->results()->create([
                    'product_id'     => $component['product_id'],
                    'stock_batch_id' => $batch->id,
                    'qty'            => (int) $component['qty'],
                    'ref_sell_price' => null,
                    'buy_price'      => 0,
                    'hpp_total'      => 0,
                ]);
            }

            $conversion->update(['status' => $markComplete ? 'selesai' : 'draft']);

            $this->rebalance($conversion);

            return $conversion->fresh(['sources.stockBatch', 'results.product', 'results.stockBatch', 'sourceProduct']);
        });
    }

    /**
     * Dipanggil dari SalesOrderService SEBELUM stok dipotong FIFO, setiap kali
     * sebuah produk dijual. Kalau produk itu kebetulan komponen hasil bongkar
     * yang masih punya sisa stok, harga jual riil yang baru saja disepakati
     * dipakai untuk membagi ulang HPP pembongkaran yang bersangkutan.
     *
     * Ini inti dari "harga jualnya dihitung di balik layar setelah sales order":
     * user tidak pernah diminta menebak harga jual saat membongkar.
     *
     * Aman dipanggil untuk produk apa pun — kalau bukan komponen bongkar,
     * fungsinya tidak melakukan apa-apa.
     */
    public function syncAfterSale(int $productId, float $sellPrice): void
    {
        if ($sellPrice <= 0) {
            return;
        }

        $conversionIds = StockConversionResult::where('product_id', $productId)
            ->pluck('stock_conversion_id')
            ->unique();

        if ($conversionIds->isEmpty()) {
            return;
        }

        StockConversion::whereIn('id', $conversionIds)
            ->with(['results.stockBatch'])
            ->get()
            ->each(function (StockConversion $conversion) use ($productId, $sellPrice) {
                // Tidak ada gunanya menghitung ulang kalau semua komponennya
                // sudah habis terjual — tidak ada nilai yang tersisa untuk digeser.
                $hasRemaining = $conversion->results->contains(
                    fn($r) => $r->stockBatch && $r->stockBatch->qty_remaining > 0
                );

                if (! $hasRemaining) {
                    return;
                }

                $this->rebalance($conversion, [$productId => $sellPrice]);
            });
    }

    /**
     * Bagi ulang HPP pembongkaran ke komponen yang MASIH PUNYA SISA STOK,
     * proporsional terhadap harga jual acuan tiap komponen.
     *
     * Rumusnya menjaga dua hal sekaligus:
     * - HPP yang sudah keluar lewat penjualan (hpp_released) tidak diganggu.
     * - Sisa nilai yang belum tersalur (total_hpp dikurangi yang sudah keluar)
     *   selalu habis dibagi ke sisa unit, tanpa menciptakan/menghilangkan nilai.
     *
     * @param array<int, float> $priceOverrides harga jual yang baru diketahui
     *                                          (product_id => harga), dipakai
     *                                          saat dipanggil dari Sales Order.
     */
    public function rebalance(StockConversion $conversion, array $priceOverrides = []): void
    {
        $conversion->load(['results.stockBatch']);

        if ($conversion->results->isEmpty()) {
            return;
        }

        // Nilai yang sudah terlanjur keluar lewat penjualan — tidak boleh diutak-atik.
        $released = [];
        $totalReleased = 0.0;

        foreach ($conversion->results as $result) {
            $released[$result->id] = $result->hpp_released;
            $totalReleased += $released[$result->id];
        }

        $pool = round((float) $conversion->total_hpp - $totalReleased, 2);

        $active = $conversion->results
            ->filter(fn($r) => $r->stockBatch && $r->stockBatch->qty_remaining > 0)
            ->values();

        if ($active->isEmpty()) {
            // Semua komponen sudah habis terjual: tidak ada sisa unit untuk
            // menampung nilai. Sisa (biasanya 0) dicatat sebagai selisih.
            $conversion->update(['rounding_diff' => $pool]);

            return;
        }

        $prices = $this->referencePrices($active->pluck('product_id')->all(), $priceOverrides);

        $weights = $active
            ->map(fn($r) => $r->stockBatch->qty_remaining * ($prices[$r->product_id] ?? 0))
            ->all();

        $shares = $this->splitPool($pool, $weights);

        $distributed = 0.0;

        foreach ($active as $i => $result) {
            $qtyRemaining = (int) $result->stockBatch->qty_remaining;
            $buyPrice = round($shares[$i] / $qtyRemaining, 2);

            // Nilai yang benar-benar tersimpan di stok adalah buy_price * qty,
            // jadi itu juga yang dicatat supaya laporan bongkar sama persis
            // dengan nilai persediaan riil.
            $actual = round($buyPrice * $qtyRemaining, 2);
            $distributed += $actual;

            $this->stockService->repriceConversionBatch($result->stockBatch, $buyPrice);

            $result->update([
                'ref_sell_price' => $prices[$result->product_id] ?? null,
                'buy_price'      => $buyPrice,
                'hpp_total'      => round($released[$result->id] + $actual, 2),
            ]);
        }

        // Sisa pembulatan (paling banter beberapa rupiah) disimpan, bukan disembunyikan.
        $conversion->update(['rounding_diff' => round($pool - $distributed, 2)]);
    }

    /**
     * Batalkan pembongkaran: komponen ditarik kembali dari stok, unit utuh
     * dikembalikan ke batch asalnya. Hanya boleh kalau SEMUA komponen hasil
     * bongkar masih utuh (belum terjual, belum dibongkar lagi).
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

            foreach ($conversion->results as $result) {
                if ($result->stockBatch) {
                    $this->stockService->removeConversionBatch($result->stockBatch);
                }
            }

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
     * Harga jual acuan per komponen, dengan urutan: harga jual riil terakhir di
     * Sales Order -> rata-rata komponen lain yang sudah diketahui -> bagi rata.
     *
     * @param  array<int, int>   $productIds
     * @param  array<int, float> $overrides harga yang baru saja disepakati di SO
     *                                      yang sedang diproses (belum tersimpan)
     * @return array<int, float>
     */
    protected function referencePrices(array $productIds, array $overrides = []): array
    {
        $productIds = array_values(array_unique($productIds));

        $lastPrices = SaleItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sale_items.sales_order_id')
            ->whereIn('sale_items.product_id', $productIds)
            ->orderBy('sales_orders.so_date')
            ->orderBy('sale_items.id')
            ->get(['sale_items.product_id', 'sale_items.sell_price'])
            ->groupBy('product_id')
            ->map(fn($rows) => (float) $rows->last()->sell_price)
            ->all();

        $prices = [];
        foreach ($productIds as $id) {
            $price = $overrides[$id] ?? ($lastPrices[$id] ?? null);

            if ($price !== null && $price > 0) {
                $prices[$id] = (float) $price;
            }
        }

        // Komponen yang belum pernah terjual: pakai rata-rata yang sudah diketahui
        // sebagai penahan sementara. Begitu ia terjual pertama kali, angka riilnya
        // langsung menggantikan ini lewat syncAfterSale().
        $fallback = count($prices) > 0
            ? array_sum($prices) / count($prices)
            : 1.0;

        foreach ($productIds as $id) {
            $prices[$id] ??= $fallback;
        }

        return $prices;
    }

    /**
     * Bagi $pool mengikuti bobot, dengan baris terakhir menerima SISA supaya
     * total pembagian tidak pernah meleset dari $pool.
     *
     * @param  array<int, float> $weights
     * @return array<int, float>
     */
    protected function splitPool(float $pool, array $weights): array
    {
        $totalWeight = array_sum($weights);
        $count = count($weights);

        // Semua bobot 0 (mis. semua harga acuan 0): bagi rata per baris.
        if ($totalWeight <= 0) {
            $weights = array_fill(0, $count, 1);
            $totalWeight = $count;
        }

        $shares = [];
        $running = 0.0;

        foreach ($weights as $i => $weight) {
            if ($i === $count - 1) {
                $shares[$i] = round($pool - $running, 2);
                break;
            }

            $shares[$i] = round($pool * ($weight / $totalWeight), 2);
            $running += $shares[$i];
        }

        return $shares;
    }

    /** Validasi yang tidak bisa diwakili aturan FormRequest biasa. */
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