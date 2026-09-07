<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $suppliers = Supplier::when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'startDate', 'endDate'));
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        return response()->json([
            'message' => 'Supplier berhasil ditambahkan.',
            'data'    => $supplier,
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return response()->json([
            'message' => 'Supplier berhasil diperbarui.',
            'data'    => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'message' => 'Supplier tidak bisa dihapus karena masih punya riwayat PO.',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier berhasil dihapus.',
        ]);
    }
}
