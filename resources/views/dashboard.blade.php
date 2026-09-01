@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

    {{-- Filter rentang tanggal --}}
    <div class="mb-6 rounded-2xl border border-ink/10 bg-white shadow-card p-4 sm:p-5">
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-ink/50 mb-1 uppercase tracking-wide">Dari Tanggal</label>
                <input type="date" name="start_date" value="{{ $startDate }}"
                       class="rounded-lg border border-ink/15 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400/60">
            </div>
            <div>
                <label class="block text-xs font-medium text-ink/50 mb-1 uppercase tracking-wide">Sampai Tanggal</label>
                <input type="date" name="end_date" value="{{ $endDate }}"
                       class="rounded-lg border border-ink/15 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400/60">
            </div>
            <button type="submit"
                    class="rounded-lg bg-ink text-white text-sm font-medium px-4 py-2 hover:bg-ink/90 transition-colors">
                Terapkan
            </button>

            <div class="flex items-center gap-1.5 ml-auto flex-wrap">
                @php
                    $presets = [
                        'Hari Ini'      => [now()->toDateString(), now()->toDateString()],
                        'Minggu Ini'    => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
                        'Bulan Ini'     => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                        'Bulan Lalu'    => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
                        '30 Hari Terakhir' => [now()->subDays(29)->toDateString(), now()->toDateString()],
                    ];
                @endphp
                @foreach ($presets as $label => $range)
                    <a href="{{ route('dashboard', ['start_date' => $range[0], 'end_date' => $range[1]]) }}"
                       class="text-xs font-medium px-3 py-1.5 rounded-full border transition-colors
                              {{ $startDate === $range[0] && $endDate === $range[1] ? 'bg-amber-400/90 border-amber-400/90 text-ink' : 'border-ink/15 text-ink/60 hover:border-ink/30' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </form>
        <p class="text-xs text-ink/40 mt-3">
            Menampilkan data periode
            <span class="font-medium text-ink/60">{{ \Illuminate\Support\Carbon::parse($startDate)->translatedFormat('d M Y') }}</span>
            &ndash;
            <span class="font-medium text-ink/60">{{ \Illuminate\Support\Carbon::parse($endDate)->translatedFormat('d M Y') }}</span>
        </p>
    </div>

    {{-- KPI utama --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Pendapatan Diterima</p>
            <p class="font-display font-semibold text-2xl tnum">
                Rp {{ number_format($profitLoss['revenue_paid'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-ink/40 mt-1">Sudah dibayar oleh pelanggan</p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Laba Bersih</p>
            <p class="font-display font-semibold text-2xl tnum {{ $profitLoss['net_profit'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                Rp {{ number_format($profitLoss['net_profit'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-ink/40 mt-1">Laba kotor &ndash; biaya operasional</p>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Nilai Stok Saat Ini</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400">
                Rp {{ number_format($stockValue, 0, ',', '.') }}
            </p>
            <p class="text-xs text-white/40 mt-1">Berdasarkan harga beli (FIFO)</p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Penjualan Bulan Ini</p>
            <p class="font-display font-semibold text-2xl tnum">
                Rp {{ number_format($salesPurchase['total_sales'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-ink/40 mt-1">Termasuk yang belum dibayar</p>
        </div>

    </div>

    {{-- Kas & Pembelian --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="h-6 w-6 rounded-md bg-emerald-50 flex items-center justify-center">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                </span>
                <p class="text-xs font-medium text-ink/50 uppercase tracking-wide">Kas Masuk</p>
            </div>
            <p class="font-display font-semibold text-xl tnum text-emerald-700">
                Rp {{ number_format($cashFlow['total_in'], 0, ',', '.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="h-6 w-6 rounded-md bg-red-50 flex items-center justify-center">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                </span>
                <p class="text-xs font-medium text-ink/50 uppercase tracking-wide">Kas Keluar</p>
            </div>
            <p class="font-display font-semibold text-xl tnum text-red-700">
                Rp {{ number_format($cashFlow['total_out'], 0, ',', '.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Total Pembelian</p>
            <p class="font-display font-semibold text-xl tnum">
                Rp {{ number_format($salesPurchase['total_purchase'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-ink/40 mt-1">Nilai PO periode ini</p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Total Pengeluaran</p>
            <p class="font-display font-semibold text-xl tnum">
                Rp {{ number_format($expenseBreakdown['total'], 0, ',', '.') }}
            </p>
            <div class="text-xs text-ink/40 mt-1 flex gap-2">
                <span>Operasional: Rp {{ number_format($expenseBreakdown['operational'], 0, ',', '.') }}</span>
            </div>
            <div class="text-xs text-ink/40 flex gap-2">
                <span>Pembelian: Rp {{ number_format($expenseBreakdown['purchase'], 0, ',', '.') }}</span>
            </div>
        </div>

    </div>

    {{-- Hutang / Piutang --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

        <a href="{{ route('reports.receivable') }}" class="group block rounded-2xl border border-ink/10 bg-white shadow-card p-6 hover:border-amber-400/60 hover:shadow-glow transition-all">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-medium text-ink/50 uppercase tracking-wide">Total Piutang (AR)</p>
                <span class="text-xs text-amber-600 font-medium opacity-0 group-hover:opacity-100 transition-opacity">Lihat detail &rarr;</span>
            </div>
            <p class="font-display font-semibold text-xl tnum">
                Rp {{ number_format($totalReceivable, 0, ',', '.') }}
            </p>
        </a>

        <a href="{{ route('reports.payable') }}" class="group block rounded-2xl border border-ink/10 bg-white shadow-card p-6 hover:border-amber-400/60 hover:shadow-glow transition-all">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-medium text-ink/50 uppercase tracking-wide">Total Hutang (AP)</p>
                <span class="text-xs text-amber-600 font-medium opacity-0 group-hover:opacity-100 transition-opacity">Lihat detail &rarr;</span>
            </div>
            <p class="font-display font-semibold text-xl tnum">
                Rp {{ number_format($totalPayable, 0, ',', '.') }}
            </p>
        </a>

    </div>

    {{-- Grafik --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

        <div class="lg:col-span-2 rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-ink/10">
                <h2 class="font-display font-semibold">Tren Penjualan &amp; Arus Kas Harian</h2>
                <p class="text-xs text-ink/40 mt-0.5">Penjualan (accrual) dibanding kas masuk &amp; keluar riil</p>
            </div>
            <div class="p-4">
                <canvas id="trendChart" height="110"></canvas>
            </div>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-ink/10">
                <h2 class="font-display font-semibold">Komposisi Pengeluaran</h2>
                <p class="text-xs text-ink/40 mt-0.5">Operasional vs. pembelian</p>
            </div>
            <div class="p-4 flex items-center justify-center">
                <canvas id="expenseChart" height="220"></canvas>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-ink/10">
                <h2 class="font-display font-semibold">Produk Terlaris</h2>
                <p class="text-xs text-ink/40 mt-0.5">Berdasarkan jumlah qty terjual</p>
            </div>
            <div class="p-4">
                <canvas id="topProductsChart" height="220"></canvas>
            </div>
        </div>

        {{-- Ringkasan Laba Rugi --}}
        <div class="lg:col-span-2 rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-ink/10 flex items-center gap-2.5">
                <span class="h-7 w-7 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 16 6-6 4 4 8-9M13.5 5H21v7.5"/></svg>
                </span>
                <h2 class="font-display font-semibold">Ringkasan Laba Rugi</h2>
            </div>
            <div class="divide-y divide-ink/[0.06] text-sm">
                <div class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-ink/60">Pendapatan Penjualan (diterima)</span>
                    <span class="tnum">Rp {{ number_format($profitLoss['revenue_paid'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-ink/60 flex items-center gap-1.5">
                        Pendapatan Tertahan
                        <span class="text-[10px] font-medium text-amber-700 bg-amber-50 rounded-full px-1.5 py-0.5">belum dibayar</span>
                    </span>
                    <span class="tnum">Rp {{ number_format($profitLoss['revenue_pending'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-ink/60">HPP (FIFO)</span>
                    <span class="tnum">Rp {{ number_format($profitLoss['hpp'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5 font-medium">
                    <span>Laba Kotor</span>
                    <span class="tnum">Rp {{ number_format($profitLoss['gross_profit'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-ink/60">Biaya Operasional</span>
                    <span class="tnum">Rp {{ number_format($profitLoss['operational_expense'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5 font-semibold bg-amber-50/60">
                    <span>Laba Bersih</span>
                    <span class="tnum">Rp {{ number_format($profitLoss['net_profit'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Stok menipis --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-ink/10 flex items-center gap-2.5">
                <span class="h-7 w-7 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.86 1.8 18a1.5 1.5 0 0 0 1.3 2.25h17.8A1.5 1.5 0 0 0 22.2 18L13.7 3.86a1.5 1.5 0 0 0-2.6 0Z"/></svg>
                </span>
                <h2 class="font-display font-semibold">Stok Menipis</h2>
            </div>
            @if ($lowStockProducts->isEmpty())
                <p class="px-6 py-8 text-sm text-ink/40 text-center">Tidak ada produk dengan stok rendah.</p>
            @else
                <div class="divide-y divide-ink/[0.06] text-sm">
                    @foreach ($lowStockProducts as $product)
                        <div class="flex items-center justify-between px-6 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $product->name }}</p>
                                <p class="text-xs text-ink/40">{{ $product->category->name ?? '—' }}</p>
                            </div>
                            <span class="tnum font-semibold rounded-full px-2.5 py-1 text-xs shrink-0
                                {{ $product->qty_on_hand == 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' }}">
                                {{ $product->qty_on_hand }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Produk terlaris (tabel) --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-ink/10 flex items-center gap-2.5">
                <span class="h-7 w-7 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg>
                </span>
                <h2 class="font-display font-semibold">Trend Produk Terlaris</h2>
            </div>
            @if ($topProducts->isEmpty())
                <p class="px-6 py-8 text-sm text-ink/40 text-center">Belum ada penjualan pada periode ini.</p>
            @else
                <div class="divide-y divide-ink/[0.06] text-sm">
                    @foreach ($topProducts as $i => $product)
                        <div class="flex items-center justify-between px-6 py-3.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="h-6 w-6 shrink-0 rounded-full bg-ink/5 text-ink/50 text-xs font-semibold flex items-center justify-center">{{ $i + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $product->product_name }}</p>
                                    <p class="text-xs text-ink/40">Rp {{ number_format($product->total_revenue, 0, ',', '.') }}</p>
                                </div>
                            </div>
                            <span class="tnum font-semibold rounded-full px-2.5 py-1 text-xs shrink-0 bg-emerald-100 text-emerald-800">
                                {{ (int) $product->total_qty }} terjual
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const trendLabels = @json($dailySalesTrend->keys());
        const salesData   = @json($dailySalesTrend->values());

        const cashDaily   = @json($cashFlow['daily']);
        const cashInData  = trendLabels.map(d => (cashDaily[d]?.in ?? 0));
        const cashOutData = trendLabels.map(d => (cashDaily[d]?.out ?? 0));

        const displayLabels = trendLabels.map(d => {
            const parts = d.split('-');
            return parts[2] + '/' + parts[1];
        });

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: displayLabels,
                datasets: [
                    {
                        label: 'Penjualan',
                        data: salesData,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.12)',
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Kas Masuk',
                        data: cashInData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.08)',
                        tension: 0.3,
                    },
                    {
                        label: 'Kas Keluar',
                        data: cashOutData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,0.08)',
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                scales: {
                    y: { ticks: { callback: (v) => 'Rp ' + (v / 1000) + 'rb' } },
                },
            },
        });

        new Chart(document.getElementById('expenseChart'), {
            type: 'doughnut',
            data: {
                labels: ['Biaya Operasional', 'Biaya Pembelian'],
                datasets: [{
                    data: [{{ $expenseBreakdown['operational'] }}, {{ $expenseBreakdown['purchase'] }}],
                    backgroundColor: ['#f59e0b', '#111214'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
            },
        });

        const topProducts = @json($topProducts);
        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: topProducts.map(p => p.product_name),
                datasets: [{
                    label: 'Qty Terjual',
                    data: topProducts.map(p => p.total_qty),
                    backgroundColor: '#f59e0b',
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { precision: 0 } } },
            },
        });
    });
</script>
@endpush
