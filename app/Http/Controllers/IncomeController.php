<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Services\IncomeService;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    public function __construct(protected IncomeService $service) {}

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $incomes = Income::with('category')
            ->when($startDate, fn($q) => $q->whereDate('income_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('income_date', '<=', $endDate))
            ->latest('income_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $incomeCategories = IncomeCategory::orderBy('name')->get(['id', 'name', 'affects_profit_loss']);

        return view('incomes.index', compact('incomes', 'incomeCategories', 'startDate', 'endDate'));
    }

    public function store(StoreIncomeRequest $request)
    {
        $income = $this->service->create($request->validated());

        return response()->json([
            'message' => 'Pemasukan berhasil dicatat.',
            'data'    => $income->load('category'),
        ]);
    }

    public function update(UpdateIncomeRequest $request, Income $income)
    {
        $this->service->update($income, $request->validated());

        return response()->json([
            'message' => 'Pemasukan berhasil diperbarui.',
            'data'    => $income->load('category'),
        ]);
    }

    public function destroy(Income $income)
    {
        $this->service->delete($income);

        return response()->json([
            'message' => 'Pemasukan berhasil dihapus.',
        ]);
    }
}
