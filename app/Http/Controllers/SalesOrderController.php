<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\StoreSalesPaymentRequest;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;

class SalesOrderController extends Controller
{
    public function __construct(protected SalesOrderService $service) {}

    public function index()
    {
        $salesOrders = SalesOrder::with('customer')
            ->latest('so_date')
            ->paginate(20);

        return view('sales-orders.index', compact('salesOrders'));
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'items.product', 'items.allocations.stockBatch', 'payments']);

        return view('sales-orders.show', compact('salesOrder'));
    }

    public function store(StoreSalesOrderRequest $request)
    {
        $validated = $request->validated();

        try {
            $so = $this->service->create(
                data: [
                    'customer_id' => $validated['customer_id'],
                    'so_date'     => $validated['so_date'],
                    'note'        => $validated['note'] ?? null,
                ],
                items: $validated['items'],
                initialPayment: $validated['initial_payment'] ?? null,
                paymentMethod: $validated['payment_method'] ?? 'cash',
            );
        } catch (\RuntimeException $e) {
            // Termasuk error "stok tidak mencukupi" dari StockService::allocateFifo()
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('sales-orders.show', $so)
            ->with('success', "Transaksi {$so->so_number} berhasil dibuat.");
    }

    public function storePayment(StoreSalesPaymentRequest $request, SalesOrder $salesOrder)
    {
        $validated = $request->validated();

        try {
            $this->service->addPayment(
                so: $salesOrder,
                date: $validated['payment_date'],
                amount: $validated['amount'],
                method: $validated['method'],
                note: $validated['note'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pembayaran berhasil dicatat.');
    }
}
