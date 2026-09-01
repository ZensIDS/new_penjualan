@extends('layouts.app')

@section('page-title', 'Detail Transaksi Penjualan')

@php
    $statusStyle = [
        'paid'    => 'bg-emerald-100 text-emerald-700',
        'partial' => 'bg-amber-100 text-amber-800',
        'unpaid'  => 'bg-red-100 text-red-700',
    ][$salesOrder->payment_status] ?? 'bg-ink/[0.06] text-ink/60';
    $statusLabel = [
        'paid'    => 'Lunas',
        'partial' => 'Sebagian',
        'unpaid'  => 'Belum Bayar',
    ][$salesOrder->payment_status] ?? $salesOrder->payment_status;
@endphp

@section('content')
<div>
    <a href="{{ route('sales-orders.index') }}" class="text-sm text-ink/50 hover:text-ink inline-flex items-center gap-1 mb-4">
        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        Kembali ke daftar SO
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3 mb-1.5">
                <h2 class="text-2xl font-display font-semibold tracking-tight tnum">{{ $salesOrder->so_number }}</h2>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusStyle }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <p class="text-sm text-ink/50">
                {{ $salesOrder->so_date->translatedFormat('d F Y') }} &middot; {{ $salesOrder->customer->name }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">

            {{-- Item barang --}}
            <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
                <div class="px-6 py-4 border-b border-ink/10">
                    <h3 class="font-display font-semibold">Item Barang</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-ink/[0.03] text-left text-ink/50">
                                <th class="px-6 py-3 font-semibold text-xs uppercase tracking-wide">Produk</th>
                                <th class="px-6 py-3 font-semibold text-xs uppercase tracking-wide text-right">Qty</th>
                                <th class="px-6 py-3 font-semibold text-xs uppercase tracking-wide text-right">Harga Jual</th>
                                <th class="px-6 py-3 font-semibold text-xs uppercase tracking-wide text-right">Subtotal</th>
                                <th class="px-6 py-3 font-semibold text-xs uppercase tracking-wide text-right">HPP</th>
                                <th class="px-6 py-3 font-semibold text-xs uppercase tracking-wide text-right">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/[0.06]">
                            @foreach ($salesOrder->items as $item)
                                <tr>
                                    <td class="px-6 py-3.5">
                                        <p class="font-medium">{{ $item->product->name }}</p>
                                        <p class="text-xs text-ink/40">{{ $item->product->unit }}</p>
                                    </td>
                                    <td class="px-6 py-3.5 text-right tnum">{{ $item->qty }}</td>
                                    <td class="px-6 py-3.5 text-right tnum">Rp {{ number_format($item->sell_price, 0, ',', '.') }}</td>
                                    <td class="px-6 py-3.5 text-right tnum font-medium">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                    <td class="px-6 py-3.5 text-right tnum text-ink/50">Rp {{ number_format($item->hpp_subtotal, 0, ',', '.') }}</td>
                                    <td class="px-6 py-3.5 text-right tnum {{ $item->margin >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                        Rp {{ number_format($item->margin, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @if ($item->allocations->isNotEmpty())
                                    <tr>
                                        <td colspan="6" class="px-6 pb-3.5 -mt-1.5">
                                            <div class="rounded-xl bg-ink/[0.025] border border-ink/[0.06] px-4 py-2.5">
                                                <p class="text-xs font-medium text-ink/40 mb-1.5">Alokasi FIFO (batch tertua duluan)</p>
                                                <div class="flex flex-wrap gap-x-5 gap-y-1">
                                                    @foreach ($item->allocations as $alloc)
                                                        <p class="text-xs text-ink/60 tnum">
                                                            Batch #{{ $alloc->stock_batch_id }}
                                                            @if ($alloc->stockBatch)
                                                                ({{ $alloc->stockBatch->batch_date->translatedFormat('d M Y') }})
                                                            @endif
                                                            &mdash; {{ $alloc->qty_taken }} unit
                                                            &times; Rp {{ number_format($alloc->buy_price_at_time, 0, ',', '.') }}
                                                        </p>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-ink/[0.02]">
                                <td colspan="3" class="px-6 py-3.5 text-right font-medium text-ink/60">Total</td>
                                <td class="px-6 py-3.5 text-right font-display font-semibold tnum">
                                    Rp {{ number_format($salesOrder->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-3.5 text-right tnum text-ink/50">
                                    Rp {{ number_format($salesOrder->total_hpp, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-3.5 text-right font-semibold tnum {{ $salesOrder->gross_profit >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                    Rp {{ number_format($salesOrder->gross_profit, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($salesOrder->note)
                    <div class="px-6 py-4 border-t border-ink/10 text-sm text-ink/60">
                        <span class="font-medium text-ink/70">Catatan:</span> {{ $salesOrder->note }}
                    </div>
                @endif
            </div>

            {{-- Riwayat pembayaran --}}
            <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
                <div class="px-6 py-4 border-b border-ink/10">
                    <h3 class="font-display font-semibold">Riwayat Pembayaran</h3>
                </div>
                @if ($salesOrder->payments->isEmpty())
                    <p class="px-6 py-8 text-sm text-ink/40 text-center">Belum ada pembayaran tercatat.</p>
                @else
                    <div class="divide-y divide-ink/[0.06] text-sm">
                        @foreach ($salesOrder->payments->sortByDesc('payment_date') as $payment)
                            <div class="flex items-center justify-between px-6 py-3.5">
                                <div>
                                    <p class="font-medium">Rp {{ number_format($payment->amount, 0, ',', '.') }}</p>
                                    <p class="text-xs text-ink/40">
                                        {{ $payment->payment_date->translatedFormat('d M Y') }} &middot;
                                        {{ ['cash' => 'Tunai', 'transfer' => 'Transfer', 'other' => 'Lainnya'][$payment->method] ?? $payment->method }}
                                        @if ($payment->note) &middot; {{ $payment->note }} @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">

            {{-- Ringkasan pembayaran --}}
            <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
                <div class="px-6 py-4 border-b border-ink/10">
                    <h3 class="font-display font-semibold">Ringkasan</h3>
                </div>
                <div class="divide-y divide-ink/[0.06] text-sm">
                    <div class="flex items-center justify-between px-6 py-3.5">
                        <span class="text-ink/60">Total Transaksi</span>
                        <span class="tnum font-medium">Rp {{ number_format($salesOrder->total_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-3.5">
                        <span class="text-ink/60">Sudah Dibayar</span>
                        <span class="tnum font-medium text-emerald-700">Rp {{ number_format($salesOrder->paid_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between px-6 py-3.5 font-semibold bg-amber-50/60">
                        <span>Sisa Piutang</span>
                        <span class="tnum {{ $salesOrder->remaining_balance > 0 ? 'text-red-700' : '' }}">
                            Rp {{ number_format($salesOrder->remaining_balance, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Form tambah pembayaran --}}
            @if (auth()->user()->isSuperadmin() && $salesOrder->remaining_balance > 0)
                <div
                    x-data="soPaymentForm({{ (float) $salesOrder->remaining_balance }})"
                    x-cloak
                    class="rounded-2xl border border-ink/10 bg-white shadow-card p-6"
                >
                    <h3 class="font-display font-semibold mb-4">Tambah Pembayaran</h3>
                    <form method="POST" action="{{ route('sales-orders.payments.store', $salesOrder) }}">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1.5">Tanggal Bayar</label>
                                <input type="date" name="payment_date" value="{{ now()->toDateString() }}"
                                       class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5">
                                    Jumlah
                                    <button type="button" @click="useMax()" class="text-amber-700 hover:text-amber-800 font-normal text-xs">(isi lunas)</button>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-ink/40">Rp</span>
                                    <input type="text" inputmode="numeric"
                                           :value="formatRupiah(amount)"
                                           @input="setAmount(parseRupiah($event.target.value)); $event.target.value = formatRupiah(amount)"
                                           class="w-full rounded-xl border border-ink/12 pl-9 pr-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                    <input type="hidden" name="amount" :value="amount">
                                </div>
                                <p class="text-xs mt-1" :class="amount === remaining ? 'text-amber-700 font-medium' : 'text-ink/40'">
                                    Sisa piutang: Rp <span x-text="formatRupiah(remaining)"></span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5">Metode</label>
                                <select name="method" class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                    <option value="cash">Tunai</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5">Catatan</label>
                                <input type="text" name="note" placeholder="Opsional"
                                       class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                            </div>
                        </div>
                        <button type="submit"
                                class="mt-5 w-full text-sm font-semibold px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-ink shadow-glow hover:brightness-105 active:scale-[0.98] transition-all">
                            Simpan Pembayaran
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function soPaymentForm(remaining) {
        return {
            remaining,
            amount: '',
            // Cegah input nominal pembayaran melebihi sisa piutang — kalau user ngetik
            // lebih besar dari sisa, otomatis dipangkas balik ke batas maksimalnya.
            setAmount(value) {
                this.amount = value === '' ? '' : Math.min(value, this.remaining);
            },
            useMax() { this.setAmount(this.remaining); },
        };
    }
</script>
@endpush
