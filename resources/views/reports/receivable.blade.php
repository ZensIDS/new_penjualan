@extends('layouts.app')

@section('title', 'Laporan Piutang (AR)')
@section('page-title', 'Laporan Piutang (AR)')

@section('content')
<div>

    {{-- KPI: agregasi SQL atas SELURUH SO belum lunas, independen dari pagination/pencarian --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Jumlah SO Belum Lunas</p>
            <p class="font-display font-semibold text-2xl tnum">{{ number_format($kpis['count'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Total Sisa Piutang</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400">Rp {{ number_format($kpis['total_outstanding'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4 gap-3">
        <form method="GET" action="{{ route('reports.receivable') }}" class="relative flex-1 max-w-sm">
            <svg viewBox="0 0 24 24" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nomor SO atau customer..." onchange="this.form.submit()"
                   class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
        </form>
        <a href="{{ route('reports.export.receivable') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600/20 bg-emerald-50 text-emerald-700 text-sm font-medium px-4 py-2 hover:bg-emerald-100 transition-colors whitespace-nowrap shrink-0">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            Export Excel
        </a>
    </div>

    <div class="rounded-2xl border border-ink/10 bg-white shadow-card divide-y divide-ink/[0.06] overflow-hidden">
        @forelse ($data as $so)
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
                            <p class="font-medium truncate">{{ $so['so_number'] }}</p>
                            <p class="text-xs text-ink/40">{{ $so['customer'] }} &middot; {{ $so['so_date'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 sm:gap-6 shrink-0">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $so['payment_status'] === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700' }}">
                            {{ $so['payment_status'] === 'partial' ? 'Sebagian' : 'Belum Bayar' }}
                        </span>
                        <span class="text-sm font-medium tnum w-32 text-right">Rp {{ number_format($so['remaining_balance'], 0, ',', '.') }}</span>
                    </div>
                </button>

                <div x-show="open" x-transition class="px-5 pb-4 pl-12">
                    <div class="grid grid-cols-3 gap-3 text-xs mb-3">
                        <div><p class="text-ink/40">Total SO</p><p class="tnum font-medium">Rp {{ number_format($so['total_amount'], 0, ',', '.') }}</p></div>
                        <div><p class="text-ink/40">Sudah Diterima</p><p class="tnum font-medium">Rp {{ number_format($so['paid_amount'], 0, ',', '.') }}</p></div>
                        <div><p class="text-ink/40">Sisa</p><p class="tnum font-medium">Rp {{ number_format($so['remaining_balance'], 0, ',', '.') }}</p></div>
                    </div>
                    @if (count($so['payment_history']) === 0)
                        <p class="text-xs text-ink/40 py-1">Belum ada pembayaran.</p>
                    @else
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-ink/40 text-left">
                                    <th class="py-1.5 font-medium">Tgl Bayar</th>
                                    <th class="py-1.5 font-medium">Metode</th>
                                    <th class="py-1.5 font-medium text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/[0.05]">
                                @foreach ($so['payment_history'] as $pmt)
                                    <tr>
                                        <td class="py-1.5 tnum">{{ $pmt['payment_date'] }}</td>
                                        <td class="py-1.5">{{ $pmt['method'] }}</td>
                                        <td class="py-1.5 tnum text-right font-medium">Rp {{ number_format($pmt['amount'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-5 py-10 text-center text-ink/40 text-sm">
                @if ($search)
                    Tidak ada SO yang cocok dengan pencarian.
                @else
                    Tidak ada piutang tertunggak. Semua SO sudah lunas 🎉
                @endif
            </p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $data->links() }}
    </div>
</div>
@endsection
