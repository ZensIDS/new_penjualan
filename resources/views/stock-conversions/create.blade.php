@extends('layouts.app')

@section('page-title', 'Bongkar Unit')

@section('content')
<div
    x-data="bongkarForm(
        {{ Illuminate\Support\Js::from($products) }},
        {{ Illuminate\Support\Js::from($lastRecipes) }},
        {{ Illuminate\Support\Js::from(old('components', [['product_id' => '', 'qty' => 1]])) }}
    )"
    x-cloak
>
    <div class="mb-6">
        <a href="{{ route('stock-conversions.index') }}" class="text-sm text-ink/50 hover:text-ink inline-flex items-center gap-1 mb-2">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Kembali ke daftar bongkar
        </a>
        <h2 class="text-2xl font-display font-semibold tracking-tight">Bongkar Unit Menjadi Komponen</h2>
        <p class="text-sm text-ink/50 mt-1">
            Unit utuh dipotong dari stok secara FIFO, lalu nilai HPP-nya dibagi ke komponen. Tidak ada uang keluar/masuk —
            laba baru dihitung saat komponen terjual.
        </p>
        <p class="text-xs text-ink/40 mt-1">Kolom bertanda <span class="text-red-600 font-medium">*</span> wajib diisi.</p>
    </div>

    {{-- Cara pembagian HPP: sekarang otomatis, tidak ada lagi yang perlu dipilih --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6 mb-6">
            <h3 class="font-display font-semibold mb-1">Pembagian HPP ke Komponen</h3>
            <p class="text-xs text-ink/50 leading-relaxed">
                HPP unit utuh dibagi otomatis <span class="font-medium text-ink/70">proporsional terhadap harga jual tiap komponen</span> —
                komponen yang lebih mahal menyerap HPP lebih besar. Harga jual yang dipakai adalah harga jual
                <span class="font-medium text-ink/70">riil terakhir dari Sales Order</span>, jadi kamu tidak perlu menebak apa pun di sini.
                Setiap kali komponen terjual dengan harga baru hasil negosiasi, HPP sisa stok yang belum terjual
                otomatis ikut disesuaikan di balik layar. Total HPP komponen selalu sama dengan HPP unit utuh —
                pembongkaran tidak pernah melahirkan laba/rugi.
            </p>
        </div>

    <form method="POST" action="{{ route('stock-conversions.store') }}" @submit="onSubmit">
        @csrf

        {{-- Unit yang dibongkar --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6 mb-6">
            <h3 class="font-display font-semibold mb-4">Unit yang Dibongkar</h3>

            <div class="grid grid-cols-1 @4xl:grid-cols-3 gap-4">
                <div class="@4xl:col-span-1">
                    <label class="block text-sm font-medium mb-1.5">Produk Utuh <span class="text-red-600">*</span></label>
                    <select x-ref="sourceSelect" name="source_product_id" x-init="initSourceSelect($el)"></select>
                    @error('source_product_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Qty Dibongkar <span class="text-red-600">*</span></label>
                    <input type="number" min="1" name="source_qty" x-model.number="sourceQty"
                           class="w-full rounded-xl border px-3.5 py-2.5 text-sm tnum focus:outline-none focus:ring-4 transition-shadow"
                           :class="exceedsStock
                                ? 'border-red-400 focus:border-red-500 focus:ring-red-500/15'
                                : 'border-ink/12 focus:border-amber-500 focus:ring-amber-500/15'">
                    <p class="text-xs mt-1" x-show="sourceProductId"
                       :class="exceedsStock ? 'text-red-600 font-medium' : 'text-ink/40'">
                        Stok tersedia: <span x-text="sourceStockLabel"></span>
                        <span x-show="exceedsStock"> — melebihi stok!</span>
                    </p>
                    @error('source_qty')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Tanggal Bongkar <span class="text-red-600">*</span></label>
                    <input type="date" name="conversion_date" value="{{ old('conversion_date', now()->toDateString()) }}"
                           class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                    @error('conversion_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium mb-1.5">Catatan</label>
                <textarea name="note" rows="2" placeholder="Opsional — mis. kondisi barang, siapa yang membongkar"
                          class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">{{ old('note') }}</textarea>
            </div>

            {{-- Perkiraan nilai yang akan dibagi. Angka final dihitung ulang server-side
                 dari batch FIFO yang benar-benar kepakai saat disimpan. --}}
            <div class="mt-5 rounded-xl bg-ink/[0.03] px-4 py-3.5 flex items-center justify-between" x-show="sourceProductId">
                <div>
                    <p class="text-sm font-medium text-ink/70">Perkiraan HPP yang akan dibagi</p>
                    <p class="text-xs text-ink/40 mt-0.5">Taksiran dari batch FIFO tertua. Nilai final dihitung ulang saat disimpan.</p>
                </div>
                <p class="font-display font-semibold text-lg tnum" x-text="'Rp ' + formatRupiah(estimatedHpp)"></p>
            </div>

            <div class="mt-3" x-show="hasRecipe" x-cloak>
                <button type="button" @click="applyRecipe()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:text-amber-800">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/></svg>
                    Pakai komposisi bongkar terakhir untuk produk ini
                </button>
            </div>
        </div>

        {{-- Komponen hasil --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-ink/10 flex items-center gap-2">
                <h3 class="font-display font-semibold">Komponen Hasil Bongkar <span class="text-red-600">*</span></h3>
                <button type="button" @click="addComponent()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:text-amber-800">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Baris
                </button>
            </div>

            <div class="px-6 py-3 bg-ink/[0.02] text-xs text-ink/50">
                Qty diisi <span class="font-medium text-ink/70">total</span> untuk seluruh unit yang dibongkar.
                Contoh: bongkar 2 unit, tiap unit berisi 13 cell &rarr; qty cell = 26.
            </div>

            <div class="divide-y divide-ink/[0.06]">
                {{-- Label ditulis di SETIAP baris (bukan cuma baris pertama) supaya baris
                     yang baru ditambah lewat "Tambah Baris" tidak tampil tanpa keterangan. --}}
                <template x-for="(row, index) in components" :key="row.key">
                    <div class="p-5 grid grid-cols-1 @4xl:grid-cols-12 gap-3 sm:items-start">
                        <div class="@4xl:col-span-5">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">Produk Komponen <span class="text-red-600">*</span></label>
                            <select :name="'components['+index+'][product_id]'" x-init="initComponentSelect($el, row)"></select>
                        </div>

                        <div class="@4xl:col-span-2">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">Qty Total <span class="text-red-600">*</span></label>
                            <input type="number" min="1" :name="'components['+index+'][qty]'" x-model.number="row.qty"
                                   class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                        </div>

                        {{-- <div class="@4xl:col-span-4">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5">Perkiraan HPP Komponen</label>
                            <p class="text-sm font-medium tnum py-2.5" x-text="'Rp ' + formatRupiah(shareOf(index))"></p>
                            <p class="text-[11px] text-ink/40 -mt-1.5">
                                Rp <span x-text="formatRupiah(perUnitOf(index))"></span> / unit
                                <span x-show="refPriceOf(index) > 0">
                                    &middot; acuan jual Rp <span x-text="formatRupiah(refPriceOf(index))"></span>
                                </span>
                                <span x-show="refPriceOf(index) <= 0" class="text-amber-700">
                                    &middot; belum pernah terjual, menyusul otomatis
                                </span>
                            </p>
                        </div> --}}

                        <div class="@4xl:col-span-1 flex sm:justify-end sm:pt-6">
                            <button type="button" @click="removeComponent(row.key)" x-show="components.length > 1"
                                    class="text-red-600/70 hover:text-red-700 p-1.5" title="Hapus baris">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- <div class="px-6 py-4 bg-ink/[0.02] space-y-1.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink/60">HPP unit yang dibagi</span>
                    <span class="font-medium tnum" x-text="'Rp ' + formatRupiah(estimatedHpp)"></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink/60">Perkiraan total nilai jual komponen</span>
                    <span class="font-medium tnum" x-text="'Rp ' + formatRupiah(estimatedSalesValue)"></span>
                </div>
                <div class="flex items-center justify-between text-sm pt-1 border-t border-ink/[0.06]">
                    <span class="font-medium">Perkiraan untung kalau dijual ecer</span>
                    <span class="font-semibold tnum" :class="estimatedMargin >= 0 ? 'text-emerald-700' : 'text-red-600'"
                          x-text="(estimatedMargin >= 0 ? '+ Rp ' : '- Rp ') + formatRupiah(Math.abs(estimatedMargin))"></span>
                </div>
                <p class="text-[11px] text-ink/40 pt-1" x-show="estimatedMargin < 0" x-cloak>
                    Dengan harga jual yang tercatat sekarang, total ecernya masih di bawah harga unit utuhnya.
                </p>
                <p class="text-[11px] text-ink/40 pt-1">
                    Perkiraan ini memakai harga jual terakhir tiap komponen. HPP per komponen dihitung server saat
                    disimpan dan menyesuaikan sendiri tiap ada penjualan dengan harga baru.
                </p>
            </div> --}}
        </div>

        {{-- Mode bertahap: belum semua komponen diketahui hari ini --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6 mb-6">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" x-model="isDraft" name="status" value="draft"
                       class="mt-0.5 h-4 w-4 rounded border-ink/30 text-amber-600 focus:ring-amber-500/30">
                <span>
                    <span class="text-sm font-semibold block">Belum semua komponen diketahui — lanjutkan nanti</span>
                    <span class="text-xs text-ink/50 leading-relaxed block mt-0.5">
                        Centang kalau hari ini kamu cuma mau catat sebagian komponen (mis. cell saja). Besok tinggal buka
                        transaksi ini lagi lewat <span class="font-medium">"Lanjutkan Bongkar"</span> untuk menambahkan
                        komponen lain (mis. BMS &amp; casing). Komponen yang sudah diisi langsung masuk stok dan bisa
                        dijual sekarang juga.
                    </span>
                </span>
            </label>

            {{-- <div class="mt-4 rounded-xl bg-amber-50/70 border border-amber-500/20 px-4 py-3.5" x-show="isDraft" x-cloak>
                <p class="text-xs text-amber-800 leading-relaxed">
                    Tidak ada yang perlu kamu hitung. Seluruh HPP unit dibagi ke komponen yang kamu isi sekarang, dan
                    kalau besok ketemu komponen lain, HPP-nya otomatis dibagi ulang ke semua komponen.
                </p>
            </div> --}}

            @error('status')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>
        @error('components')<p class="text-xs text-red-600 -mt-4 mb-6">{{ $message }}</p>@enderror
        @error('error')<p class="text-xs text-red-600 -mt-4 mb-6">{{ $message }}</p>@enderror

        <p class="text-xs text-red-600 mb-4" x-show="blockingMessage" x-cloak x-text="blockingMessage"></p>

        <div class="flex justify-end gap-3">
            <a href="{{ route('stock-conversions.index') }}"
               class="text-sm font-medium px-5 py-2.5 rounded-xl border border-ink/12 hover:bg-ink/[0.03] transition-colors">Batal</a>
            <button type="submit"
                    class="text-sm font-semibold px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-ink shadow-glow hover:brightness-105 active:scale-[0.98] transition-all"
                    x-text="isDraft ? 'Simpan sebagai Draft' : 'Simpan Pembongkaran'">
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function bongkarForm(products, lastRecipes, initialComponents) {
        return {
            products,
            lastRecipes,
            sourceProductId: {{ Illuminate\Support\Js::from(old('source_product_id', '')) }} || '',
            sourceQty: {{ (int) old('source_qty', 1) }} || 1,
            isDraft: {{ old('status') === 'draft' ? 'true' : 'false' }},

            components: initialComponents.map(c => ({
                key: Math.random().toString(36).slice(2),
                product_id: c.product_id || '',
                qty: c.qty || 1,
            })),

            // ---------- Sumber ----------
            get sourceProduct() {
                return this.products.find(p => p.id == this.sourceProductId) || null;
            },

            get sourceStockLabel() {
                const p = this.sourceProduct;
                return p ? p.qty_on_hand + ' ' + p.unit : '—';
            },

            get exceedsStock() {
                const p = this.sourceProduct;
                if (!p) return false;
                return (parseInt(this.sourceQty) || 0) > p.qty_on_hand;
            },

            // Taksiran HPP = qty x harga beli batch tertua yang masih ada stok.
            get estimatedHpp() {
                const p = this.sourceProduct;
                if (!p) return 0;
                return (parseInt(this.sourceQty) || 0) * (parseFloat(p.next_buy_price) || 0);
            },

            get hasRecipe() {
                return !!(this.sourceProductId && this.lastRecipes[this.sourceProductId]);
            },

            applyRecipe() {
                const recipe = this.lastRecipes[this.sourceProductId];
                if (!recipe) return;

                this.components = recipe.components.map(c => ({
                    key: Math.random().toString(36).slice(2),
                    product_id: c.product_id,
                    // Qty resep disimpan untuk source_qty saat itu, jadi diskalakan
                    // ke qty yang sedang dibongkar sekarang.
                    qty: Math.max(1, Math.round((c.qty / (recipe.source_qty || 1)) * (parseInt(this.sourceQty) || 1))),
                }));
            },

            // ---------- Komponen ----------
            addComponent() {
                this.components.push({
                    key: Math.random().toString(36).slice(2),
                    product_id: '',
                    qty: 1,
                });
            },

            removeComponent(key) {
                if (this.components.length === 1) return;
                this.components = this.components.filter(c => c.key !== key);
            },

            // Harga jual acuan per baris = harga jual riil terakhir produk itu.
            refPriceOf(index) {
                const row = this.components[index];
                if (!row || !row.product_id) return 0;
                const p = this.products.find(p => p.id == row.product_id);
                return p ? (parseFloat(p.ref_sell_price) || 0) : 0;
            },

            // Pratinjau pembagian — algoritmanya sengaja disamakan dengan
            // StockConversionService (komponen yang belum pernah terjual memakai
            // rata-rata yang diketahui; kalau tidak ada sama sekali, bagi rata).
            get shares() {
                const pool = this.estimatedHpp;

                const known = this.components
                    .map((_, i) => this.refPriceOf(i))
                    .filter(v => v > 0);
                const fallback = known.length ? known.reduce((a, b) => a + b, 0) / known.length : 1;

                const weights = this.components.map((c, i) => {
                    const price = this.refPriceOf(i) || fallback;
                    return price * (parseInt(c.qty) || 0);
                });

                const totalWeight = weights.reduce((a, b) => a + b, 0);
                if (totalWeight <= 0) return this.components.map(() => 0);

                const out = [];
                let running = 0;

                weights.forEach((w, i) => {
                    if (i === weights.length - 1) {
                        out[i] = Math.round((pool - running) * 100) / 100;
                        return;
                    }
                    out[i] = Math.round(pool * (w / totalWeight) * 100) / 100;
                    running += out[i];
                });

                return out;
            },

            shareOf(index) {
                return this.shares[index] || 0;
            },

            perUnitOf(index) {
                const qty = parseInt(this.components[index]?.qty) || 0;
                if (qty <= 0) return 0;
                return Math.round((this.shareOf(index) / qty) * 100) / 100;
            },

            get totalShared() {
                return this.shares.reduce((a, b) => a + b, 0);
            },

            // Yang paling dipedulikan user: kalau unit 5 juta ini dipecah,
            // total ecerannya laku berapa? Pakai harga jual terakhir tiap komponen.
            get estimatedSalesValue() {
                return this.components.reduce((sum, c, i) => {
                    return sum + this.refPriceOf(i) * (parseInt(c.qty) || 0);
                }, 0);
            },

            get estimatedMargin() {
                return this.estimatedSalesValue - this.estimatedHpp;
            },

            get blockingMessage() {
                if (this.exceedsStock) return 'Qty yang dibongkar melebihi stok yang tersedia.';
                if (this.components.some(c => !c.product_id)) return 'Masih ada baris komponen yang produknya belum dipilih.';
                return null;
            },

            onSubmit(e) {
                if (this.blockingMessage) {
                    e.preventDefault();
                }
            },

            // ---------- Helper tampilan ----------
            formatRupiah(value) {
                const n = parseFloat(value);
                if (!n && n !== 0) return '';
                return Math.round(n).toLocaleString('id-ID');
            },

            parseRupiah(value) {
                const digits = String(value).replace(/\D/g, '');
                return digits ? parseInt(digits) : '';
            },

            // ---------- Select2 ----------
            initSourceSelect(el) {
                const self = this;
                $(el).select2({
                    placeholder: '— Pilih produk utuh —',
                    width: '100%',
                    dropdownParent: $('body'),
                    data: [
                        { id: '', text: '— Pilih produk utuh —' },
                        ...this.products.map(p => ({
                            id: p.id,
                            text: p.name + ' (' + p.unit + ') — stok ' + p.qty_on_hand,
                            disabled: p.qty_on_hand <= 0,
                        })),
                    ],
                }).on('change', function () {
                    self.sourceProductId = $(this).val();
                });

                if (this.sourceProductId) {
                    this.$nextTick(() => $(el).val(String(this.sourceProductId)).trigger('change.select2'));
                }
            },

            initComponentSelect(el, row) {
                const self = this;
                $(el).select2({
                    placeholder: '— Pilih komponen —',
                    width: '100%',
                    dropdownParent: $('body'),
                    // Komponen boleh berstok 0 (justru biasanya begitu sebelum dibongkar),
                    // jadi tidak ada opsi yang di-disable karena stok.
                    data: [
                        { id: '', text: '— Pilih komponen —' },
                        ...this.products.map(p => ({ id: p.id, text: p.name + ' (' + p.unit + ')' })),
                    ],
                }).on('change', function () {
                    const newVal = $(this).val();

                    if (newVal && newVal == self.sourceProductId) {
                        alert('Komponen tidak boleh sama dengan produk yang dibongkar. Buat produk komponen tersendiri di menu Produk.');
                        $(this).val(row.product_id || null).trigger('change.select2');
                        return;
                    }

                    if (newVal && self.components.some(c => c.key !== row.key && c.product_id == newVal)) {
                        alert('Komponen ini sudah dipilih di baris lain. Ubah qty di baris tersebut saja.');
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