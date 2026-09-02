@extends('layouts.app')

@section('title', 'Laporan Arus Kas')
@section('page-title', 'Laporan Arus Kas')

@section('content')

    @include('reports.partials.date-filter', ['routeName' => 'reports.cash-flow', 'exportRouteName' => 'reports.export.cash-flow'])

    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Kas Masuk</p>
            <p class="font-display font-semibold text-2xl tnum text-emerald-700">
                Rp {{ number_format($data['total_in'], 0, ',', '.') }}
            </p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Kas Keluar</p>
            <p class="font-display font-semibold text-2xl tnum text-red-700">
                Rp {{ number_format($data['total_out'], 0, ',', '.') }}
            </p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Kas Bersih (Masuk &ndash; Keluar)</p>
            <p class="font-display font-semibold text-2xl tnum {{ $data['net_cash'] < 0 ? 'text-red-400' : 'text-amber-400' }}">
                Rp {{ number_format($data['net_cash'], 0, ',', '.') }}
            </p>
        </div>
    </div>

    {{-- Grafik harian --}}
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-ink/10">
            <h2 class="font-display font-semibold">Arus Kas Harian</h2>
            <p class="text-xs text-ink/40 mt-0.5">Kas masuk vs. kas keluar per hari</p>
        </div>
        <div class="p-4">
            @if ($data['daily']->isEmpty())
                <p class="py-10 text-sm text-ink/40 text-center">Tidak ada transaksi kas pada periode ini.</p>
            @else
                <canvas id="cashFlowChart" height="90"></canvas>
            @endif
        </div>
    </div>

    {{-- Daftar transaksi. Diurutkan dari server (transaction_date DESC, lalu
         id DESC) supaya transaksi yang paling baru dicatat selalu tampil
         paling atas — termasuk saat beberapa transaksi terjadi di tanggal
         yang sama. Dipaginasi 25/halaman, terpisah dari agregat KPI & grafik
         di atas yang tetap menghitung semua transaksi pada periode ini. --}}
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-ink/10">
            <h2 class="font-display font-semibold">Rincian Transaksi</h2>
        </div>
        @if ($details->isEmpty())
            <p class="px-6 py-10 text-sm text-ink/40 text-center">Tidak ada transaksi kas pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-ink/40 uppercase tracking-wide border-b border-ink/[0.06]">
                            <th class="px-6 py-3 font-medium">Tanggal</th>
                            <th class="px-6 py-3 font-medium">Keterangan</th>
                            <th class="px-6 py-3 font-medium">Arah</th>
                            <th class="px-6 py-3 font-medium text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/[0.06]">
                        @foreach ($details as $row)
                            <tr>
                                <td class="px-6 py-3 tnum whitespace-nowrap">{{ $row->transaction_date->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-ink/70">{{ $row->description }}</td>
                                <td class="px-6 py-3">
                                    <span class="text-xs font-medium rounded-full px-2.5 py-1
                                        {{ $row->direction === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' }}">
                                        {{ $row->direction === 'in' ? 'Masuk' : 'Keluar' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right tnum font-medium {{ $row->direction === 'in' ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $row->direction === 'in' ? '+' : '-' }}Rp {{ number_format($row->amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">
                {{ $details->links() }}
            </div>
        @endif
    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('cashFlowChart');
        if (!el) return;

        const daily = @json($data['daily']);
        const labels = Object.keys(daily);
        const displayLabels = labels.map(d => {
            const parts = d.split('-');
            return parts[2] + '/' + parts[1];
        });

        new Chart(el, {
            type: 'bar',
            data: {
                labels: displayLabels,
                datasets: [
                    {
                        label: 'Kas Masuk',
                        data: labels.map(d => daily[d].in),
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                    },
                    {
                        label: 'Kas Keluar',
                        data: labels.map(d => daily[d].out),
                        backgroundColor: '#ef4444',
                        borderRadius: 4,
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
    });
</script>
@endpush
