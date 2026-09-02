<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::orderBy('name')->paginate(10);

        return view('customers.index', compact('customers'));
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return response()->json([
            'message' => 'Customer berhasil ditambahkan.',
            'data'    => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return response()->json([
            'message' => 'Customer berhasil diperbarui.',
            'data'    => $customer,
        ]);
    }

    public function destroy(Customer $customer)
    {
        if ($customer->salesOrders()->exists()) {
            return response()->json([
                'message' => 'Customer tidak bisa dihapus karena masih punya riwayat SO.',
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus.',
        ]);
    }
}
