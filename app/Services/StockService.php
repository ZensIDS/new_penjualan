<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleItem;
use App\Models\SaleItemAllocation;
use App\Models\SaleReturnItemAllocation;
use App\Models\StockBatch;
use App\Models\StockConversion;
use App\Models\StockConversionSource;
use App\Models\StockMovement;
use RuntimeException;

class StockService
{
    /**
     * Buat stock_batch baru dari 1 baris purchase_order_item.
     * Dipanggil setelah PurchaseOrderItem tersimpan (barang dianggap
     * langsung masuk stok, sesuai aturan bisnis: barang bisa masuk
     * meski pembayaran belum lunas).
     */
    public function receiveFromPurchaseItem(PurchaseOrderItem $item, ?string $batchDate = null): StockBatch
    {
        $batchDate ??= $item->purchaseOrder->po_date;

        $batch = StockBatch::create([
            'product_id'              => $item->product_id,
            'purchase_order_item_id'  => $item->id,
            'origin_type'             => 'purchase',
            'batch_date'              => $batchDate,
            'buy_price'                => $item->buy_price,
            'qty_in'                   => $item->qty,
            'qty_remaining'            => $item->qty,
        ]);

        StockMovement::create([
            'product_id'      => $item->product_id,
            'stock_batch_id'  => $batch->id,
            'type'            => 'in',
            'qty'             => $item->qty,
            'movement_date'   => $batchDate,
            'reference_type'  => 'purchase_order_item',
            'reference_id'    => $item->id,
            'note'            => "Stok masuk dari PO #{$item->purchaseOrder->po_number}",
        ]);

        $this->syncProductQtyOnHand($item->product_id);

        return $batch;
    }

    /**
     * Buat stock_batch baru untuk 1 komponen hasil pembongkaran unit utuh.
     *
     * Beda dari receiveFromPurchaseItem(): batch ini TIDAK punya purchase_order_item_id
     * (tidak ada PO-nya — barangnya sudah dibeli sebelumnya dalam wujud utuh).
     * buy_price-nya adalah hasil pembagian HPP unit utuh, dihitung oleh
     * StockConversionService, bukan harga beli dari supplier.
     */
    public function receiveFromConversion(
        StockConversion $conversion,
        int $productId,
        int $qty,
        float $buyPrice
    ): StockBatch {
        $batch = StockBatch::create([
            'product_id'             => $productId,
            'purchase_order_item_id' => null,
            'origin_type'            => 'conversion',
            'batch_date'             => $conversion->conversion_date,
            'buy_price'              => $buyPrice,
            'qty_in'                 => $qty,
            'qty_remaining'          => $qty,
        ]);

        StockMovement::create([
            'product_id'     => $productId,
            'stock_batch_id' => $batch->id,
            'type'           => 'in',
            'qty'            => $qty,
            'movement_date'  => $conversion->conversion_date,
            'reference_type' => 'stock_conversion',
            'reference_id'   => $conversion->id,
            'note'           => "Hasil bongkar {$conversion->conversion_number} dari {$conversion->sourceProduct->name}",
        ]);

        $this->syncProductQtyOnHand($productId);

        return $batch;
    }

    /**
     * Potong stok dari batch-batch tertua (FIFO) untuk memenuhi 1 baris SaleItem.
     * Mengembalikan array detail alokasi (untuk dipakai membuat SaleItemAllocation).
     *
     * @return array<int, array{stock_batch_id:int, qty_taken:int, buy_price_at_time:float, hpp_subtotal:float}>
     *
     * @throws RuntimeException jika stok tidak mencukupi
     */
    public function allocateFifo(Product $product, int $qtyNeeded, string $movementDate, SaleItem $saleItem): array
    {
        return $this->allocateFifoRaw(
            $product,
            $qtyNeeded,
            $movementDate,
            'sale_item',
            $saleItem->id,
            fn(StockBatch $batch) => "Penjualan SO #{$saleItem->salesOrder->so_number} (FIFO dari batch #{$batch->id})"
        );
    }

    /**
     * Potong stok FIFO untuk keperluan PEMBONGKARAN unit utuh. Mekanismenya persis
     * sama dengan penjualan (batch tertua duluan, HPP diambil dari batch asal),
     * bedanya cuma referensi & catatan mutasinya — barangnya tidak keluar gudang,
     * tapi berubah wujud jadi komponen (lihat StockConversionService).
     */
    public function allocateFifoForConversion(
        Product $product,
        int $qtyNeeded,
        string $movementDate,
        StockConversion $conversion
    ): array {
        return $this->allocateFifoRaw(
            $product,
            $qtyNeeded,
            $movementDate,
            'stock_conversion',
            $conversion->id,
            fn(StockBatch $batch) => "Dibongkar via {$conversion->conversion_number} (FIFO dari batch #{$batch->id})"
        );
    }

    /**
     * Mesin FIFO generik yang dipakai bersama oleh penjualan dan pembongkaran.
     * Sengaja dipisah supaya aturan FIFO, locking, dan validasi kecukupan stok
     * cuma ditulis SEKALI — kalau logikanya berubah, semua pemakainya ikut berubah.
     *
     * @param callable(StockBatch): string $noteResolver catatan untuk stock_movements
     *
     * @throws RuntimeException jika stok tidak mencukupi
     */
    public function allocateFifoRaw(
        Product $product,
        int $qtyNeeded,
        string $movementDate,
        string $referenceType,
        int $referenceId,
        callable $noteResolver
    ): array {
        if ($qtyNeeded <= 0) {
            throw new RuntimeException('Qty harus lebih dari 0.');
        }

        // Lock baris batch supaya aman dari race condition kalau 2 transaksi
        // terjadi bersamaan (wajib dipanggil di dalam DB::transaction()).
        $batches = $product->availableBatches()->lockForUpdate()->get();

        $totalAvailable = $batches->sum('qty_remaining');
        if ($totalAvailable < $qtyNeeded) {
            throw new RuntimeException(
                "Stok {$product->name} tidak mencukupi. Diminta {$qtyNeeded}, tersedia {$totalAvailable}."
            );
        }

        $remainingToTake = $qtyNeeded;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remainingToTake <= 0) {
                break;
            }

            $qtyFromThisBatch = min($batch->qty_remaining, $remainingToTake);

            // Kurangi sisa batch
            $batch->qty_remaining -= $qtyFromThisBatch;
            $batch->save();

            $hppSubtotal = $qtyFromThisBatch * $batch->buy_price;

            $allocations[] = [
                'stock_batch_id'     => $batch->id,
                'qty_taken'          => $qtyFromThisBatch,
                'buy_price_at_time'  => $batch->buy_price,
                'hpp_subtotal'       => $hppSubtotal,
            ];

            StockMovement::create([
                'product_id'      => $product->id,
                'stock_batch_id'  => $batch->id,
                'type'            => 'out',
                'qty'             => $qtyFromThisBatch,
                'movement_date'   => $movementDate,
                'reference_type'  => $referenceType,
                'reference_id'    => $referenceId,
                'note'            => $noteResolver($batch),
            ]);

