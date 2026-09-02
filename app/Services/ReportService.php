<?php

namespace App\Services;

use App\Models\CashFlow;
use App\Models\Expense;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\PurchaseReturn;
use App\Models\SaleItem;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Laporan Stok: total stok per produk + breakdown per batch.
     *
     * PERHATIAN: mengambil & memetakan SEMUA produk sekaligus (termasuk semua
     * batch-nya) ke dalam collection PHP. Ini masih dipakai khusus untuk
     * export Excel (ReportExportController), yang memang butuh seluruh baris.
     * Untuk tampilan halaman (web), JANGAN pakai method ini — pakai
     * stockReportPaginated() supaya query & payload tetap kecil walau data
     * produk sudah ribuan baris.
     */
    public function stockReport()
    {
        return Product::with(['category', 'stockBatches' => function ($q) {
            $q->where('qty_remaining', '>', 0)->orderBy('batch_date');
        }])
            ->orderBy('name')
            ->get()
            ->map(fn(Product $product) => $this->mapProductStock($product));
    }

    /**
     * Versi paginated dari laporan stok, dipakai untuk halaman stock.index &
     * reports.stock. Hanya produk pada HALAMAN AKTIF yang di-eager-load
     * batch-nya (bukan seluruh produk), jadi query & ukuran response tetap
     * kecil meski jumlah produk sudah ribuan. Search dilakukan di level SQL
     * (WHERE ... LIKE), bukan filter di PHP/JS atas seluruh data.
     */
    public function stockReportPaginated(?string $search = null, int $perPage = 25)
    {
        $query = Product::query()->with('category');

        if (filled($search)) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $paginator = $query->orderBy('name')->paginate($perPage)->withQueryString();

        // Eager-load batch hanya untuk produk di halaman ini (maks $perPage baris),
        // bukan untuk seluruh produk di database.
        $paginator->getCollection()->loadMissing(['stockBatches' => function ($q) {
            $q->where('qty_remaining', '>', 0)->orderBy('batch_date');
        }]);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn(Product $product) => $this->mapProductStock($product))
        );

        return $paginator;
    }

    /**
     * Angka ringkasan (KPI) stok dihitung lewat agregasi SQL (COUNT/SUM),
     * bukan dengan me-load seluruh produk+batch ke PHP lalu di-sum manual.
     * Nilainya mencakup SELURUH data, independen dari pagination/pencarian
     * di atas.
     */
    public function stockKpis(): array
    {
        return [
            'product_count' => Product::count(),
            'total_qty'     => (int) Product::sum('qty_on_hand'),
            'total_value'   => (float) (DB::table('stock_batches')
                ->where('qty_remaining', '>', 0)
                ->selectRaw('COALESCE(SUM(qty_remaining * buy_price), 0) as val')
                ->value('val')),
        ];
    }

    /**
     * Total nilai stok per kategori (untuk chart di halaman reports.stock).
     * Diagregasi langsung di SQL, bukan dari collection produk yang sudah di-load.
     */
    public function stockValueByCategory()
    {
        return DB::table('stock_batches')
            ->join('products', 'products.id', '=', 'stock_batches.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('stock_batches.qty_remaining', '>', 0)
            ->selectRaw("COALESCE(categories.name, 'Tanpa Kategori') as name, SUM(stock_batches.qty_remaining * stock_batches.buy_price) as value")
            ->groupBy('categories.name')
            ->orderByDesc('value')
            ->get();
    }

    /**
     * Petakan satu Product (beserta stockBatches yang sudah di-load) ke bentuk
     * array yang dipakai baik oleh stockReport() maupun stockReportPaginated().
     */
    private function mapProductStock(Product $product): array
    {
        return [
            'product_id'   => $product->id,
            'name'         => $product->name,
            'category'     => $product->category->name ?? null,
            'total_qty'    => $product->qty_on_hand,
            'stock_value'  => $product->stockBatches->sum(fn($b) => $b->qty_remaining * $b->buy_price),
            'batches'      => $product->stockBatches->map(fn($b) => [
                'batch_date'    => $b->batch_date->format('Y-m-d'),
                'buy_price'     => (float) $b->buy_price,
                'qty_in'        => $b->qty_in,
                'qty_remaining' => $b->qty_remaining,
            ]),
        ];
    }

    /**
     * Laporan Laba Rugi untuk rentang tanggal tertentu.
     * Pendapatan - HPP (FIFO) = Laba Kotor. Laba Kotor - Biaya Operasional = Laba Bersih.
     * Basis: so_date / expense_date (accrual), BUKAN tanggal pembayaran diterima.
     *
     * CATATAN PEMBELIAN (PO): nilai pembelian TIDAK dikurangkan langsung dari
     * laba di sini — itu bukan bug, tapi prinsip "matching cost vs revenue".
     * Saat PO dibuat, uang yang keluar berubah wujud jadi ASET (stok di
     * gudang), bukan biaya. Baru saat barang itu TERJUAL (SO), nilai
     * beli-nya "keluar" dari stok dan diakui sebagai HPP — itulah yang
     * dikurangkan dari pendapatan di atas. Kalau nilai PO ikut dikurangkan
     * juga di sini, biaya barang yang belum laku akan terhitung dobel
     * (sekali sebagai "pembelian", sekali lagi sebagai HPP saat laku).
     * Makanya 'purchase' & 'purchase_return' di bawah hanya ditampilkan
     * sebagai INFORMASI (berapa besar belanja/retur periode ini), bukan
     * komponen pengurang Laba Bersih.
     *
     * CATATAN RETUR: kolom total_amount/total_hpp pada SalesOrder & total_amount
     * pada PurchaseOrder SUDAH otomatis dikurangi begitu ada retur (lihat
     * SalesReturnService & PurchaseReturnService) — jadi $revenue & $hpp di
     * bawah ini SUDAH bersih dari retur secara nilai, tidak dihitung dobel.
     * Baris sales_return / sales_return_hpp / purchase_return di bawah HANYA
     * dipakai sebagai INFORMASI seberapa besar retur yang terjadi pada
     * rentang tanggal ini (berdasar return_date), supaya kelihatan di
     * ringkasan & tidak "hilang senyap" dari angka pendapatan.
     */
    public function profitLossReport(string $startDate, string $endDate): array
    {
        $revenue = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('total_amount');
        $revenuePaid = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('paid_amount');
        $revenuePending = $revenue - $revenuePaid; // "Pendapatan Tertahan": bagian penjualan yang belum dibayar (sudah masuk piutang)
        $hpp     = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('total_hpp');
        $grossProfit = $revenue - $hpp;

        $operationalExpense = Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');
        $netProfit = $grossProfit - $operationalExpense;

        $expenseByCategory = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total'))
            ->groupBy('expense_categories.name')
            ->get();

        // Retur Penjualan (SO) & Retur Pembelian (PO) pada periode ini,
        // berdasar return_date — informasional, lihat catatan di atas.
        $salesReturnAmount = SalesReturn::whereBetween('return_date', [$startDate, $endDate])->sum('total_amount');
        $salesReturnHpp    = SalesReturn::whereBetween('return_date', [$startDate, $endDate])->sum('total_hpp');
        $purchaseReturnAmount = PurchaseReturn::whereBetween('return_date', [$startDate, $endDate])->sum('total_amount');

        // Total pembelian (PO) periode ini — sudah bersih dari retur pembelian
        // (lihat PurchaseReturnService, total_amount PO langsung dikurangi saat
        // retur dibuat). Informasional saja, lihat catatan di atas.
        $purchaseAmount = PurchaseOrder::whereBetween('po_date', [$startDate, $endDate])->sum('total_amount');

        // Perkiraan pendapatan kotor sebelum retur pada periode ini (revenue sudah
        // netto, jadi ditambah balik nilai retur periode ini untuk estimasi kotor).
        $revenueGross = $revenue + $salesReturnAmount;

        return [
            'period'                => [$startDate, $endDate],
            'revenue'                => (float) $revenue,
            'revenue_gross'          => (float) $revenueGross,
            'revenue_paid'           => (float) $revenuePaid,
            'revenue_pending'        => (float) $revenuePending,
            'hpp'                    => (float) $hpp,
            'gross_profit'           => (float) $grossProfit,
            'operational_expense'    => (float) $operationalExpense,
            'expense_by_category'    => $expenseByCategory,
            'net_profit'             => (float) $netProfit,
            'sales_return'           => (float) $salesReturnAmount,
            'sales_return_hpp'       => (float) $salesReturnHpp,
            'purchase'               => (float) $purchaseAmount,
            'purchase_return'        => (float) $purchaseReturnAmount,
        ];
    }

    /**
     * Ringkasan penjualan & pembelian (accrual, berdasar tanggal transaksi) untuk suatu rentang.
     * Dipakai di dashboard: "Penjualan Bulan Ini" (semua, termasuk yang belum dibayar)
     * dan "Total Pembelian Bulan Ini".
     */
    public function salesPurchaseSummary(string $startDate, string $endDate): array
    {
        $totalSales    = SalesOrder::whereBetween('so_date', [$startDate, $endDate])->sum('total_amount');
        $totalPurchase = PurchaseOrder::whereBetween('po_date', [$startDate, $endDate])->sum('total_amount');

        return [
            'total_sales'    => (float) $totalSales,
            'total_purchase' => (float) $totalPurchase,
        ];
    }

    /**
     * Pengeluaran kas riil (dari ledger cash_flows) pada rentang tanggal, dipisah
     * antara Biaya Operasional (Expense) dan Biaya Pembelian (pembayaran PO ke supplier).
     */
    public function expenseBreakdown(string $startDate, string $endDate): array
    {
        $rows = CashFlow::where('direction', 'out')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->select('source_type', DB::raw('SUM(amount) as total'))
            ->groupBy('source_type')
            ->pluck('total', 'source_type');

        $operational = (float) ($rows[Expense::class] ?? 0);
        $purchase    = (float) ($rows[PurchasePayment::class] ?? 0);

        return [
            'operational' => $operational,
            'purchase'    => $purchase,
            'total'       => $operational + $purchase,
        ];
    }

    /**
     * Produk terlaris dalam suatu rentang tanggal, diurutkan dari qty terjual terbanyak.
     */
    public function topSellingProducts(string $startDate, string $endDate, int $limit = 5)
    {
        return SaleItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sale_items.sales_order_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales_orders.so_date', [$startDate, $endDate])
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                DB::raw('SUM(sale_items.qty) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();
    }

    /**
     * Tren penjualan harian (accrual, berdasar so_date) dalam suatu rentang tanggal.
     * Dipakai untuk grafik di dashboard.
     */
    public function dailySalesTrend(string $startDate, string $endDate)
    {
        return SalesOrder::whereBetween('so_date', [$startDate, $endDate])
            ->select('so_date', DB::raw('SUM(total_amount) as total'))
            ->groupBy('so_date')
            ->orderBy('so_date')
            ->get()
            ->mapWithKeys(fn($row) => [$row->so_date->format('Y-m-d') => (float) $row->total]);
    }

    /**
     * Laporan Arus Kas: kas masuk & kas keluar per tanggal, dari ledger cash_flows
     * (sumbernya sudah realisasi bayar, bukan accrual seperti Laba Rugi).
     *
     * CATATAN URUTAN: 'details' diurutkan DESC berdasarkan transaction_date
     * DAN id (bukan cuma transaction_date). Soalnya transaction_date cuma
     * presisi per-hari (bukan per-detik) — kalau beberapa transaksi terjadi
     * di tanggal yang sama, urut cuma berdasar tanggal tidak bisa membedakan
     * mana yang paling baru dicatat. 'id' dipakai sebagai tie-breaker karena
     * nilainya naik sesuai urutan pencatatan (transaksi baru = id lebih besar).
     * Ini juga yang jadi penyebab bug "transaksi baru tidak tampil di atas"
     * sebelumnya — dulu di-sort di Blade/export cuma pakai
     * ->sortByDesc('transaction_date') saja, jadi transaksi di tanggal yang
     * sama akan tetap dalam urutan lama (insertion order), bukan yang
     * terbaru duluan.
     */
    public function cashFlowReport(string $startDate, string $endDate): array
    {
        $rows = CashFlow::whereBetween('transaction_date', [$startDate, $endDate])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $totalIn  = $rows->where('direction', 'in')->sum('amount');
        $totalOut = $rows->where('direction', 'out')->sum('amount');

        $daily = $rows->groupBy(fn($r) => $r->transaction_date->format('Y-m-d'))
            ->map(function ($group) {
                return [
                    'in'  => (float) $group->where('direction', 'in')->sum('amount'),
                    'out' => (float) $group->where('direction', 'out')->sum('amount'),
                ];
            });

        return [
            'period'    => [$startDate, $endDate],
            'total_in'  => (float) $totalIn,
            'total_out' => (float) $totalOut,
            'net_cash'  => (float) ($totalIn - $totalOut),
            'daily'     => $daily,
            'details'   => $rows,
        ];
    }

    /**
     * Versi paginated dari daftar "Rincian Transaksi" untuk halaman
     * reports.cash-flow, supaya tabel tidak nge-dump seluruh transaksi di
     * rentang tanggal (yang bisa saja ribuan baris kalau rentangnya lebar).
     * KPI & grafik harian tetap dari cashFlowReport() (butuh semua baris
     * untuk agregasi), hanya TABEL detailnya yang dipaginasi di sini.
     * Urutan sama seperti cashFlowReport(): transaction_date DESC, lalu id
     * DESC supaya transaksi yang baru dicatat selalu tampil paling atas.
     */
    public function cashFlowDetailsPaginated(string $startDate, string $endDate, int $perPage = 25)
    {
        return CashFlow::whereBetween('transaction_date', [$startDate, $endDate])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Laporan Hutang (Account Payable): PO yang belum lunas + histori pembayaran.
     *
     * PERHATIAN: mengambil SEMUA PO belum lunas sekaligus. Dipakai khusus
     * untuk export Excel yang memang butuh seluruh baris. Untuk halaman web
     * pakai accountPayableReportPaginated().
     */
    public function accountPayableReport()
    {
        return PurchaseOrder::with(['supplier', 'payments'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('po_date')
            ->get()
            ->map(fn(PurchaseOrder $po) => $this->mapPayable($po));
    }

    /**
     * Versi paginated laporan hutang untuk halaman reports.payable. Pencarian
     * (nomor PO / nama supplier) dilakukan di level SQL, dan relasi
     * supplier+payments hanya di-load untuk baris pada halaman aktif.
     */
    public function accountPayableReportPaginated(?string $search = null, int $perPage = 25)
    {
        $query = PurchaseOrder::with(['supplier', 'payments'])
            ->whereIn('payment_status', ['unpaid', 'partial']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', '%' . $search . '%')
                    ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', '%' . $search . '%'));
            });
        }

        $paginator = $query->orderBy('po_date')->paginate($perPage)->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn(PurchaseOrder $po) => $this->mapPayable($po))
        );

        return $paginator;
    }

    /**
     * KPI hutang (jumlah PO & total sisa) dihitung via agregasi SQL atas
     * SELURUH PO belum lunas, independen dari pagination/pencarian di atas.
     */
    public function payableKpis(): array
    {
        $base = PurchaseOrder::whereIn('payment_status', ['unpaid', 'partial']);

        return [
            'count'             => (clone $base)->count(),
            'total_outstanding' => (float) (clone $base)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as val')->value('val'),
        ];
    }

    private function mapPayable(PurchaseOrder $po): array
    {
        return [
            'po_number'         => $po->po_number,
            'supplier'          => $po->supplier->name,
            'po_date'           => $po->po_date->format('Y-m-d'),
            'total_amount'      => (float) $po->total_amount,
            'paid_amount'       => (float) $po->paid_amount,
            'remaining_balance' => (float) $po->remaining_balance,
            'payment_status'    => $po->payment_status,
            'payment_history'   => $po->payments->map(fn($p) => [
                'payment_date' => $p->payment_date->format('Y-m-d'),
                'amount'       => (float) $p->amount,
                'method'       => $p->method,
                'note'         => $p->note,
            ]),
        ];
    }

    /**
     * Laporan Piutang (Account Receivable): SO yang belum lunas + histori pembayaran.
     *
     * PERHATIAN: sama seperti accountPayableReport(), method ini mengambil
     * SEMUA baris sekaligus dan khusus dipakai untuk export Excel. Untuk
     * halaman web pakai accountReceivableReportPaginated().
     */
    public function accountReceivableReport()
    {
        return SalesOrder::with(['customer', 'payments'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->orderBy('so_date')
            ->get()
            ->map(fn(SalesOrder $so) => $this->mapReceivable($so));
    }

    /**
     * Versi paginated laporan piutang untuk halaman reports.receivable.
     * Sama pola-nya dengan accountPayableReportPaginated().
     */
    public function accountReceivableReportPaginated(?string $search = null, int $perPage = 25)
    {
        $query = SalesOrder::with(['customer', 'payments'])
            ->whereIn('payment_status', ['unpaid', 'partial']);

        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('so_number', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', fn($c) => $c->where('name', 'like', '%' . $search . '%'));
            });
        }

        $paginator = $query->orderBy('so_date')->paginate($perPage)->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn(SalesOrder $so) => $this->mapReceivable($so))
        );

        return $paginator;
    }

    /**
     * KPI piutang (jumlah SO & total sisa), agregasi SQL atas SELURUH SO
     * belum lunas — independen dari pagination/pencarian.
     */
    public function receivableKpis(): array
    {
        $base = SalesOrder::whereIn('payment_status', ['unpaid', 'partial']);

        return [
            'count'             => (clone $base)->count(),
            'total_outstanding' => (float) (clone $base)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as val')->value('val'),
        ];
    }

    private function mapReceivable(SalesOrder $so): array
    {
        return [
            'so_number'         => $so->so_number,
            'customer'          => $so->customer->name,
            'so_date'           => $so->so_date->format('Y-m-d'),
            'total_amount'      => (float) $so->total_amount,
            'paid_amount'       => (float) $so->paid_amount,
            'remaining_balance' => (float) $so->remaining_balance,
            'payment_status'    => $so->payment_status,
            'payment_history'   => $so->payments->map(fn($p) => [
                'payment_date' => $p->payment_date->format('Y-m-d'),
                'amount'       => (float) $p->amount,
                'method'       => $p->method,
                'note'         => $p->note,
            ]),
        ];
    }
}
