@extends('layouts.app')

@section('title', 'Laporan Laba Rugi')
@section('page-title', 'Laporan Laba Rugi')

@section('content')

    @include('reports.partials.date-filter', ['routeName' => 'reports.profit-loss', 'exportRouteName' => 'reports.export.profit-loss'])

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
                    <span class="text-ink/60">Pendapatan Penjualan (diterima)</span>
                    <span class="tnum">Rp {{ number_format($data['revenue_paid'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-ink/60 flex items-center gap-1.5">
                        Pendapatan Tertahan
                        <span class="text-[10px] font-medium text-amber-700 bg-amber-50 rounded-full px-1.5 py-0.5">belum dibayar</span>
                    </span>
                    <span class="tnum">Rp {{ number_format($data['revenue_pending'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-ink/60">HPP (FIFO)</span>
                    <span class="tnum">Rp {{ number_format($data['hpp'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5 font-medium">
                    <span>Laba Kotor</span>
                    <span class="tnum">Rp {{ number_format($data['gross_profit'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <span class="text-ink/60">Biaya Operasional</span>
                    <span class="tnum">Rp {{ number_format($data['operational_expense'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3.5 font-semibold bg-amber-50/60">
                    <span>Laba Bersih</span>
                    <span class="tnum {{ $data['net_profit'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                        Rp {{ number_format($data['net_profit'], 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Biaya operasional per kategori --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-ink/10">
                <h2 class="font-display font-semibold">Biaya per Kategori</h2>
                <p class="text-xs text-ink/40 mt-0.5">Rincian biaya operasional</p>
            </div>
            @if ($data['expense_by_category']->isEmpty())
                <p class="px-6 py-8 text-sm text-ink/40 text-center">Tidak ada biaya operasional pada periode ini.</p>
            @else
                <div class="p-4">
                    <canvas id="expenseCategoryChart" height="180"></canvas>
                </div>
                <div class="divide-y divide-ink/[0.06] text-sm">
                    @foreach ($data['expense_by_category'] as $row)
                        <div class="flex items-center justify-between px-6 py-3">
                            <span class="text-ink/60">{{ $row->name }}</span>
                            <span class="tnum">Rp {{ number_format($row->total, 0, ',', '.') }}</span>
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
        const el = document.getElementById('expenseCategoryChart');
        if (!el) return;

        const categories = @json($data['expense_by_category']->pluck('name'));
        const totals = @json($data['expense_by_category']->pluck('total'));
        const palette = ['#f59e0b', '#111214', '#10b981', '#ef4444', '#6366f1', '#eab308', '#0ea5e9', '#ec4899'];

        new Chart(el, {
            type: 'doughnut',
            data: {
                labels: categories,
                datasets: [{
                    data: totals,
                    backgroundColor: categories.map((_, i) => palette[i % palette.length]),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
            },
        });
    });
</script>
@endpush
