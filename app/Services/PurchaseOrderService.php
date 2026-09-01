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

    protected function resolvePaymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }
}
