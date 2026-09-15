@extends('layouts.app')

@section('page-title', 'Bongkar Unit')

@section('content')
<div
    x-data="bongkarForm(
        {{ Illuminate\Support\Js::from($products) }},
        {{ Illuminate\Support\Js::from($lastRecipes) }},
        {{ Illuminate\Support\Js::from(old('components', [['product_id' => '', 'qty' => 1, 'allocation_percent' => '', 'estimated_sell_price' => '', 'hpp_total' => '']])) }}
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

        {{-- Metode pembagian HPP --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6 mb-6">
            <h3 class="font-display font-semibold mb-1">Cara Membagi HPP ke Komponen <span class="text-red-600">*</span></h3>
            <p class="text-xs text-ink/50 mb-4">Berapa pun metodenya, total HPP komponen selalu sama dengan HPP unit utuh — tidak ada laba/rugi yang lahir dari pembongkaran.</p>

            <input type="hidden" name="allocation_method" :value="method">

            <div class="grid grid-cols-1 @4xl:grid-cols-3 gap-3">
                <template x-for="opt in methodOptions" :key="opt.value">
                    <button type="button" @click="method = opt.value"
                            class="text-left rounded-xl border px-4 py-3.5 transition-colors"
                            :class="method === opt.value
                                ? 'border-amber-500 bg-amber-50/70 ring-4 ring-amber-500/10'
                                : 'border-ink/12 hover:bg-ink/[0.02]'">
                        <p class="text-sm font-semibold" x-text="opt.label"></p>
                        <p class="text-xs text-ink/50 mt-1 leading-relaxed" x-text="opt.hint"></p>
                    </button>
                </template>
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
                <template x-for="(row, index) in components" :key="row.key">
                    <div class="p-5 grid grid-cols-1 @4xl:grid-cols-12 gap-3 sm:items-start">
                        <div class="@4xl:col-span-4">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0">Produk Komponen <span class="text-red-600">*</span></label>
                            <select :name="'components['+index+'][product_id]'" x-init="initComponentSelect($el, row)"></select>
                        </div>

                        <div class="@4xl:col-span-2">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0">Qty Total <span class="text-red-600">*</span></label>
                            <input type="number" min="1" :name="'components['+index+'][qty]'" x-model.number="row.qty"
                                   class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                        </div>

                        {{-- Kolom nilai berganti sesuai metode yang dipilih --}}
                        <div class="@4xl:col-span-3">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0" x-text="valueLabel"></label>

                            <div x-show="method === 'percent'">
                                <div class="relative">
                                    <input type="number" step="0.01" min="0" max="100"
                                           :name="'components['+index+'][allocation_percent]'"
                                           x-model.number="row.allocation_percent"
                                           class="w-full rounded-xl border border-ink/12 pr-9 pl-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm text-ink/40">%</span>
                                </div>
                            </div>

                            <div x-show="method === 'market'">
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-ink/40">Rp</span>
                                    <input type="text" inputmode="numeric"
                                           :value="formatRupiah(row.estimated_sell_price)"
                                           @input="row.estimated_sell_price = parseRupiah($event.target.value); $event.target.value = formatRupiah(row.estimated_sell_price)"
                                           class="w-full rounded-xl border border-ink/12 pl-9 pr-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                    <input type="hidden" :name="'components['+index+'][estimated_sell_price]'" :value="row.estimated_sell_price">
                                </div>
                                <p class="text-[11px] text-ink/40 mt-1" x-show="index === 0">Harga jual per unit (perkiraan), bukan harga beli.</p>
                            </div>

                            <div x-show="method === 'manual'">
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-ink/40">Rp</span>
                                    <input type="text" inputmode="numeric"
                                           :value="formatRupiah(row.hpp_total)"
                                           @input="row.hpp_total = parseRupiah($event.target.value); $event.target.value = formatRupiah(row.hpp_total)"
                                           class="w-full rounded-xl border border-ink/12 pl-9 pr-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                    <input type="hidden" :name="'components['+index+'][hpp_total]'" :value="row.hpp_total">
                                </div>
                            </div>
                        </div>

                        <div class="@4xl:col-span-2">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0">HPP Komponen</label>
                            <p class="text-sm font-medium tnum py-2.5" x-text="'Rp ' + formatRupiah(shareOf(index))"></p>
                            <p class="text-[11px] text-ink/40 -mt-1.5">
                                Rp <span x-text="formatRupiah(perUnitOf(index))"></span> / unit
                            </p>
                        </div>

                        <div class="@4xl:col-span-1 flex sm:justify-end sm:pt-6">
                            <button type="button" @click="removeComponent(row.key)" x-show="components.length > 1"
                                    class="text-red-600/70 hover:text-red-700 p-1.5" title="Hapus baris">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-6 py-4 bg-ink/[0.02] space-y-1.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink/60">Total HPP dibagi</span>
                    <span class="font-medium tnum" x-text="'Rp ' + formatRupiah(totalShared)"></span>
                </div>
                <div class="flex items-center justify-between text-sm" x-show="method === 'percent'">
                    <span class="text-ink/60">Total persentase</span>
                    <span class="font-medium tnum" :class="percentValid ? 'text-emerald-700' : 'text-red-600'"
                          x-text="totalPercent.toFixed(2).replace('.', ',') + '%'"></span>
                </div>
                <div class="flex items-center justify-between text-sm" x-show="method === 'manual'">
                    <span class="text-ink/60">Selisih terhadap HPP unit</span>
                    <span class="font-medium tnum" :class="manualValid ? 'text-emerald-700' : 'text-red-600'"
                          x-text="'Rp ' + formatRupiah(estimatedHpp - totalShared)"></span>
                </div>
            </div>
        </div>
        @error('components')<p class="text-xs text-red-600 -mt-4 mb-6">{{ $message }}</p>@enderror

        <p class="text-xs text-red-600 mb-4" x-show="blockingMessage" x-cloak x-text="blockingMessage"></p>

        <div class="flex justify-end gap-3">
            <a href="{{ route('stock-conversions.index') }}"
               class="text-sm font-medium px-5 py-2.5 rounded-xl border border-ink/12 hover:bg-ink/[0.03] transition-colors">Batal</a>
            <button type="submit"
                    class="text-sm font-semibold px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-ink shadow-glow hover:brightness-105 active:scale-[0.98] transition-all">
                Simpan Pembongkaran
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
            method: '{{ old('allocation_method', 'market') }}',

            methodOptions: [
                {
                    value: 'market',
                    label: 'Proporsi Harga Jual',
                    hint: 'Paling adil & paling lazim. HPP dibagi mengikuti perbandingan nilai jual tiap komponen — komponen mahal menyerap HPP lebih besar.',
                },
                {
                    value: 'percent',
                    label: 'Persentase',
                    hint: 'Kamu tentukan sendiri porsi tiap komponen dalam persen. Total harus 100%.',
                },
                {
                    value: 'manual',
                    label: 'Nominal Manual',
                    hint: 'Isi langsung rupiah HPP tiap komponen. Totalnya harus sama persis dengan HPP unit yang dibongkar.',
                },
            ],

            components: initialComponents.map(c => ({
                key: Math.random().toString(36).slice(2),
                product_id: c.product_id || '',
                qty: c.qty || 1,
                allocation_percent: c.allocation_percent || '',
                estimated_sell_price: c.estimated_sell_price || '',
                hpp_total: c.hpp_total || '',
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
            // Kalau qty yang dibongkar melintasi beberapa batch dengan harga berbeda,
            // angka riilnya bisa sedikit berbeda — makanya server menghitung ulang.
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

                this.method = recipe.allocation_method;
                this.components = recipe.components.map(c => ({
                    key: Math.random().toString(36).slice(2),
                    product_id: c.product_id,
                    // Qty resep disimpan untuk source_qty saat itu, jadi diskalakan
                    // ke qty yang sedang dibongkar sekarang.
                    qty: Math.max(1, Math.round((c.qty / (recipe.source_qty || 1)) * (parseInt(this.sourceQty) || 1))),
                    allocation_percent: c.allocation_percent || '',
                    estimated_sell_price: c.estimated_sell_price || '',
                    hpp_total: '',
                }));
            },

            // ---------- Komponen ----------
            addComponent() {
                this.components.push({
                    key: Math.random().toString(36).slice(2),
                    product_id: '', qty: 1,
                    allocation_percent: '', estimated_sell_price: '', hpp_total: '',
                });
            },

            removeComponent(key) {
                if (this.components.length === 1) return;
                this.components = this.components.filter(c => c.key !== key);
            },

            get valueLabel() {
                return {
                    percent: 'Porsi (%)',
                    market: 'Estimasi Harga Jual / Unit',
                    manual: 'HPP Komponen (Rp)',
                }[this.method];
            },

            // Pratinjau pembagian — algoritmanya sengaja disamakan dengan
            // StockConversionService::splitHpp() (baris terakhir menerima sisa)
            // supaya angka di layar identik dengan yang nanti tersimpan.
            get shares() {
                const total = this.estimatedHpp;

                if (this.method === 'manual') {
                    return this.components.map(c => parseFloat(c.hpp_total) || 0);
                }

                const weights = this.components.map(c => this.method === 'percent'
                    ? (parseFloat(c.allocation_percent) || 0)
                    : (parseFloat(c.estimated_sell_price) || 0) * (parseInt(c.qty) || 0));

                const totalWeight = weights.reduce((a, b) => a + b, 0);
                if (totalWeight <= 0) return this.components.map(() => 0);

                const out = [];
                let running = 0;

                weights.forEach((w, i) => {
                    if (i === weights.length - 1) {
                        out[i] = Math.round((total - running) * 100) / 100;
                        return;
                    }
                    out[i] = Math.round(total * (w / totalWeight) * 100) / 100;
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

            get totalPercent() {
                return this.components.reduce((sum, c) => sum + (parseFloat(c.allocation_percent) || 0), 0);
            },

            get percentValid() {
                return Math.abs(this.totalPercent - 100) <= 0.01;
            },

            get manualValid() {
                return Math.abs(this.estimatedHpp - this.totalShared) <= 1;
            },

            get blockingMessage() {
                if (this.exceedsStock) return 'Qty yang dibongkar melebihi stok yang tersedia.';
                if (this.method === 'percent' && !this.percentValid) return 'Total persentase komponen harus 100%.';
                if (this.components.some(c => !c.product_id)) return 'Masih ada baris komponen yang produknya belum dipilih.';
                return null;
            },

            onSubmit(e) {
                if (this.blockingMessage) {
                    e.preventDefault();
                    return;
                }

                // Metode manual divalidasi terhadap HPP RIIL di server; di sini
                // cuma diingatkan karena angka acuannya masih taksiran.
                if (this.method === 'manual' && !this.manualValid) {
                    const ok = confirm(
                        'Total HPP komponen belum sama dengan perkiraan HPP unit yang dibongkar. ' +
                        'Kalau meleset, sistem akan menolak menyimpan. Lanjutkan?'
                    );
                    if (!ok) e.preventDefault();
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