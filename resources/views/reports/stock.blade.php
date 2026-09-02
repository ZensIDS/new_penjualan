@extends('layouts.app')

@section('title', 'Laporan Stok')
@section('page-title', 'Laporan Stok')

@section('content')
<div>

    {{-- KPI: agregasi SQL atas SELURUH data, independen dari pagination/pencarian --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Produk Terdaftar</p>
            <p class="font-display font-semibold text-2xl tnum">{{ number_format($kpis['product_count'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Total Unit Stok</p>
            <p class="font-display font-semibold text-2xl tnum">{{ number_format($kpis['total_qty'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Total Nilai Stok</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400">Rp {{ number_format($kpis['total_value'], 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Nilai stok per kategori: diagregasi langsung via SQL GROUP BY (byCategory),
         bukan dihitung ulang di JS dari seluruh data produk. --}}
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-ink/10">
            <h2 class="font-display font-semibold">Nilai Stok per Kategori</h2>
        </div>
        <div class="p-4">
            <canvas id="stockByCategoryChart" height="90"></canvas>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4 gap-3">
        <form method="GET" action="{{ route('reports.stock') }}" class="relative flex-1 max-w-sm">
            <svg viewBox="0 0 24 24" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama produk..." onchange="this.form.submit()"
                   class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
        </form>
        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('reports.export.stock') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600/20 bg-emerald-50 text-emerald-700 text-sm font-medium px-4 py-2 hover:bg-emerald-100 transition-colors whitespace-nowrap">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                Export Excel
            </a>
            <a href="{{ route('stock.index') }}" class="text-sm font-medium text-ink/50 hover:text-ink whitespace-nowrap">
                Kelola stok &rarr;
            </a>
        </div>
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
                    @forelse ($data as $p)
                        <tr x-data="{ open: false }">
                            <td colspan="4" class="p-0">
                                <button type="button" @click="open = !open"
                                        class="w-full flex items-center gap-3 px-6 py-3 hover:bg-amber-50/40 transition-colors text-left"
                                        {{ count($p['batches']) === 0 ? 'disabled' : '' }}>
                                    <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 transition-transform {{ count($p['batches']) === 0 ? 'text-ink/15' : 'text-ink/40' }}"
                                         :class="open ? 'rotate-90' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 6l6 6-6 6"/>
                                    </svg>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate">{{ $p['name'] }}</p>
                                        <p class="text-xs text-ink/40">
                                            {{ $p['category'] ?? 'Tanpa kategori' }}
                                            @if (count($p['batches']) > 0)
                                                &middot; {{ count($p['batches']) }} batch
                                            @endif
                                        </p>
                                    </div>
                                    <span class="tnum font-semibold rounded-full px-2.5 py-1 text-xs shrink-0
                                        {{ $p['total_qty'] == 0 ? 'bg-red-100 text-red-700' : ($p['total_qty'] <= 5 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700') }}">{{ $p['total_qty'] }}</span>
                                    <span class="tnum text-sm w-32 text-right shrink-0">Rp {{ number_format($p['stock_value'], 0, ',', '.') }}</span>
                                </button>

                                <div x-show="open" x-transition class="px-6 pb-4 pl-16">
                                    @if (count($p['batches']) === 0)
                                        <p class="text-xs text-ink/40 py-1">Tidak ada batch stok tersisa untuk produk ini.</p>
                                    @else
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-ink/40 text-left">
                                                    <th class="py-1.5 font-medium">Tgl Batch (masuk)</th>
                                                    <th class="py-1.5 font-medium text-right">Harga Beli</th>
                                                    <th class="py-1.5 font-medium text-right">Qty Masuk</th>
                                                    <th class="py-1.5 font-medium text-right">Qty Saat Ini</th>
                                                    <th class="py-1.5 font-medium text-right">Nilai Batch</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-ink/[0.05]">
                                                @foreach ($p['batches'] as $b)
                                                    <tr>
                                                        <td class="py-1.5 tnum">{{ $b['batch_date'] }}</td>
                                                        <td class="py-1.5 tnum text-right">Rp {{ number_format($b['buy_price'], 0, ',', '.') }}</td>
                                                        <td class="py-1.5 tnum text-right">{{ $b['qty_in'] }}</td>
                                                        <td class="py-1.5 tnum text-right font-medium">{{ $b['qty_remaining'] }}</td>
                                                        <td class="py-1.5 tnum text-right font-medium">Rp {{ number_format($b['qty_remaining'] * $b['buy_price'], 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr class="border-t border-ink/[0.08] font-semibold">
                                                    <td class="py-1.5" colspan="3">Total</td>
                                                    <td class="py-1.5 tnum text-right">{{ $p['total_qty'] }}</td>
                                                    <td class="py-1.5 tnum text-right">Rp {{ number_format($p['stock_value'], 0, ',', '.') }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-ink/40">{{ $search ? 'Tidak ada produk yang cocok dengan pencarian.' : 'Belum ada produk.' }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t border-ink/10 bg-amber-50/60 font-semibold text-sm">
                        <td class="px-6 py-3" colspan="2">Total (semua produk)</td>
                        <td class="px-6 py-3 text-right tnum">{{ number_format($kpis['total_qty'], 0, ',', '.') }}</td>
                        <td class="px-6 py-3 text-right tnum">Rp {{ number_format($kpis['total_value'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="px-6 py-4">
            {{ $data->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Sumbernya sudah agregat dari server (GROUP BY kategori di SQL),
        // bukan dihitung ulang dari seluruh data produk di JS.
        const byCategory = @json($byCategory);

        new Chart(document.getElementById('stockByCategoryChart'), {
            type: 'bar',
            data: {
                labels: byCategory.map(c => c.name),
                datasets: [{
                    label: 'Nilai Stok',
                    data: byCategory.map(c => c.value),
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
