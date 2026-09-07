<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $products = Product::with('category')
            ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('products.index', compact('products', 'categories', 'startDate', 'endDate'));
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data'    => $product->load('category'),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data'    => $product->load('category'),
        ]);
    }

    public function destroy(Product $product)
    {
        if ($product->qty_on_hand > 0 || $product->purchaseOrderItems()->exists() || $product->saleItems()->exists()) {
            return response()->json([
                'message' => 'Produk tidak bisa dihapus karena sudah punya riwayat transaksi/stok.',
            ], 422);
        }

        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }
}
