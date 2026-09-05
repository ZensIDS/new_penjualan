@extends('layouts.app')

@section('title', 'Laporan Retur Pembelian')
@section('page-title', 'Laporan Retur Pembelian (PO)')

@section('content')

    @include('reports.partials.date-filter', ['routeName' => 'reports.purchase-return', 'exportRouteName' => 'reports.export.purchase-return'])

    {{-- KPI: agregasi SQL atas SELURUH retur pada rentang tanggal, independen dari pagination/pencarian --}}
    <div class="grid grid-cols-1 @4xl:grid-cols-2 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Jumlah Retur</p>
            <p class="font-display font-semibold text-2xl tnum">{{ number_format($kpis['count'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Total Nilai Retur</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400">Rp {{ number_format($kpis['total'], 0, ',', '.') }}</p>
        </div>
    </div>
    <p class="text-xs text-ink/40 -mt-3 mb-6">
        Mengurangi hutang ke supplier &mdash; belum memengaruhi Laba Rugi (lihat catatan di Laporan Laba Rugi).
    </p>

    <form method="GET" action="{{ route('reports.purchase-return') }}" class="relative max-w-sm mb-4">
        <input type="hidden" name="start_date" value="{{ $startDate }}">
        <input type="hidden" name="end_date" value="{{ $endDate }}">
        <svg viewBox="0 0 24 24" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari no. retur, no. PO, atau supplier..." onchange="this.form.submit()"
               class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
    </form>

    <div class="rounded-2xl border border-ink/10 bg-white shadow-card divide-y divide-ink/[0.06] overflow-hidden">
        @forelse ($data as $r)
            <div x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between gap-4 px-5 py-4 hover:bg-amber-50/40 transition-colors text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-ink/40 transition-transform"
                             :class="open ? 'rotate-90' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 6l6 6-6 6"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="font-medium truncate">{{ $r['return_number'] }}</p>
                            <p class="text-xs text-ink/40">
                                {{ $r['supplier'] }} &middot; PO {{ $r['po_number'] }} &middot;
                                {{ \Illuminate\Support\Carbon::parse($r['return_date'])->translatedFormat('d M Y') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 sm:gap-6 shrink-0">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-amber-100 text-amber-800">
                            {{ count($r['items']) }} item
                        </span>
                        <span class="text-sm font-medium tnum w-32 text-right text-amber-700">Rp {{ number_format($r['total_amount'], 0, ',', '.') }}</span>
                    </div>
                </button>

                <div x-show="open" x-transition class="px-5 pb-4 pl-12">
                    @if ($r['note'])
                        <p class="text-xs mb-3"><span class="text-ink/40">Catatan:</span> {{ $r['note'] }}</p>
                    @endif
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-ink/40 text-left">
                                <th class="py-1.5 font-medium">Produk</th>
                                <th class="py-1.5 font-medium text-right">Qty</th>
                                <th class="py-1.5 font-medium text-right">Harga Beli</th>
                                <th class="py-1.5 font-medium text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/[0.05]">
                            @foreach ($r['items'] as $item)
                                <tr>
                                    <td class="py-1.5">{{ $item['product'] }}</td>
                                    <td class="py-1.5 tnum text-right">{{ $item['qty'] }}</td>
                                    <td class="py-1.5 tnum text-right">Rp {{ number_format($item['buy_price'], 0, ',', '.') }}</td>
                                    <td class="py-1.5 tnum text-right font-medium">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-ink/40 text-sm">
                @if ($search)
                    Tidak ada retur yang cocok dengan pencarian.
                @else
                    Tidak ada retur pembelian pada periode ini.
                @endif
            </p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $data->links() }}
    </div>

@endsection
