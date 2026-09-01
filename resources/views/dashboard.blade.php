@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

    <div class="mb-8">
        <p class="text-sm text-ink/50">Ringkasan periode <span class="font-medium text-ink/70">{{ now()->translatedFormat('F Y') }}</span></p>
    </div>

    {{-- KPI utama --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Pendapatan Bulan Ini</p>
            <p class="font-display font-semibold text-2xl tnum">
                Rp {{ number_format($profitLoss['revenue'], 0, ',', '.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Laba Bersih</p>
            <p class="font-display font-semibold text-2xl tnum {{ $profitLoss['net_profit'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                Rp {{ number_format($profitLoss['net_profit'], 0, ',', '.') }}
            </p>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Nilai Stok Saat Ini</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400">
                Rp {{ number_format($stockValue, 0, ',', '.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Kas Bersih Bulan Ini</p>
            <p class="font-display font-semibold text-2xl tnum {{ $cashFlow['net_cash'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                Rp {{ number_format($cashFlow['net_cash'], 0, ',', '.') }}
            </p>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

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
                    <span class="text-ink/60">Pendapatan Penjualan</span>
                    <span class="tnum">Rp {{ number_format($profitLoss['revenue'], 0, ',', '.') }}</span>
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

    </div>

@endsection
