@extends('layouts.app')

@section('page-title', 'Lanjutkan Bongkar')

@section('content')
<div
    x-data="lanjutkanForm(
        {{ Illuminate\Support\Js::from($products) }},
        {{ Illuminate\Support\Js::from($conversion->results->map(fn($r) => [
            'result_id'    => $r->id,
            'product_id'   => $r->product_id,
            'product_name' => $r->product->name,
            'product_unit' => $r->product->unit,
            'qty'          => $r->qty,
            'qty_used'     => $r->qty_used,
            'buy_price'    => (float) $r->buy_price,
            'hpp_total'    => (float) $r->hpp_total,
        ])) }},
        {{ Illuminate\Support\Js::from(old('components', [])) }},
        {{ (float) $conversion->total_hpp }}
    )"
    x-cloak
>
    <div class="mb-6">
        <a href="{{ route('stock-conversions.show', $conversion) }}" class="text-sm text-ink/50 hover:text-ink inline-flex items-center gap-1 mb-2">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Kembali ke detail {{ $conversion->conversion_number }}
        </a>
        <h2 class="text-2xl font-display font-semibold tracking-tight">Lanjutkan Bongkar — {{ $conversion->conversion_number }}</h2>
        <p class="text-sm text-ink/50 mt-1">
            {{ $conversion->source_qty }} {{ $conversion->sourceProduct->unit }} {{ $conversion->sourceProduct->name }} &middot;
            HPP unit Rp {{ number_format($conversion->total_hpp, 0, ',', '.') }} (tetap, tidak berubah) — akan dibagi
            ulang ke semua komponen begitu kamu simpan.
        </p>
    </div>

    <form method="POST" action="{{ route('stock-conversions.continue.store', $conversion) }}" @submit="onSubmit">
        @csrf

        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6 mb-6">
            <h3 class="font-display font-semibold mb-1">Pembagian HPP ke Komponen</h3>
            <p class="text-xs text-ink/50 leading-relaxed">
                Otomatis, proporsional terhadap harga jual riil tiap komponen di Sales Order. Kamu cukup mencatat
                komponen apa saja yang keluar dan berapa banyak — HPP-nya dihitung sendiri oleh sistem dan terus
                menyesuaikan setiap kali ada penjualan baru. HPP yang sudah terlanjur keluar lewat penjualan tidak
                ikut berubah.
            </p>
        </div>

        {{-- Komponen yang sudah tercatat --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-ink/10">
                <h3 class="font-display font-semibold">Komponen yang Sudah Tercatat</h3>
                <p class="text-xs text-ink/50 mt-0.5">
                    Qty hanya bisa <span class="font-medium text-ink/70">ditambah</span>, tidak bisa dikurangi kalau sudah ada yang terjual.
                </p>
            </div>

            <div class="divide-y divide-ink/[0.06]">
                <template x-for="(row, index) in existing" :key="row.result_id">
                    <div class="p-5 grid grid-cols-1 @4xl:grid-cols-12 gap-3 sm:items-start">
                        <div class="@4xl:col-span-5">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">Komponen</label>
                            <p class="text-sm font-medium py-2.5" x-text="row.product_name"></p>
                        </div>

                        <div class="@4xl:col-span-3">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">Qty Total</label>
                            <input type="number" :min="row.qty_used || 1" :name="'existing['+index+'][qty]'" x-model.number="row.qty"
                                   class="w-full rounded-xl border px-3.5 py-2.5 text-sm tnum focus:outline-none focus:ring-4 transition-shadow"
                                   :class="row.qty < row.qty_used
                                        ? 'border-red-400 focus:border-red-500 focus:ring-red-500/15'
                                        : 'border-ink/12 focus:border-amber-500 focus:ring-amber-500/15'">
                            <input type="hidden" :name="'existing['+index+'][result_id]'" :value="row.result_id">
                            <p class="text-[11px] mt-1" :class="row.qty < row.qty_used ? 'text-red-600 font-medium' : 'text-ink/40'"
                               x-show="row.qty_used > 0">
                                Sudah terjual/terpakai <span x-text="row.qty_used"></span> — tidak bisa dikurangi di bawah itu.
                            </p>
                        </div>

                        <div class="@4xl:col-span-4">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">HPP Sekarang</label>
                            <p class="text-sm font-medium tnum py-2.5" x-text="'Rp ' + formatRupiah(row.hpp_total)"></p>
                            <p class="text-[11px] text-ink/40 -mt-1.5">
                                Rp <span x-text="formatRupiah(row.buy_price)"></span> / unit &middot; dihitung ulang setelah disimpan
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Komponen baru --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-ink/10 flex items-center gap-2">
                <h3 class="font-display font-semibold">Tambah Komponen Baru</h3>
                <button type="button" @click="addComponent()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:text-amber-800">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Baris
                </button>
            </div>

            <template x-if="components.length === 0">
                <p class="px-6 py-5 text-xs text-ink/40">Belum ada komponen baru ditambahkan. Klik "Tambah Baris" kalau ada komponen lain yang mau dicatat sekarang.</p>
            </template>

            <div class="divide-y divide-ink/[0.06]">
                {{-- Label ditulis di setiap baris supaya baris hasil "Tambah Baris"
                     tidak muncul tanpa keterangan kolom. --}}
                <template x-for="(row, index) in components" :key="row.key">
                    <div class="p-5 grid grid-cols-1 @4xl:grid-cols-12 gap-3 sm:items-start">
                        <div class="@4xl:col-span-6">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">Produk Komponen</label>
                            <select :name="'components['+index+'][product_id]'" x-init="initComponentSelect($el, row)"></select>
                        </div>

                        <div class="@4xl:col-span-3">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">Qty Total</label>
                            <input type="number" min="1" :name="'components['+index+'][qty]'" x-model.number="row.qty"
                                   class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                        </div>

                        <div class="@4xl:col-span-2">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">HPP</label>
                            <p class="text-xs text-ink/40 py-3">Dihitung otomatis</p>
                        </div>

                        <div class="@4xl:col-span-1 flex sm:justify-end sm:pt-6">
                            <button type="button" @click="removeComponent(row.key)"
                                    class="text-red-600/70 hover:text-red-700 p-1.5" title="Hapus baris">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-6 py-4 bg-ink/[0.02]">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink/60">HPP unit yang dibagi ke semua komponen</span>
                    <span class="font-medium tnum" x-text="'Rp ' + formatRupiah(totalHpp)"></span>
                </div>
            </div>
        </div>

        {{-- Tandai selesai --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6 mb-6">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" x-model="markComplete" name="mark_complete" value="1"
                       class="mt-0.5 h-4 w-4 rounded border-ink/30 text-amber-600 focus:ring-amber-500/30">
                <span>
                    <span class="text-sm font-semibold block">Ini komponen terakhir — tandai pembongkaran selesai</span>
                    <span class="text-xs text-ink/50 leading-relaxed block mt-0.5">
                        Kalau dicentang, transaksi ini dikunci dan tidak bisa ditambah komponen lagi. Kalau tidak
                        dicentang, kamu masih bisa buka "Lanjutkan Bongkar" lagi nanti. Pembagian HPP-nya sendiri
                        sama saja — selalu dibagi habis ke komponen yang sudah tercatat.
                    </span>
                </span>
            </label>

        </div>

        @error('error')<p class="text-xs text-red-600 mb-4">{{ $message }}</p>@enderror
        @error('components')<p class="text-xs text-red-600 mb-4">{{ $message }}</p>@enderror

        <p class="text-xs text-red-600 mb-4" x-show="blockingMessage" x-cloak x-text="blockingMessage"></p>

        <div class="flex justify-end gap-3">
            <a href="{{ route('stock-conversions.show', $conversion) }}"
               class="text-sm font-medium px-5 py-2.5 rounded-xl border border-ink/12 hover:bg-ink/[0.03] transition-colors">Batal</a>
            <button type="submit"
                    class="text-sm font-semibold px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-ink shadow-glow hover:brightness-105 active:scale-[0.98] transition-all"
                    x-text="markComplete ? 'Simpan &amp; Selesaikan' : 'Simpan Lanjutan'">
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function lanjutkanForm(products, initialExisting, initialNewComponents, totalHpp) {
        return {
            products,
            totalHpp,
            markComplete: false,

            existing: initialExisting.map(r => ({
                result_id: r.result_id,
                product_id: r.product_id,
                product_name: r.product_name,
                product_unit: r.product_unit,
                qty: r.qty,
                qty_used: r.qty_used || 0,
                buy_price: r.buy_price || 0,
                hpp_total: r.hpp_total || 0,
            })),

            components: initialNewComponents.map(c => ({
                key: Math.random().toString(36).slice(2),
                product_id: c.product_id || '',
                qty: c.qty || 1,
            })),

            addComponent() {
                this.components.push({
                    key: Math.random().toString(36).slice(2),
                    product_id: '',
                    qty: 1,
                });
            },

            removeComponent(key) {
                this.components = this.components.filter(c => c.key !== key);
            },

            get hasQtyBelowUsed() {
                return this.existing.some(r => (parseInt(r.qty) || 0) < (r.qty_used || 0));
            },

            get blockingMessage() {
                if (this.hasQtyBelowUsed) return 'Ada qty komponen yang dikurangi sampai di bawah jumlah yang sudah terjual/terpakai.';
                if (this.components.some(c => !c.product_id)) return 'Masih ada baris komponen baru yang produknya belum dipilih.';
                return null;
            },

            onSubmit(e) {
                if (this.blockingMessage) {
                    e.preventDefault();
                }
            },

            formatRupiah(value) {
                const n = parseFloat(value);
                if (!n && n !== 0) return '';
                return Math.round(n).toLocaleString('id-ID');
            },

            parseRupiah(value) {
                const digits = String(value).replace(/\D/g, '');
                return digits ? parseInt(digits) : '';
            },

            initComponentSelect(el, row) {
                const self = this;
                $(el).select2({
                    placeholder: '— Pilih komponen —',
                    width: '100%',
                    dropdownParent: $('body'),
                    data: [
                        { id: '', text: '— Pilih komponen —' },
                        ...this.products.map(p => ({ id: p.id, text: p.name + ' (' + p.unit + ')' })),
                    ],
                }).on('change', function () {
                    const newVal = $(this).val();

                    if (newVal && self.existing.some(r => r.product_id == newVal)) {
                        alert('Komponen ini sudah tercatat di daftar "Komponen yang Sudah Tercatat" — ubah qty-nya di sana saja.');
                        $(this).val(row.product_id || null).trigger('change.select2');
                        return;
                    }

                    if (newVal && self.components.some(c => c.key !== row.key && c.product_id == newVal)) {
                        alert('Komponen ini sudah dipilih di baris lain.');
                        $(this).val(row.product_id || null).trigger('change.select2');
                        return;
                    }

                    row.product_id = newVal;
                });

                if (row.product_id) {
                    this.$nextTick(() => $(el).val(String(row.product_id)).trigger('change.select2'));
                }
            },
        };
    }
</script>
@endpush