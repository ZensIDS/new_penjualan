<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockConversionRequest;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockConversion;
use App\Services\StockConversionService;
use Illuminate\Http\Request;

class StockConversionController extends Controller
{
    public function __construct(protected StockConversionService $service) {}

    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $search    = trim((string) $request->input('search', ''));

        $conversions = StockConversion::query()
            ->with(['sourceProduct:id,name,unit'])
            ->withCount('results')
            ->when($startDate, fn($q) => $q->whereDate('conversion_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('conversion_date', '<=', $endDate))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('conversion_number', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhere('total_hpp', 'like', "%{$search}%")
                        ->orWhereHas('sourceProduct', fn($sq) => $sq->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('results.product', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('conversion_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        if ($request->ajax()) {
            return view('stock-conversions._table', compact('conversions'));
        }

        return view('stock-conversions.index', compact('conversions', 'startDate', 'endDate', 'search'));
    }

    public function create()
    {
        return view('stock-conversions.create', [
            'products'     => $this->productsForForm(),
            'lastRecipes'  => $this->lastRecipes(),
        ]);
    }

    public function store(StoreStockConversionRequest $request)
    {
        try {
            $conversion = $this->service->create(
                $request->only(['conversion_date', 'source_product_id', 'source_qty', 'allocation_method', 'note']),
                $request->input('components')
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('stock-conversions.show', $conversion)
            ->with('success', "Pembongkaran {$conversion->conversion_number} berhasil dicatat. Komponen sudah masuk stok dan siap dijual.");
    }

    public function show(StockConversion $stockConversion)
    {
        $stockConversion->load([
            'sourceProduct',
            'sources.stockBatch.purchaseOrderItem.purchaseOrder:id,po_number',
            'results.product',
            'results.stockBatch',
        ]);

        return view('stock-conversions.show', ['conversion' => $stockConversion]);
    }

    public function destroy(StockConversion $stockConversion)
    {
        $number = $stockConversion->conversion_number;

        try {
            $this->service->delete($stockConversion);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('stock-conversions.index')
            ->with('success', "Pembongkaran {$number} dibatalkan. Unit utuh sudah dikembalikan ke stok.");
    }

    /**
     * Produk aktif untuk dropdown. qty_on_hand dipakai form (JS) buat mencegah
     * bongkar melebihi stok, dan next_buy_price untuk menaksir HPP yang akan
     * dibagi. Batas final tetap ditegakkan server-side lewat StockService.
     */
    protected function productsForForm(): \Illuminate\Support\Collection
    {
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'qty_on_hand']);

        // Harga beli acuan = batch tertua yang masih punya sisa (batch yang akan
        // kepakai duluan kalau produk ini dibongkar), sama polanya dengan form SO.
        $nextBuyPrices = StockBatch::where('qty_remaining', '>', 0)
            ->orderBy('batch_date')
            ->orderBy('id')
            ->get(['product_id', 'buy_price'])
            ->groupBy('product_id')
            ->map(fn($rows) => (float) $rows->first()->buy_price);

        return $products->map(function ($p) use ($nextBuyPrices) {
            $p->next_buy_price = $nextBuyPrices[$p->id] ?? 0;

            return $p;
        });
    }

    /**
     * Komposisi bongkar TERAKHIR untuk tiap produk sumber, dikirim ke form supaya
     * user bisa klik "pakai komposisi terakhir" dan tidak perlu mengetik ulang
     * daftar komponen yang itu-itu juga setiap kali membongkar barang sejenis.
     */
    protected function lastRecipes(): array
    {
        $latestIds = StockConversion::selectRaw('MAX(id) as id')
            ->groupBy('source_product_id')
            ->pluck('id');

        return StockConversion::with('results:id,stock_conversion_id,product_id,qty,allocation_percent,estimated_sell_price')
            ->whereIn('id', $latestIds)
            ->get()
            ->mapWithKeys(fn($c) => [
                $c->source_product_id => [
                    'allocation_method' => $c->allocation_method,
                    'source_qty'        => $c->source_qty,
                    'components'        => $c->results->map(fn($r) => [
                        'product_id'           => $r->product_id,
                        'qty'                  => $r->qty,
                        'allocation_percent'   => $r->allocation_percent,
                        'estimated_sell_price' => $r->estimated_sell_price,
                    ])->values(),
                ],
            ])
            ->toArray();
    }
}