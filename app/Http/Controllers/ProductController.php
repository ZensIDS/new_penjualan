<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->paginate(10);

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('products.index', compact('products', 'categories'));
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
