<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $service) {}

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $search    = trim((string) $request->input('search', ''));

        $expenses = Expense::query()
            ->with(['category:id,name', 'purchaseOrder:id,po_number']) // hanya kolom yang dipakai di tabel
            // Biaya tambahan PO yang belum ditandai "Lunas" belum tercatat
            // sebagai pengeluaran (lihat ExpenseService::createUnpaid/markPaid).
            ->where('is_paid', true)
            ->when($startDate, fn($q) => $q->whereDate('expense_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('expense_date', '<=', $endDate))
            ->when($search !== '', function ($q) use ($search) {
                // Cari di semua kolom yang tampil di tabel: Kategori, Keterangan, Jumlah.
                $q->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%")
                        ->orWhereHas('category', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('expense_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $expenseCategories = ExpenseCategory::orderBy('name')->get(['id', 'name']);

        if ($request->ajax()) {
            return view('expenses._table', compact('expenses'));
        }

        return view('expenses.index', compact('expenses', 'expenseCategories', 'startDate', 'endDate', 'search'));
    }

    public function store(StoreExpenseRequest $request)
    {
        $expense = $this->service->create($request->validated());

        return response()->json([
            'message' => 'Biaya berhasil dicatat.',
            'data'    => $expense->load('category'),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        // Biaya tambahan PO cuma boleh dikelola dari halaman detail PO
        // (lihat PurchaseOrderController@updateCost) — bukan dari sini,
        // supaya konteksnya (PO mana, guard-guard PO) tidak terlewat.
        if ($expense->purchase_order_id) {
            return response()->json([
                'message' => 'Biaya ini berasal dari PO, silakan kelola dari halaman detail PO terkait.',
            ], 422);
        }

        $this->service->update($expense, $request->validated());

        return response()->json([
            'message' => 'Biaya berhasil diperbarui.',
            'data'    => $expense->load('category'),
        ]);
    }

    public function destroy(Expense $expense)
    {
        if ($expense->purchase_order_id) {
            return response()->json([
                'message' => 'Biaya ini berasal dari PO, silakan kelola dari halaman detail PO terkait.',
            ], 422);
        }

        $this->service->delete($expense);

        return response()->json([
            'message' => 'Biaya berhasil dihapus.',
        ]);
    }
}