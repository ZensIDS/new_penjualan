<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContinueStockConversionRequest;
use App\Http\Requests\StoreStockConversionRequest;
use App\Models\Product;
use App\Models\SaleItem;
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
                $request->only(['conversion_date', 'source_product_id', 'source_qty', 'note', 'status']),
                $request->input('components')
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $message = $conversion->isDraft()
            ? "Pembongkaran {$conversion->conversion_number} tersimpan. Komponen sudah masuk stok dan siap dijual — kalau nanti ketemu komponen lain, tambahkan lewat \"Lanjutkan Bongkar\"."
            : "Pembongkaran {$conversion->conversion_number} berhasil dicatat. Komponen sudah masuk stok dan siap dijual.";

        return redirect()
            ->route('stock-conversions.show', $conversion)
            ->with('success', $message);
    }

    /**
     * Form "Lanjutkan Bongkar" — cuma untuk transaksi yang masih draft.
     */
    public function continueForm(StockConversion $stockConversion)
    {
        if (! $stockConversion->isDraft()) {
            return redirect()
                ->route('stock-conversions.show', $stockConversion)
                ->with('success', "Pembongkaran {$stockConversion->conversion_number} sudah selesai, tidak ada lagi yang perlu dilanjutkan.");
        }

        $stockConversion->load(['results.product', 'results.stockBatch', 'sourceProduct']);

        return view('stock-conversions.continue', [
            'conversion' => $stockConversion,
            'products'   => $this->productsForForm(),
        ]);
    }

    public function continueStore(ContinueStockConversionRequest $request, StockConversion $stockConversion)
    {
        try {
            $conversion = $this->service->continueConversion(
                $stockConversion,
                $request->only(['mark_complete']),
                $request->input('existing', []),
                $request->input('components', [])
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $message = $conversion->isDraft()
            ? "Komponen untuk {$conversion->conversion_number} tersimpan dan HPP sudah dibagi ulang ke semua komponen."
            : "Pembongkaran {$conversion->conversion_number} sudah lengkap — semua komponen tercatat dan HPP sudah dibagi habis.";

        return redirect()->route('stock-conversions.show', $conversion)->with('success', $message);
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

        // Harga jual acuan = harga jual RIIL terakhir produk tsb di Sales Order.
        // Dipakai form cuma untuk memperlihatkan pratinjau pembagian HPP; angka
        // final tetap dihitung ulang server-side (dan diperbarui otomatis setiap
        // kali ada penjualan baru).
        $lastSellPrices = SaleItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sale_items.sales_order_id')
            ->orderBy('sales_orders.so_date')
            ->orderBy('sale_items.id')
            ->get(['sale_items.product_id', 'sale_items.sell_price'])
            ->groupBy('product_id')
            ->map(fn($rows) => (float) $rows->last()->sell_price);

        return $products->map(function ($p) use ($nextBuyPrices, $lastSellPrices) {
            $p->next_buy_price = $nextBuyPrices[$p->id] ?? 0;
            $p->ref_sell_price = $lastSellPrices[$p->id] ?? 0;

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

        return StockConversion::with('results:id,stock_conversion_id,product_id,qty')
            ->whereIn('id', $latestIds)
            ->get()
            ->mapWithKeys(fn($c) => [
                $c->source_product_id => [
                    'source_qty' => $c->source_qty,
                    'components' => $c->results->map(fn($r) => [
                        'product_id' => $r->product_id,
                        'qty'        => $r->qty,
                    ])->values(),
                ],
            ])
            ->toArray();
    }
}