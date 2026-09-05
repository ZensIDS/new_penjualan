@extends('layouts.app')

@section('page-title', 'Sales Order')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-2xl font-display font-semibold tracking-tight">{{ $salesOrders->total() }}</p>
            <p class="text-sm text-ink/50">transaksi penjualan tercatat</p>
        </div>

        @if (auth()->user()->isSuperadmin())
            <a
                href="{{ route('sales-orders.create') }}"
                class="inline-flex items-center gap-2 text-sm font-semibold bg-gradient-to-r from-amber-400 to-amber-500 text-ink px-5 py-2.5 rounded-xl shadow-glow hover:brightness-105 active:scale-[0.98] transition-all"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Buat Transaksi
            </a>
        @endif
    </div>

    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-ink/[0.03] text-left text-ink/50">
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">No. SO</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Customer</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Total</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Sisa Piutang</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/[0.06]">
                    @forelse ($salesOrders as $so)
                        <tr class="hover:bg-amber-50/40 transition-colors">
                            <td class="px-5 py-3.5 font-medium tnum">{{ $so->so_number }}</td>
                            <td class="px-5 py-3.5 text-ink/60 tnum">{{ $so->so_date->translatedFormat('d M Y') }}</td>
                            <td class="px-5 py-3.5">{{ $so->customer->name ?? 'Customer umum' }}</td>
                            <td class="px-5 py-3.5 text-right tnum">Rp {{ number_format($so->total_amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-3.5 text-right tnum {{ $so->remaining_balance > 0 ? 'text-red-700 font-medium' : 'text-ink/40' }}">
                                Rp {{ number_format($so->remaining_balance, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5">
                                @php
                                    $statusStyle = [
                                        'paid'    => 'bg-emerald-100 text-emerald-700',
                                        'partial' => 'bg-amber-100 text-amber-800',
                                        'unpaid'  => 'bg-red-100 text-red-700',
                                    ][$so->payment_status] ?? 'bg-ink/[0.06] text-ink/60';
                                    $statusLabel = [
                                        'paid'    => 'Lunas',
                                        'partial' => 'Sebagian',
                                        'unpaid'  => 'Belum Bayar',
                                    ][$so->payment_status] ?? $so->payment_status;
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusStyle }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('sales-orders.show', $so) }}" class="text-ink/60 hover:text-ink font-medium transition-colors">
                                    Lihat &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-ink/40">Belum ada transaksi penjualan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $salesOrders->links() }}
    </div>
</div>
@endsection
