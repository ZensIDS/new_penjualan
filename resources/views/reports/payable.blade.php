@extends('layouts.app')

@section('title', 'Laporan Hutang (AP)')
@section('page-title', 'Laporan Hutang (AP)')

@section('content')
<div x-data="apReportPage({{ Illuminate\Support\Js::from($data) }})" x-cloak>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Jumlah PO Belum Lunas</p>
            <p class="font-display font-semibold text-2xl tnum" x-text="items.length"></p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Total Sisa Hutang</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400" x-text="'Rp ' + rp(totalOutstanding)"></p>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4 gap-3">
        <div class="relative flex-1 max-w-sm">
            <svg viewBox="0 0 24 24" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
            <input type="text" x-model="search" placeholder="Cari nomor PO atau supplier..."
                   class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
        </div>
        <a href="{{ route('reports.export.payable') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600/20 bg-emerald-50 text-emerald-700 text-sm font-medium px-4 py-2 hover:bg-emerald-100 transition-colors whitespace-nowrap shrink-0">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            Export Excel
        </a>
    </div>

    <div class="rounded-2xl border border-ink/10 bg-white shadow-card divide-y divide-ink/[0.06] overflow-hidden">
        <template x-for="po in filtered" :key="po.po_number">
            <div>
                <button type="button" @click="po.open = !po.open"
                        class="w-full flex items-center justify-between gap-4 px-5 py-4 hover:bg-amber-50/40 transition-colors text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-ink/40 transition-transform"
                             :class="po.open ? 'rotate-90' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 6l6 6-6 6"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="font-medium truncate" x-text="po.po_number"></p>
                            <p class="text-xs text-ink/40" x-text="po.supplier + ' &middot; ' + po.po_date"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 sm:gap-6 shrink-0">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                              :class="po.payment_status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700'"
                              x-text="po.payment_status === 'partial' ? 'Sebagian' : 'Belum Bayar'"></span>
                        <span class="text-sm font-medium tnum w-32 text-right" x-text="'Rp ' + rp(po.remaining_balance)"></span>
                    </div>
                </button>

                <div x-show="po.open" x-transition class="px-5 pb-4 pl-12">
                    <div class="grid grid-cols-3 gap-3 text-xs mb-3">
                        <div><p class="text-ink/40">Total PO</p><p class="tnum font-medium" x-text="'Rp ' + rp(po.total_amount)"></p></div>
                        <div><p class="text-ink/40">Sudah Dibayar</p><p class="tnum font-medium" x-text="'Rp ' + rp(po.paid_amount)"></p></div>
                        <div><p class="text-ink/40">Sisa</p><p class="tnum font-medium" x-text="'Rp ' + rp(po.remaining_balance)"></p></div>
                    </div>
                    <template x-if="po.payment_history.length === 0">
                        <p class="text-xs text-ink/40 py-1">Belum ada pembayaran.</p>
                    </template>
                    <template x-if="po.payment_history.length > 0">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-ink/40 text-left">
                                    <th class="py-1.5 font-medium">Tgl Bayar</th>
                                    <th class="py-1.5 font-medium">Metode</th>
                                    <th class="py-1.5 font-medium text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/[0.05]">
                                <template x-for="(pmt, i) in po.payment_history" :key="i">
                                    <tr>
                                        <td class="py-1.5 tnum" x-text="pmt.payment_date"></td>
                                        <td class="py-1.5" x-text="pmt.method"></td>
                                        <td class="py-1.5 tnum text-right font-medium" x-text="'Rp ' + rp(pmt.amount)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="filtered.length === 0">
            <p class="px-5 py-10 text-center text-ink/40 text-sm">
                <span x-show="items.length === 0">Tidak ada hutang tertunggak. Semua PO sudah lunas 🎉</span>
                <span x-show="items.length > 0">Tidak ada PO yang cocok dengan pencarian.</span>
            </p>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function apReportPage(initialData) {
        return {
            items: initialData.map(po => ({ ...po, open: false })),
            search: '',

            get filtered() {
                const q = this.search.trim().toLowerCase();
                if (!q) return this.items;
                return this.items.filter(po =>
                    po.po_number.toLowerCase().includes(q) || po.supplier.toLowerCase().includes(q)
                );
            },

            get totalOutstanding() {
                return this.items.reduce((sum, po) => sum + po.remaining_balance, 0);
            },

            rp(value) {
                return new Intl.NumberFormat('id-ID').format(Math.round(value || 0));
            },
        };
    }
</script>
@endpush
