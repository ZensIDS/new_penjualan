@extends('layouts.app')

@section('page-title', 'Detail Bongkar Unit')

@section('content')
<div x-data="{ confirmOpen: false }">
    <div class="mb-6">
        <a href="{{ route('stock-conversions.index') }}" class="text-sm text-ink/50 hover:text-ink inline-flex items-center gap-1 mb-2">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Kembali ke daftar bongkar
        </a>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-display font-semibold tracking-tight tnum">{{ $conversion->conversion_number }}</h2>
                    @if ($conversion->isDraft())
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">Belum Selesai</span>
                    @else
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800">Selesai</span>
                    @endif
                </div>
                <p class="text-sm text-ink/50 mt-1">
                    {{ $conversion->conversion_date->format('d M Y') }} &middot;
                    {{ $conversion->source_qty }} {{ $conversion->sourceProduct->unit }} {{ $conversion->sourceProduct->name }}
                    dibongkar jadi {{ $conversion->results->count() }} jenis komponen
                </p>
            </div>

            @if (auth()->user()->isSuperadmin())
                <div class="flex items-center gap-2">
                    @if ($conversion->isDraft())
                        <a href="{{ route('stock-conversions.continue', $conversion) }}"
                           class="text-sm font-semibold px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-ink shadow-glow hover:brightness-105 active:scale-[0.98] transition-all">
                            Lanjutkan Bongkar
                        </a>
                    @endif
                    <button type="button" @click="confirmOpen = true"
                            class="text-sm font-medium px-4 py-2.5 rounded-xl border border-red-600/20 text-red-700 hover:bg-red-50 transition-colors">
                        Batalkan Pembongkaran
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if ($conversion->isDraft())
        <div class="rounded-xl border border-amber-500/25 bg-amber-50/70 px-4 py-3.5 text-sm text-amber-800 mb-6 flex items-start gap-2">
            <svg viewBox="0 0 24 24" class="h-4 w-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
            <span>
                Pembongkaran ini ditandai belum lengkap — masih bisa ditambah komponen lewat "Lanjutkan Bongkar".
                Seluruh HPP unit sudah dibagi ke komponen yang tercatat di bawah, dan akan dibagi ulang otomatis
                kalau ada komponen baru ditambahkan.
            </span>
        </div>
    @endif

    {{-- Ringkasan nilai --}}
    <div class="grid grid-cols-1 @2xl:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-5">
            <p class="text-xs font-medium text-ink/45 uppercase tracking-wide">HPP Unit Dibongkar</p>
            <p class="font-display font-semibold text-xl tnum mt-1.5">Rp {{ number_format($conversion->total_hpp, 0, ',', '.') }}</p>
        </div>
        @php
            // Yang paling dipedulikan user: kalau unit ini dipecah, total ecerannya
            // laku berapa? Dipakai harga jual terakhir tiap komponen di Sales Order.
            $estimatedSalesValue = $conversion->results->sum(fn($r) => (float) $r->ref_sell_price * $r->qty);
            $estimatedMargin = $estimatedSalesValue - (float) $conversion->total_hpp;
        @endphp
    </div>

    @if ($conversion->note)
        <div class="rounded-xl border border-ink/10 bg-white/60 px-4 py-3.5 text-sm text-ink/70 shadow-card mb-6">
            {{ $conversion->note }}
        </div>
    @endif

    {{-- Komponen hasil --}}
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-ink/10">
            <h3 class="font-display font-semibold">Komponen Hasil</h3>
            <p class="text-xs text-ink/50 mt-0.5">
                Tiap komponen masuk stok sebagai batch tersendiri dan dijual lewat Sales Order seperti biasa.
                
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-ink/[0.03] text-left text-ink/50">
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Komponen</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Qty</th>
                        {{-- <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">HPP / Unit</th> --}}
                        {{-- <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Total HPP</th> --}}
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Sisa di Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/[0.06]">
                    @foreach ($conversion->results as $result)
                        <tr>
                            <td class="px-5 py-3.5">
                                {{ $result->product->name }}
                                @if ($result->ref_sell_price !== null)
                                    <span class="text-xs text-ink/40 tnum">&middot; acuan jual Rp {{ number_format($result->ref_sell_price, 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right tnum">{{ $result->qty }} {{ $result->product->unit }}</td>
                            {{-- <td class="px-5 py-3.5 text-right tnum">Rp {{ number_format($result->buy_price, 0, ',', '.') }}</td> --}}
                            {{-- <td class="px-5 py-3.5 text-right tnum">Rp {{ number_format($result->hpp_total, 0, ',', '.') }}</td> --}}
                            <td class="px-5 py-3.5 text-right tnum {{ $result->qty_used > 0 ? 'text-amber-700 font-medium' : 'text-ink/40' }}">
                                {{ $result->stockBatch?->qty_remaining ?? 0 }} / {{ $result->qty }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Batch sumber yang dipotong --}}
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-ink/10">
            <h3 class="font-display font-semibold">Batch Sumber yang Dipotong (FIFO)</h3>
            <p class="text-xs text-ink/50 mt-0.5">Dari batch mana saja unit utuh ini diambil, beserta HPP aslinya.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-ink/[0.03] text-left text-ink/50">
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Batch</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Asal</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Qty Diambil</th>
                        {{-- <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">HPP / Unit</th> --}}
                        {{-- <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Subtotal</th> --}}
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/[0.06]">
                    @foreach ($conversion->sources as $source)
                        <tr>
                            <td class="px-5 py-3.5 tnum">#{{ $source->stock_batch_id }}</td>
                            <td class="px-5 py-3.5 text-ink/60">{{ $source->stockBatch?->origin_label ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-right tnum">{{ $source->qty_taken }}</td>
                            {{-- <td class="px-5 py-3.5 text-right tnum">Rp {{ number_format($source->buy_price_at_time, 0, ',', '.') }}</td> --}}
                            {{-- <td class="px-5 py-3.5 text-right tnum">Rp {{ number_format($source->hpp_subtotal, 0, ',', '.') }}</td> --}}
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Konfirmasi pembatalan --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-ink/40" @click="confirmOpen = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-panel p-6">
            <h3 class="font-display font-semibold text-lg">Batalkan pembongkaran ini?</h3>
            <p class="text-sm text-ink/60 mt-2 leading-relaxed">
                Komponen hasil bongkar akan ditarik dari stok dan
                {{ $conversion->source_qty }} {{ $conversion->sourceProduct->unit }}
                {{ $conversion->sourceProduct->name }} dikembalikan ke batch asalnya.
                Hanya bisa dilakukan selama tidak ada komponen yang sudah terjual.
            </p>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" @click="confirmOpen = false"
                        class="text-sm font-medium px-4 py-2.5 rounded-xl border border-ink/12 hover:bg-ink/[0.03] transition-colors">
                    Tidak jadi
                </button>
                <form method="POST" action="{{ route('stock-conversions.destroy', $conversion) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-sm font-semibold px-5 py-2.5 rounded-xl bg-red-600 text-white hover:bg-red-700 transition-colors">
                        Ya, batalkan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection