<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\SaleItem;
use App\Models\StockBatch;
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
     * Potong stok dari batch-batch tertua (FIFO) untuk memenuhi 1 baris SaleItem.
     * Mengembalikan array detail alokasi (untuk dipakai membuat SaleItemAllocation).
     *
     * @return array<int, array{stock_batch_id:int, qty_taken:int, buy_price_at_time:float, hpp_subtotal:float}>
     *
     * @throws RuntimeException jika stok tidak mencukupi
     */
    public function allocateFifo(Product $product, int $qtyNeeded, string $movementDate, SaleItem $saleItem): array
    {
        if ($qtyNeeded <= 0) {
            throw new RuntimeException('Qty penjualan harus lebih dari 0.');
        }

        // Lock baris batch supaya aman dari race condition kalau 2 transaksi
        // penjualan terjadi bersamaan (wajib dipanggil di dalam DB::transaction()).
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
                'reference_type'  => 'sale_item',
                'reference_id'    => $saleItem->id,
                'note'            => "Penjualan SO #{$saleItem->salesOrder->so_number} (FIFO dari batch #{$batch->id})",
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
        }

        if (isset($batch)) {
            $this->syncProductQtyOnHand($batch->product_id);
        }
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
     */
    public function batchBreakdown(Product $product)
    {
        return $product->stockBatches()
            ->where('qty_remaining', '>', 0)
            ->orderBy('batch_date')
            ->get(['id', 'batch_date', 'buy_price', 'qty_in', 'qty_remaining']);
    }
}
