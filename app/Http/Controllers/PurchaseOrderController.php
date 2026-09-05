<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\StorePurchasePaymentRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchasePaymentRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $service) {}

    public function index()
    {
        $purchaseOrders = PurchaseOrder::with('supplier')
            ->latest('po_date')
            ->paginate(10);

        return view('purchase-orders.index', compact('purchaseOrders'));
        // Kalau API: return response()->json($purchaseOrders);
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'qty_on_hand']);

        return view('purchase-orders.create', compact('suppliers', 'products'));
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'items.product', 'items.stockBatch', 'items.returnItems', 'payments', 'returns.items.product']);

        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        // Edit tetap boleh diakses meskipun PO sudah ada pembayaran (partial/lunas).
        // Satu-satunya hal yang benar-benar memblokir adalah kalau barang dari PO
        // ini sudah terlanjur terjual — itu baru ketahuan saat submit (lihat
        // PurchaseOrderService::guardCanModify), dan errornya ditangkap di update().
        $purchaseOrder->load('items');

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'qty_on_hand']);

        return view('purchase-orders.edit', compact('purchaseOrder', 'suppliers', 'products'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validated();

        try {
            $po = $this->service->update(
                po: $purchaseOrder,
                data: [
                    'supplier_id' => $validated['supplier_id'],
                    'po_date'     => $validated['po_date'],
                    'note'        => $validated['note'] ?? null,
                ],
                items: $validated['items'],
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('purchase-orders.show', $po)
            ->with('success', "PO {$po->po_number} berhasil diperbarui.");
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        // Hapus tetap boleh meskipun PO sudah ada pembayaran (partial/lunas) —
        // pembayaran & entry cashflow-nya ikut dibersihkan di service. Satu-
        // satunya hal yang memblokir adalah kalau barangnya sudah terjual.
        try {
            $poNumber = $purchaseOrder->po_number;
            $this->service->delete($purchaseOrder);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', "PO {$poNumber} berhasil dihapus, stok terkait sudah dikembalikan.");
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $validated = $request->validated();

        try {
            $po = $this->service->create(
                data: [
                    'supplier_id' => $validated['supplier_id'],
                    'po_date'     => $validated['po_date'],
                    'note'        => $validated['note'] ?? null,
                ],
                items: $validated['items'],
                initialPayment: $validated['initial_payment'] ?? null,
                paymentMethod: $validated['payment_method'] ?? 'cash',
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
            // Kalau API: return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()
            ->route('purchase-orders.show', $po)
            ->with('success', "PO {$po->po_number} berhasil dibuat.");
    }

    public function storePayment(StorePurchasePaymentRequest $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validated();

        try {
            $this->service->addPayment(
                po: $purchaseOrder,
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

    /**
     * Edit pembayaran yang sudah tercatat (koreksi nominal/tanggal/dll).
     * paid_amount, payment_status PO, dan ledger cash_flow ikut disinkronkan ulang.
     */
    public function updatePayment(UpdatePurchasePaymentRequest $request, PurchaseOrder $purchaseOrder, PurchasePayment $payment)
    {
        abort_unless($payment->purchase_order_id === $purchaseOrder->id, 404);

        $validated = $request->validated();

        try {
            $this->service->updatePayment($payment, $validated);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Pembayaran berhasil diperbarui.');
    }
}
