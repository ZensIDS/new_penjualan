<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate(20);

        return view('suppliers.index', compact('suppliers'));
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
