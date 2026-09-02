<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        protected StockService $stockService,
        protected CashFlowService $cashFlowService,
        protected DocumentNumberService $numberService,
    ) {}

    /**
     * Buat PO lengkap dengan item-itemnya sekaligus, langsung masukkan
     * barang ke stok (buat stock_batch per item), dan catat pembayaran
     * awal jika ada (cash/partial).
     *
     * @param array $data ['supplier_id', 'po_date', 'note'] — 'po_number' opsional,
     *                     kalau tidak diisi akan digenerate otomatis: PO/{Bulan Romawi}/{Tahun}/{Urut}
     * @param array $items [['product_id', 'qty', 'buy_price'], ...]
     * @param float|null $initialPayment jumlah bayar awal (null = belum bayar sama sekali)
     */
    public function create(array $data, array $items, ?float $initialPayment = null, string $paymentMethod = 'cash'): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $initialPayment, $paymentMethod) {
            $totalAmount = 0;
            foreach ($items as $item) {
                $totalAmount += $item['qty'] * $item['buy_price'];
            }

            $data['po_number'] ??= $this->numberService->generate('PO', PurchaseOrder::class, 'po_number');

            $po = PurchaseOrder::create([
                ...$data,
                'total_amount'   => $totalAmount,
                'paid_amount'    => 0,
                'payment_status' => 'unpaid',
            ]);

            foreach ($items as $item) {
                $poItem = $po->items()->create([
                    'product_id' => $item['product_id'],
                    'qty'        => $item['qty'],
                    'buy_price'  => $item['buy_price'],
                    'subtotal'   => $item['qty'] * $item['buy_price'],
                ]);

                // Barang langsung masuk stok terlepas dari status pembayaran
                $this->stockService->receiveFromPurchaseItem($poItem, $po->po_date);
            }

            if ($initialPayment && $initialPayment > 0) {
                $this->addPayment($po, $po->po_date, $initialPayment, $paymentMethod, 'Pembayaran awal saat PO dibuat');
            }

            return $po->fresh(['items', 'payments']);
        });
    }

    /**
     * Tambah pembayaran termin ke PO yang sudah ada. Menyinkronkan
     * paid_amount & payment_status, dan mencatat kas keluar.
     */
    public function addPayment(PurchaseOrder $po, string $date, float $amount, string $method = 'cash', ?string $note = null): PurchasePayment
    {
        return DB::transaction(function () use ($po, $date, $amount, $method, $note) {
            $remaining = $po->total_amount - $po->paid_amount;

            if ($amount > $remaining) {
                throw new \RuntimeException(
                    "Jumlah pembayaran (Rp {$amount}) melebihi sisa hutang (Rp {$remaining}) untuk PO #{$po->po_number}."
                );
            }

            $payment = $po->payments()->create([
                'payment_date' => $date,
                'amount'       => $amount,
                'method'       => $method,
                'note'         => $note,
            ]);

            $po->paid_amount += $amount;
            $po->payment_status = $this->resolvePaymentStatus($po->total_amount, $po->paid_amount);
            $po->save();

            $this->cashFlowService->recordOut(
                $date,
                $amount,
                $payment,
                "Pembayaran PO #{$po->po_number} ke {$po->supplier->name}"
            );

            return $payment;
        });
    }

    /**
     * Edit pembayaran yang sudah tercatat (koreksi salah input nominal/tanggal/dll).
     * paid_amount & payment_status PO dihitung ulang otomatis, dan entry cash_flow
     * terkait ikut disinkronkan — semua dalam satu transaksi supaya konsisten.
     *
     * @param array $data ['payment_date', 'amount', 'method', 'note']
     *
     * @throws \RuntimeException kalau nominal baru bikin total pembayaran melebihi total PO
     */
    public function updatePayment(PurchasePayment $payment, array $data): PurchasePayment
    {
        return DB::transaction(function () use ($payment, $data) {
            $po = $payment->purchaseOrder()->lockForUpdate()->first();

            $oldAmount = (float) $payment->amount;
            $newAmount = (float) $data['amount'];
            $newPaidTotal = (float) $po->paid_amount - $oldAmount + $newAmount;

            if ($newPaidTotal > (float) $po->total_amount) {
                $maxAllowed = $oldAmount + ((float) $po->total_amount - (float) $po->paid_amount);
                throw new \RuntimeException(
                    "Nominal baru (Rp {$newAmount}) membuat total pembayaran melebihi total PO. Maksimal untuk pembayaran ini: Rp {$maxAllowed}."
                );
            }

            $payment->update([
                'payment_date' => $data['payment_date'],
                'amount'       => $newAmount,
                'method'       => $data['method'],
                'note'         => $data['note'] ?? null,
            ]);

            $po->paid_amount = $newPaidTotal;
            $po->payment_status = $this->resolvePaymentStatus($po->total_amount, $newPaidTotal);
            $po->save();

            $this->cashFlowService->updateForSource($payment, $data['payment_date'], $newAmount);

            return $payment->fresh();
        });
    }

    /**
     * Update PO yang sudah ada: ganti data utama + replace semua item lama
     * dengan item baru (batch stok lama dihapus, batch baru dibuat).
     * HANYA boleh dipanggil selagi PO belum dibayar sama sekali dan
     * belum ada barangnya yang terlanjur terjual.
     *
     * @param array $data ['supplier_id', 'po_date', 'note']
     * @param array $items [['product_id', 'qty', 'buy_price'], ...]
     *
     * @throws \RuntimeException kalau PO sudah dibayar atau ada batch yang sudah kepakai
     */
    public function update(PurchaseOrder $po, array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $items) {
            $po->loadMissing('items.stockBatch');

            $this->guardCanModify($po);

            foreach ($po->items as $item) {
                $this->stockService->removeUnusedPurchaseBatch($item);
                $item->delete();
            }

            $totalAmount = 0;
            foreach ($items as $item) {
                $totalAmount += $item['qty'] * $item['buy_price'];
            }

            $po->update([
                'supplier_id'  => $data['supplier_id'],
                'po_date'      => $data['po_date'],
                'note'         => $data['note'] ?? null,
                'total_amount' => $totalAmount,
            ]);

            foreach ($items as $item) {
                $poItem = $po->items()->create([
                    'product_id' => $item['product_id'],
                    'qty'        => $item['qty'],
                    'buy_price'  => $item['buy_price'],
                    'subtotal'   => $item['qty'] * $item['buy_price'],
                ]);

                $this->stockService->receiveFromPurchaseItem($poItem, $po->po_date);
            }

            return $po->fresh(['items', 'payments']);
        });
    }

    /**
     * Hapus PO beserta item & batch stoknya. HANYA boleh dipanggil selagi
     * PO belum dibayar sama sekali dan belum ada barangnya yang terjual.
     *
     * @throws \RuntimeException kalau PO sudah dibayar atau ada batch yang sudah kepakai
     */
    public function delete(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po->loadMissing('items.stockBatch');

            $this->guardCanModify($po);

            foreach ($po->items as $item) {
                $this->stockService->removeUnusedPurchaseBatch($item);
            }

            // items ikut terhapus otomatis (cascadeOnDelete di migration purchase_order_items)
            $po->delete();
        });
    }

    /**
     * Pastikan PO boleh diedit/dihapus: belum dibayar sama sekali, DAN
     * semua barangnya belum ada yang terjual (batch masih utuh).
     */
    protected function guardCanModify(PurchaseOrder $po): void
    {
        if (! $po->canBeModified()) {
            throw new \RuntimeException(
                "PO #{$po->po_number} sudah ada pembayaran, tidak bisa diedit/dihapus lagi."
            );
        }

        foreach ($po->items as $item) {
            if ($this->stockService->isPurchaseItemBatchUsed($item)) {
                throw new \RuntimeException(
                    "PO #{$po->po_number} tidak bisa diedit/dihapus karena barang \"{$item->product->name}\" dari PO ini sudah terlanjur terjual."
                );
            }
        }
    }

    protected function resolvePaymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }
}
