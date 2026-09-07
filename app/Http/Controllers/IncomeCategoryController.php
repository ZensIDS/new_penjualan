<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomeCategoryRequest;
use App\Http\Requests\UpdateIncomeCategoryRequest;
use App\Models\IncomeCategory;

class IncomeCategoryController extends Controller
{
    public function index()
    {
        $incomeCategories = IncomeCategory::withCount('incomes')
            ->latest()
            ->paginate(10);

        return view('income-categories.index', compact('incomeCategories'));
    }

    public function store(StoreIncomeCategoryRequest $request)
    {
        $data = $request->validated();
        $data['affects_profit_loss'] = $request->boolean('affects_profit_loss');

        $incomeCategory = IncomeCategory::create($data);

        return response()->json([
            'message' => 'Kategori pemasukan berhasil ditambahkan.',
            'data'    => $incomeCategory,
        ]);
    }

    public function update(UpdateIncomeCategoryRequest $request, IncomeCategory $incomeCategory)
    {
        $data = $request->validated();
        $data['affects_profit_loss'] = $request->boolean('affects_profit_loss');

        $incomeCategory->update($data);

        return response()->json([
            'message' => 'Kategori pemasukan berhasil diperbarui.',
            'data'    => $incomeCategory,
        ]);
    }

    public function destroy(IncomeCategory $incomeCategory)
    {
        if ($incomeCategory->incomes()->exists()) {
            return response()->json([
                'message' => 'Kategori pemasukan tidak bisa dihapus karena masih dipakai.',
            ], 422);
        }

        $incomeCategory->delete();

        return response()->json([
            'message' => 'Kategori pemasukan berhasil dihapus.',
        ]);
    }
}
