<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Services\IncomeService;

class IncomeController extends Controller
{
    public function __construct(protected IncomeService $service) {}

    public function index()
    {
        $incomes = Income::with('category')
            ->latest('income_date')
            ->paginate(10);

        $incomeCategories = IncomeCategory::orderBy('name')->get(['id', 'name']);

        return view('incomes.index', compact('incomes', 'incomeCategories'));
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
