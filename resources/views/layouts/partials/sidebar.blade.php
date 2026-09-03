@php
    $nav = [
        'Ringkasan' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard'],
        ],
        'Pembelian' => [
            ['label' => 'Purchase Order', 'route' => 'purchase-orders.index', 'pattern' => 'purchase-orders.*'],
            ['label' => 'Supplier', 'route' => 'suppliers.index', 'pattern' => 'suppliers.*'],
        ],
        'Persediaan' => [
            ['label' => 'Stok', 'route' => 'stock.index', 'pattern' => 'stock.*'],
            ['label' => 'Produk', 'route' => 'products.index', 'pattern' => 'products.*'],
            ['label' => 'Kategori', 'route' => 'categories.index', 'pattern' => 'categories.*'],
        ],
        'Penjualan' => [
            ['label' => 'Sales Order', 'route' => 'sales-orders.index', 'pattern' => 'sales-orders.*'],
            ['label' => 'Customer', 'route' => 'customers.index', 'pattern' => 'customers.*'],
        ],
        'Operasional' => [
            ['label' => 'Kategori Pengeluaran', 'route' => 'expense-categories.index', 'pattern' => 'expense-categories.*'],
            ['label' => 'Pengeluaran', 'route' => 'expenses.index', 'pattern' => 'expenses.*'],
        ],
        'Laporan' => [
            ['label' => 'Stok', 'route' => 'reports.stock', 'pattern' => 'reports.stock'],
            ['label' => 'Laba Rugi', 'route' => 'reports.profit-loss', 'pattern' => 'reports.profit-loss'],
            ['label' => 'Arus Kas', 'route' => 'reports.cash-flow', 'pattern' => 'reports.cash-flow'],
            ['label' => 'Hutang (AP)', 'route' => 'reports.payable', 'pattern' => 'reports.payable'],
            ['label' => 'Piutang (AR)', 'route' => 'reports.receivable', 'pattern' => 'reports.receivable'],
            ['label' => 'Retur Penjualan (SO)', 'route' => 'reports.sales-return', 'pattern' => 'reports.sales-return'],
            ['label' => 'Retur Pembelian (PO)', 'route' => 'reports.purchase-return', 'pattern' => 'reports.purchase-return'],
        ],
    ];
@endphp

<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 w-64 bg-ink text-white flex flex-col transition-transform duration-200 ease-out lg:static lg:translate-x-0"
>
    {{-- Brand --}}
    <div class="h-16 flex items-center gap-2.5 px-6 border-b border-white/10 shrink-0">
        <span class="h-7 w-7 rounded-lg bg-amber-400 flex items-center justify-center shrink-0">
            <span class="font-display font-bold text-ink text-sm">B</span>
        </span>
        <a href="{{ route('dashboard') }}" class="font-display font-semibold text-lg tracking-tight">
            BerlianZ<span class="text-white/40">Store</span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-6 space-y-6">
        @foreach ($nav as $group => $items)
            <div>
                <p class="px-3 text-[11px] font-medium text-white/35 mb-2">{{ $group }}</p>
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                        @php $active = request()->routeIs($item['pattern']); @endphp
                        <a
                            href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
                            class="block px-3 py-2 text-sm transition-colors
                                {{ $active
                                    ? 'bg-white text-ink font-medium'
                                    : 'text-white/70 hover:text-white hover:bg-white/[0.06]' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    {{-- User info + logout --}}
    <div class="border-t border-white/10 p-4 shrink-0">
        <div class="flex items-center justify-between gap-3 px-2">
            <div class="min-w-0">
                <p class="text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                <p class="text-xs text-white/40 capitalize">{{ auth()->user()->role }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="text-xs text-white/50 hover:text-white border border-white/15 hover:border-white/30 px-2.5 py-1.5 transition-colors"
                >
                    Keluar
                </button>
            </form>
        </div>
    </div>
</aside>
