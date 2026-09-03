<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesPayment;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        protected StockService $stockService,
        protected CashFlowService $cashFlowService,
        protected DocumentNumberService $numberService,
    ) {}

    /**
     * Buat SO lengkap dengan item-itemnya, otomatis alokasikan stok
     * secara FIFO per item dan hitung HPP riil.
     *
     * @param array $data ['customer_id', 'so_date', 'note'] — 'so_number' opsional,
     *                     kalau tidak diisi akan digenerate otomatis: SO/{Bulan Romawi}/{Tahun}/{Urut}
     * @param array $items [['product_id', 'qty', 'sell_price'], ...]
     */
    public function create(array $data, array $items, ?float $initialPayment = null, string $paymentMethod = 'cash'): SalesOrder
    {
        return DB::transaction(function () use ($data, $items, $initialPayment, $paymentMethod) {
            $totalAmount = 0;
            foreach ($items as $item) {
                $totalAmount += $item['qty'] * $item['sell_price'];
            }

            $data['so_number'] ??= $this->numberService->generate('SO', SalesOrder::class, 'so_number');

            $so = SalesOrder::create([
                ...$data,
                'total_amount'   => $totalAmount,
                'total_hpp'      => 0, // diisi setelah alokasi FIFO tiap item
                'paid_amount'    => 0,
                'payment_status' => 'unpaid',
            ]);

            $totalHpp = 0;

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                $saleItem = $so->items()->create([
                    'product_id'   => $product->id,
                    'qty'          => $item['qty'],
                    'sell_price'   => $item['sell_price'],
                    'subtotal'     => $item['qty'] * $item['sell_price'],
                    'hpp_subtotal' => 0,
                ]);

                // Inti FIFO: potong stok dari batch tertua, dapatkan rincian alokasi
                $allocations = $this->stockService->allocateFifo(
                    $product,
                    $item['qty'],
                    $so->so_date,
                    $saleItem
                );

                $itemHpp = 0;
                foreach ($allocations as $alloc) {
                    $saleItem->allocations()->create($alloc);
                    $itemHpp += $alloc['hpp_subtotal'];
                }

                $saleItem->update(['hpp_subtotal' => $itemHpp]);
                $totalHpp += $itemHpp;
            }

            $so->update(['total_hpp' => $totalHpp]);

            if ($initialPayment && $initialPayment > 0) {
                $this->addPayment($so, $so->so_date, $initialPayment, $paymentMethod, 'Pembayaran awal saat transaksi');
            }

            return $so->fresh(['items.allocations', 'payments']);
        });
    }

    public function addPayment(SalesOrder $so, string $date, float $amount, string $method = 'cash', ?string $note = null): SalesPayment
    {
        return DB::transaction(function () use ($so, $date, $amount, $method, $note) {
            $remaining = $so->total_amount - $so->paid_amount;

            if ($amount > $remaining) {
                throw new \RuntimeException(
                    "Jumlah pembayaran (Rp {$amount}) melebihi sisa piutang (Rp {$remaining}) untuk SO #{$so->so_number}."
                );
            }

            $payment = $so->payments()->create([
                'payment_date' => $date,
                'amount'       => $amount,
                'method'       => $method,
                'note'         => $note,
            ]);

            $so->paid_amount += $amount;
            $so->payment_status = $this->resolvePaymentStatus($so->total_amount, $so->paid_amount);
            $so->save();

            $this->cashFlowService->recordIn(
                $date,
                $amount,
                $payment,
                "Pembayaran SO #{$so->so_number} dari {$so->customer->name}"
            );

            return $payment;
        });
    }

    /**
     * Edit pembayaran yang sudah tercatat (koreksi salah input nominal/tanggal/dll).
     * paid_amount & payment_status SO dihitung ulang otomatis, dan entry cash_flow
     * terkait ikut disinkronkan — semua dalam satu transaksi supaya konsisten.
     *
     * @param array $data ['payment_date', 'amount', 'method', 'note']
     *
     * @throws \RuntimeException kalau nominal baru bikin total pembayaran melebihi total SO
     */
    public function updatePayment(SalesPayment $payment, array $data): SalesPayment
    {
        return DB::transaction(function () use ($payment, $data) {
            $so = $payment->salesOrder()->lockForUpdate()->first();

            $oldAmount = (float) $payment->amount;
            $newAmount = (float) $data['amount'];
            $newPaidTotal = (float) $so->paid_amount - $oldAmount + $newAmount;

            if ($newPaidTotal > (float) $so->total_amount) {
                $maxAllowed = $oldAmount + ((float) $so->total_amount - (float) $so->paid_amount);
                throw new \RuntimeException(
                    "Nominal baru (Rp {$newAmount}) membuat total pembayaran melebihi total SO. Maksimal untuk pembayaran ini: Rp {$maxAllowed}."
                );
            }

            $payment->update([
                'payment_date' => $data['payment_date'],
                'amount'       => $newAmount,
                'method'       => $data['method'],
                'note'         => $data['note'] ?? null,
            ]);

            $so->paid_amount = $newPaidTotal;
            $so->payment_status = $this->resolvePaymentStatus($so->total_amount, $newPaidTotal);
            $so->save();

            $this->cashFlowService->updateForSource($payment, $data['payment_date'], $newAmount);

            return $payment->fresh();
        });
    }

    /**
     * Update SO yang sudah ada: ganti data utama + replace semua item lama
     * dengan item baru. Alokasi FIFO lama dikembalikan ke batch asal dulu,
     * baru item baru dialokasikan ulang. HANYA boleh dipanggil selagi SO
     * belum dibayar sama sekali.
     *
     * @param array $data ['customer_id', 'so_date', 'note', 'source_id']
     * @param array $items [['product_id', 'qty', 'sell_price'], ...]
     *
     * @throws \RuntimeException kalau SO sudah dibayar, atau stok tidak cukup untuk item baru
     */
    public function update(SalesOrder $so, array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($so, $data, $items) {
            $so->loadMissing('items.allocations');

            $this->guardCanModify($so);

            foreach ($so->items as $saleItem) {
                $this->stockService->reverseAllocations(
                    $saleItem->allocations,
                    $so->so_date,
                    "Edit transaksi SO #{$so->so_number} — kembalikan alokasi lama"
                );
                $saleItem->delete(); // allocations ikut terhapus (cascadeOnDelete)
            }

            $totalAmount = 0;
            foreach ($items as $item) {
                $totalAmount += $item['qty'] * $item['sell_price'];
            }

            $so->update([
                'customer_id'  => $data['customer_id'],
                'so_date'      => $data['so_date'],
                'note'         => $data['note'] ?? null,
                'source_id'    => $data['source_id'] ?? $so->source_id,
                'total_amount' => $totalAmount,
                'total_hpp'    => 0,
            ]);

            $totalHpp = 0;

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                $saleItem = $so->items()->create([
                    'product_id'   => $product->id,
                    'qty'          => $item['qty'],
                    'sell_price'   => $item['sell_price'],
                    'subtotal'     => $item['qty'] * $item['sell_price'],
                    'hpp_subtotal' => 0,
                ]);

                $allocations = $this->stockService->allocateFifo(
                    $product,
                    $item['qty'],
                    $so->so_date,
                    $saleItem
                );

                $itemHpp = 0;
                foreach ($allocations as $alloc) {
                    $saleItem->allocations()->create($alloc);
                    $itemHpp += $alloc['hpp_subtotal'];
                }

                $saleItem->update(['hpp_subtotal' => $itemHpp]);
                $totalHpp += $itemHpp;
            }

            $so->update(['total_hpp' => $totalHpp]);

            return $so->fresh(['items.allocations', 'payments']);
        });
    }

    /**
     * Hapus SO: kembalikan semua alokasi FIFO ke batch asal, lalu hapus SO
     * (item & pembayaran ikut terhapus lewat cascade). HANYA boleh dipanggil
     * selagi SO belum dibayar sama sekali.
     *
     * @throws \RuntimeException kalau SO sudah dibayar
     */
    public function delete(SalesOrder $so): void
    {
        DB::transaction(function () use ($so) {
            $so->loadMissing('items.allocations');

            $this->guardCanModify($so);

            foreach ($so->items as $saleItem) {
                $this->stockService->reverseAllocations(
                    $saleItem->allocations,
                    $so->so_date,
                    "Pembatalan transaksi SO #{$so->so_number}"
                );
            }

            // items & payments ikut terhapus otomatis (cascadeOnDelete di migration)
            $so->delete();
        });
    }

    protected function guardCanModify(SalesOrder $so): void
    {
        if (! $so->canBeModified()) {
            throw new \RuntimeException(
                "Transaksi #{$so->so_number} sudah ada pembayaran, tidak bisa diedit/dihapus lagi."
            );
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
