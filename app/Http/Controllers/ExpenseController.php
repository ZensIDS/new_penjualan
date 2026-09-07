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

        $expenses = Expense::with('category')
            ->when($startDate, fn($q) => $q->whereDate('expense_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('expense_date', '<=', $endDate))
            ->latest('expense_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $expenseCategories = ExpenseCategory::orderBy('name')->get(['id', 'name']);

        return view('expenses.index', compact('expenses', 'expenseCategories', 'startDate', 'endDate'));
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
        $this->service->update($expense, $request->validated());

        return response()->json([
            'message' => 'Biaya berhasil diperbarui.',
            'data'    => $expense->load('category'),
        ]);
    }

    public function destroy(Expense $expense)
    {
        $this->service->delete($expense);

        return response()->json([
            'message' => 'Biaya berhasil dihapus.',
        ]);
    }
}
