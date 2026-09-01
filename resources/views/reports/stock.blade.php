@extends('layouts.app')

@section('title', 'Laporan Stok')
@section('page-title', 'Laporan Stok')

@section('content')
<div x-data="stockReportPage({{ Illuminate\Support\Js::from($data) }})" x-cloak>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Produk Terdaftar</p>
            <p class="font-display font-semibold text-2xl tnum" x-text="items.length"></p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Total Unit Stok</p>
            <p class="font-display font-semibold text-2xl tnum" x-text="totalQty.toLocaleString('id-ID')"></p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Total Nilai Stok</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400" x-text="'Rp ' + rp(totalValue)"></p>
        </div>
    </div>

    {{-- Nilai stok per kategori --}}
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-ink/10">
            <h2 class="font-display font-semibold">Nilai Stok per Kategori</h2>
        </div>
        <div class="p-4">
            <canvas id="stockByCategoryChart" height="90"></canvas>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4 gap-3">
        <div class="relative flex-1 max-w-sm">
            <svg viewBox="0 0 24 24" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
            <input type="text" x-model="search" placeholder="Cari nama produk..."
                   class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
        </div>
        <a href="{{ route('stock.index') }}" class="text-sm font-medium text-ink/50 hover:text-ink whitespace-nowrap">
            Kelola stok &rarr;
        </a>
    </div>

    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-ink/40 uppercase tracking-wide border-b border-ink/[0.06]">
                        <th class="px-6 py-3 font-medium">Produk</th>
                        <th class="px-6 py-3 font-medium">Kategori</th>
                        <th class="px-6 py-3 font-medium text-right">Qty</th>
                        <th class="px-6 py-3 font-medium text-right">Nilai Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/[0.06]">
                    <template x-for="p in filtered" :key="p.product_id">
                        <tr>
                            <td class="px-6 py-3 font-medium" x-text="p.name"></td>
                            <td class="px-6 py-3 text-ink/50" x-text="p.category || '—'"></td>
                            <td class="px-6 py-3 text-right">
                                <span class="tnum font-semibold rounded-full px-2.5 py-1 text-xs" :class="badgeClass(p.total_qty)" x-text="p.total_qty"></span>
                            </td>
                            <td class="px-6 py-3 text-right tnum" x-text="'Rp ' + rp(p.stock_value)"></td>
                        </tr>
                    </template>
                    <template x-if="filtered.length === 0">
                        <tr><td colspan="4" class="px-6 py-10 text-center text-ink/40">Tidak ada produk yang cocok dengan pencarian.</td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    function stockReportPage(initialData) {
        return {
            items: initialData,
            search: '',

            get filtered() {
                const q = this.search.trim().toLowerCase();
                if (!q) return this.items;
                return this.items.filter(p => p.name.toLowerCase().includes(q));
            },

            get totalQty() {
                return this.items.reduce((sum, p) => sum + p.total_qty, 0);
            },

            get totalValue() {
                return this.items.reduce((sum, p) => sum + p.stock_value, 0);
            },

            rp(value) {
                return new Intl.NumberFormat('id-ID').format(Math.round(value || 0));
            },

            badgeClass(qty) {
                if (qty == 0) return 'bg-red-100 text-red-700';
                if (qty <= 5) return 'bg-amber-100 text-amber-800';
                return 'bg-emerald-100 text-emerald-700';
            },
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        const data = @json($data);
        const byCategory = {};
        data.forEach(p => {
            const cat = p.category || 'Tanpa Kategori';
            byCategory[cat] = (byCategory[cat] || 0) + p.stock_value;
        });

        new Chart(document.getElementById('stockByCategoryChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(byCategory),
                datasets: [{
                    label: 'Nilai Stok',
                    data: Object.values(byCategory),
                    backgroundColor: '#f59e0b',
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { ticks: { callback: (v) => 'Rp ' + (v / 1000) + 'rb' } } },
            },
        });
    });
</script>
@endpush
