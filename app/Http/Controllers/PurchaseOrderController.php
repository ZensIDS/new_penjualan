<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderCostRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\StorePurchasePaymentRequest;
use App\Http\Requests\UpdatePurchaseOrderCostRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchasePaymentRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $service) {}

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $search    = trim((string) $request->input('search', ''));

        $purchaseOrders = PurchaseOrder::query()
            ->with('supplier:id,name') // hanya kolom yang dipakai di tabel, bukan seluruh model supplier
            ->when($startDate, fn($q) => $q->whereDate('po_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('po_date', '<=', $endDate))
            ->when($search !== '', function ($q) use ($search) {
                // Cari di semua kolom yang tampil di tabel: No. PO, Supplier, Status,
                // serta Total/Sisa Hutang (dicocokkan sebagai teks angka).
                $q->where(function ($q) use ($search) {
                    $q->where('po_number', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                        ->orWhere('total_amount', 'like', "%{$search}%")
                        ->orWhereRaw('(total_amount - paid_amount) LIKE ?', ["%{$search}%"])
                        ->orWhere('payment_status', $this->mapStatusSearch($search));
                });
            })
            ->latest('po_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        if ($request->ajax()) {
            return view('purchase-orders._table', compact('purchaseOrders'));
        }

        return view('purchase-orders.index', compact('purchaseOrders', 'startDate', 'endDate', 'search'));
        // Kalau API: return response()->json($purchaseOrders);
    }

    /**
     * Terjemahkan kata kunci pencarian ala label status ("lunas", "sebagian",
     * "belum bayar") ke nilai enum payment_status di DB, supaya user bisa cari
     * pakai label yang tampil di tabel, bukan cuma nilai mentahnya.
     */
    protected function mapStatusSearch(string $search): string
    {
        $search = strtolower($search);

        return match (true) {
            str_contains($search, 'lunas')  => 'paid',
            str_contains($search, 'sebagian') => 'partial',
            str_contains($search, 'belum')  => 'unpaid',
            default => $search,
        };
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'qty_on_hand']);

        $expenseCategories = ExpenseCategory::orderBy('name')->get(['id', 'name']);

        return view('purchase-orders.create', compact('suppliers', 'products', 'expenseCategories'));
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'supplier', 'items.product', 'items.stockBatch', 'items.returnItems',
            'payments', 'returns.items.product', 'extraCosts.category',
        ]);

        $expenseCategories = ExpenseCategory::orderBy('name')->get(['id', 'name']);

        return view('purchase-orders.show', compact('purchaseOrder', 'expenseCategories'));
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
                extraCosts: $validated['extra_costs'] ?? [],
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
            // Kalau API: return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()
            ->route('purchase-orders.show', $po)
            ->with('success', "PO {$po->po_number} berhasil dibuat.");
    }

    /**
     * Kebalikan dari "Tandai Lunas": balikkan status pembayaran PO ini ke
     * "Belum Bayar" lagi. Semua pembayaran yang sudah tercatat untuk PO
     * ini (beserta ledger arus kasnya) ikut dihapus (lihat
     * PurchaseOrderService::markUnpaid()).
     */
    public function unmarkPaid(PurchaseOrder $purchaseOrder)
    {
        abort_unless(auth()->user()->isSuperadmin(), 403);

        $this->service->markUnpaid($purchaseOrder);

        return back()->with('success', "PO {$purchaseOrder->po_number} ditandai belum lunas, seluruh pembayaran yang tercatat sudah dihapus.");
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

    /**
     * Tambah biaya tambahan PO (ongkir, bongkar muat, dll). Tercatat sebagai
     * Expense biasa yang ditautkan ke PO ini — otomatis ikut ke Laporan
     * Pengeluaran, Laba Rugi, dan ledger arus kas (lihat PurchaseOrderService::addExtraCost).
     */
    public function storeCost(StorePurchaseOrderCostRequest $request, PurchaseOrder $purchaseOrder)
    {
        $this->service->addExtraCost($purchaseOrder, $request->validated());

        return back()->with('success', 'Biaya tambahan PO berhasil dicatat.');
    }

    /**
     * Edit biaya tambahan PO yang sudah tercatat (koreksi kategori/nominal/dll).
     * Entry cash_flow terkait ikut disinkronkan otomatis.
     */
    public function updateCost(UpdatePurchaseOrderCostRequest $request, PurchaseOrder $purchaseOrder, Expense $cost)
    {
        abort_unless($cost->purchase_order_id === $purchaseOrder->id, 404);

        $this->service->updateExtraCost($cost, $request->validated());

        return back()->with('success', 'Biaya tambahan PO berhasil diperbarui.');
    }

    /**
     * Hapus biaya tambahan PO. Entry cash_flow terkait ikut dihapus otomatis
     * (lihat PurchaseOrderService::deleteExtraCost), supaya ledger arus kas
     * & Laba Rugi tidak menyisakan biaya "hantu" untuk biaya yang sudah dihapus.
     */
    public function destroyCost(PurchaseOrder $purchaseOrder, Expense $cost)
    {
        abort_unless($cost->purchase_order_id === $purchaseOrder->id, 404);

        $this->service->deleteExtraCost($cost);

        return back()->with('success', 'Biaya tambahan PO berhasil dihapus.');
    }

    /**
     * Tandai biaya tambahan PO sebagai lunas. Baru dari sini biaya tersebut
     * tercatat ke ledger arus kas dan ikut ke Laporan Pengeluaran & Laba Rugi
     * (lihat PurchaseOrderService::payExtraCost()).
     */
    public function payCost(PurchaseOrder $purchaseOrder, Expense $cost)
    {
        abort_unless($cost->purchase_order_id === $purchaseOrder->id, 404);
        abort_unless(auth()->user()->isSuperadmin(), 403);

        $this->service->payExtraCost($cost);

        return back()->with('success', 'Biaya tambahan PO ditandai lunas & sudah masuk ke Pengeluaran.');
    }

    /**
     * Kebalikan dari payCost(): tandai biaya tambahan PO yang sudah lunas
     * jadi belum lunas lagi. Entry cash_flow terkait ikut dihapus (lihat
     * PurchaseOrderService::unpayExtraCost()), sehingga biaya ini lepas
     * lagi dari Laporan Pengeluaran & Laba Rugi.
     */
    public function unpayCost(PurchaseOrder $purchaseOrder, Expense $cost)
    {
        abort_unless($cost->purchase_order_id === $purchaseOrder->id, 404);
        abort_unless(auth()->user()->isSuperadmin(), 403);

        $this->service->unpayExtraCost($cost);

        return back()->with('success', 'Biaya tambahan PO ditandai belum lunas & sudah dikeluarkan dari Pengeluaran.');
    }
}