            $remainingToTake -= $qtyFromThisBatch;
        }

        $this->syncProductQtyOnHand($product->id);

        return $allocations;
    }

    /**
     * Kembalikan qty ke batch asal (dipakai untuk pembatalan/retur penjualan).
     * Menerima kembali array allocation records (SaleItemAllocation collection).
     */
    public function reverseAllocations(iterable $allocations, string $movementDate, string $note = 'Retur/pembatalan penjualan'): void
    {
        $affectedProductIds = [];

        foreach ($allocations as $allocation) {
            $batch = $allocation->stockBatch;
            $batch->qty_remaining += $allocation->qty_taken;
            $batch->save();

            StockMovement::create([
                'product_id'      => $batch->product_id,
                'stock_batch_id'  => $batch->id,
                'type'            => 'in',
                'qty'             => $allocation->qty_taken,
                'movement_date'   => $movementDate,
                'reference_type'  => 'sale_item',
                'reference_id'    => $allocation->sale_item_id,
                'note'            => $note,
            ]);

            $affectedProductIds[$batch->product_id] = true;
        }

        // Sync qty_on_hand untuk SETIAP produk yang batch-nya kena, bukan cuma
        // produk dari alokasi terakhir — 1 SO bisa berisi banyak produk berbeda.
        foreach (array_keys($affectedProductIds) as $productId) {
            $this->syncProductQtyOnHand($productId);
        }
    }

    /**
     * Kembalikan potongan batch sumber saat sebuah PEMBONGKARAN dibatalkan.
     * Aman dipanggil tanpa validasi batas atas (hanya menambah qty_remaining),
     * tapi pemanggil (StockConversionService) WAJIB memastikan dulu batch-batch
     * komponen hasilnya belum kepakai — kalau sudah, bongkarnya tidak boleh dibatalkan.
     */
    public function reverseConversionSource(StockConversionSource $source, string $movementDate, string $note): void
    {
        $batch = StockBatch::where('id', $source->stock_batch_id)->lockForUpdate()->first();

        if (! $batch) {
            throw new RuntimeException('Batch stok asal pembongkaran ini tidak ditemukan.');
        }

        $batch->qty_remaining += $source->qty_taken;
        $batch->save();

        StockMovement::create([
            'product_id'     => $batch->product_id,
            'stock_batch_id' => $batch->id,
            'type'           => 'in',
            'qty'            => $source->qty_taken,
            'movement_date'  => $movementDate,
            'reference_type' => 'stock_conversion',
            'reference_id'   => $source->stock_conversion_id,
            'note'           => $note,
        ]);

        $this->syncProductQtyOnHand($batch->product_id);
    }

    /**
     * Hapus 1 batch hasil pembongkaran beserta jejak mutasinya. HANYA boleh
     * dipanggil kalau batch belum tersentuh sama sekali (qty_remaining == qty_in),
     * dicek di StockConversionService.
     */
    public function removeConversionBatch(StockBatch $batch): void
    {
        $productId = $batch->product_id;

        StockMovement::where('stock_batch_id', $batch->id)->delete();
        $batch->delete();

        $this->syncProductQtyOnHand($productId);
    }

    /**
     * Kembalikan qty ke SATU batch tertentu (lewat SaleItemAllocation asalnya)
     * karena customer meretur sebagian/seluruh qty dari baris SO tsb. Beda dari
     * reverseAllocations() (yang selalu mengembalikan qty_taken PENUH — dipakai
     * utk edit/hapus SO), method ini menerima qty parsial sehingga bisa dipakai
     * utk retur sebagian, dan hasilnya dicatat via $returnAlloc (SaleReturnItemAllocation)
     * supaya jejak "sudah diretur berapa dari alokasi ini" tetap akurat.
     */
    public function returnFromCustomer(SaleItemAllocation $allocation, int $qty, string $returnDate, SaleReturnItemAllocation $returnAlloc): void
    {
        if ($qty <= 0) {
            throw new RuntimeException('Qty retur harus lebih dari 0.');
        }

        // Lock baris batch supaya aman dari race condition (wajib dipanggil
        // di dalam DB::transaction()).
        $batch = StockBatch::where('id', $allocation->stock_batch_id)->lockForUpdate()->first();

        if (! $batch) {
            throw new RuntimeException('Batch stok asal untuk alokasi ini tidak ditemukan.');
        }

        $batch->qty_remaining += $qty;
        $batch->save();

        StockMovement::create([
            'product_id'      => $batch->product_id,
            'stock_batch_id'  => $batch->id,
            'type'            => 'in',
            'qty'             => $qty,
            'movement_date'   => $returnDate,
            'reference_type'  => 'sale_return_item',
            'reference_id'    => $returnAlloc->sale_return_item_id,
            'note'            => "Retur penjualan dari customer — SO #{$allocation->saleItem->salesOrder->so_number}",
        ]);

        $this->syncProductQtyOnHand($batch->product_id);
    }

    /**
     * Kebalikan dari returnFromCustomer() — keluarkan lagi qty dari batch saat
     * sebuah retur SO dihapus/dibatalkan. TIDAK selalu aman seperti kebalikan
     * retur PO: barang yang sudah kembali ke stok bisa saja sudah kadung terjual
     * lagi atau diretur ke supplier, jadi divalidasi dulu terhadap qty_remaining
     * batch saat ini.
     *
     * @throws RuntimeException kalau qty batch tidak cukup untuk dikeluarkan lagi
     */
    public function undoReturnFromCustomer(SaleReturnItemAllocation $returnAlloc, string $movementDate): void
    {
        $batch = StockBatch::where('id', $returnAlloc->stock_batch_id)->lockForUpdate()->first();

        if (! $batch || $returnAlloc->qty > $batch->qty_remaining) {
            $available = $batch->qty_remaining ?? 0;
            throw new RuntimeException(
                "Retur tidak bisa dibatalkan: stok produk \"{$returnAlloc->saleReturnItem->product->name}\" dari batch ini sudah kadung terjual lagi atau diretur ke supplier ({$available} tersisa, butuh {$returnAlloc->qty})."
            );
        }

        $batch->qty_remaining -= $returnAlloc->qty;
        $batch->save();

        StockMovement::create([
            'product_id'      => $batch->product_id,
            'stock_batch_id'  => $batch->id,
            'type'            => 'out',
            'qty'             => $returnAlloc->qty,
            'movement_date'   => $movementDate,
            'reference_type'  => 'sale_return_item',
            'reference_id'    => $returnAlloc->sale_return_item_id,
            'note'            => 'Pembatalan retur penjualan',
        ]);

        $this->syncProductQtyOnHand($batch->product_id);
    }

    /**
     * Keluarkan qty dari batch stok hasil 1 baris PO item karena barang
     * dikembalikan (retur) ke supplier. Batch harus milik item yang sama,
     * dan hanya boleh sebesar qty_remaining yang masih ada di batch itu —
     * kalau barangnya sudah kadung terjual ATAU sudah kadung dibongkar,
     * sisa yang bisa diretur otomatis lebih kecil.
     *
     * @throws RuntimeException kalau qty retur melebihi sisa batch yang ada
     */
    public function returnToSupplier(PurchaseOrderItem $item, int $qty, string $returnDate, PurchaseReturnItem $returnItem): void
    {
        if ($qty <= 0) {
            throw new RuntimeException('Qty retur harus lebih dari 0.');
        }

        // Lock baris batch supaya aman dari race condition kalau ada transaksi
        // penjualan/retur lain terjadi bersamaan (wajib dipanggil di dalam DB::transaction()).
        $batch = StockBatch::where('purchase_order_item_id', $item->id)->lockForUpdate()->first();

        if (! $batch || $qty > $batch->qty_remaining) {
            $available = $batch->qty_remaining ?? 0;
            throw new RuntimeException(
                "Qty retur untuk \"{$item->product->name}\" ({$qty}) melebihi sisa stok yang masih ada dari PO ini ({$available}). Barang yang sudah terjual atau sudah dibongkar tidak bisa diretur ke supplier."
            );
        }

        $batch->qty_remaining -= $qty;
        $batch->save();

        StockMovement::create([
            'product_id'      => $item->product_id,
            'stock_batch_id'  => $batch->id,
            'type'            => 'out',
            'qty'             => $qty,
            'movement_date'   => $returnDate,
            'reference_type'  => 'purchase_return_item',
            'reference_id'    => $returnItem->id,
            'note'            => "Retur ke supplier — PO #{$item->purchaseOrder->po_number}",
        ]);

        $this->syncProductQtyOnHand($item->product_id);
    }

    /**
     * Kebalikan dari returnToSupplier() — kembalikan qty ke batch asal saat
     * sebuah retur PO dihapus/dibatalkan. Selalu aman (menambah qty_remaining,
     * tidak pernah bikin negatif), jadi tidak perlu validasi batas atas.
     */
    public function undoReturnToSupplier(PurchaseReturnItem $returnItem, string $movementDate): void
    {
        $poItem = $returnItem->purchaseOrderItem;

        // Lock baris batch supaya aman dari race condition (wajib dipanggil
        // di dalam DB::transaction()).
        $batch = StockBatch::where('purchase_order_item_id', $poItem->id)->lockForUpdate()->first();

        if (! $batch) {
            throw new RuntimeException(
                "Batch stok untuk produk \"{$returnItem->product->name}\" tidak ditemukan, retur tidak bisa dibatalkan."
            );
        }

        $batch->qty_remaining += $returnItem->qty;
        $batch->save();

        StockMovement::create([
            'product_id'      => $returnItem->product_id,
            'stock_batch_id'  => $batch->id,
            'type'            => 'in',
            'qty'             => $returnItem->qty,
            'movement_date'   => $movementDate,
            'reference_type'  => 'purchase_return_item',
            'reference_id'    => $returnItem->id,
            'note'            => "Pembatalan retur {$returnItem->purchaseReturn->return_number} — PO #{$poItem->purchaseOrder->po_number}",
        ]);

        $this->syncProductQtyOnHand($returnItem->product_id);
    }

    /**
     * Cek apakah batch stok hasil 1 baris PO item sudah kepakai (terjual
     * ATAU dibongkar) sebagian/seluruhnya. Dipakai buat menolak edit/hapus PO
     * yang barangnya sudah kadung dipakai, walau PO itu sendiri belum dibayar
     * sama sekali (status pembayaran & pergerakan stok itu dua hal terpisah).
     */
    public function isPurchaseItemBatchUsed(PurchaseOrderItem $item): bool
    {
        $batch = $item->stockBatch;

        if (! $batch) {
            return false;
        }

        return $batch->qty_remaining < $batch->qty_in;
    }

    /**
     * Hapus 1 batch stok hasil PO item beserta jejak stock_movements-nya,
     * lalu sync ulang qty_on_hand produknya. HANYA boleh dipanggil kalau
     * isPurchaseItemBatchUsed() sudah dipastikan false (batch belum kepakai
     * sama sekali) — dicek di pemanggil (PurchaseOrderService).
     */
    public function removeUnusedPurchaseBatch(PurchaseOrderItem $item): void
    {
        $batch = $item->stockBatch;

        if (! $batch) {
            return;
        }

        $productId = $batch->product_id;

        StockMovement::where('reference_type', 'purchase_order_item')
            ->where('reference_id', $item->id)
            ->delete();

        $batch->delete();

        $this->syncProductQtyOnHand($productId);
    }

    /**
     * Sinkronkan kolom cache products.qty_on_hand dari SUM(stock_batches.qty_remaining).
     * Selalu panggil ini setiap kali qty_remaining berubah, jangan pernah
     * update qty_on_hand secara manual di tempat lain.
     */
    public function syncProductQtyOnHand(int $productId): void
    {
        $total = StockBatch::where('product_id', $productId)->sum('qty_remaining');
        Product::where('id', $productId)->update(['qty_on_hand' => $total]);
    }

    /**
     * Breakdown stok per batch untuk 1 produk (dipakai Laporan Stok).
     * origin_type ikut dikirim supaya tampilan bisa menandai batch mana yang
     * lahir dari pembelian dan mana yang lahir dari pembongkaran unit utuh.
     */
    public function batchBreakdown(Product $product)
    {
        return $product->stockBatches()
            ->where('qty_remaining', '>', 0)
            ->orderBy('batch_date')
            ->get(['id', 'batch_date', 'buy_price', 'qty_in', 'qty_remaining', 'origin_type']);
    }
}