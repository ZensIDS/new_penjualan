@extends('layouts.app')

@section('page-title', 'Edit Transaksi Penjualan')

@section('content')
<div
    x-data="soEditForm(
        {{ Illuminate\Support\Js::from($customers) }},
        {{ Illuminate\Support\Js::from($products) }},
        {{ Illuminate\Support\Js::from(old('items', $salesOrder->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'qty'        => $item->qty,
            'sell_price' => (int) round($item->sell_price),
        ])->values())) }}
    )"
    x-cloak
>
    <div class="mb-6">
        <a href="{{ route('sales-orders.show', $salesOrder) }}" class="text-sm text-ink/50 hover:text-ink inline-flex items-center gap-1 mb-2">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Kembali ke detail transaksi
        </a>
        <h2 class="text-2xl font-display font-semibold tracking-tight tnum">Edit {{ $salesOrder->so_number }}</h2>
        <p class="text-sm text-ink/50 mt-1">
            Transaksi ini belum ada pembayaran, jadi masih bisa diedit bebas. Alokasi stok FIFO
            lama akan dikembalikan dulu, lalu dialokasikan ulang sesuai perubahan item.
        </p>
    </div>

    <form method="POST" action="{{ route('sales-orders.update', $salesOrder) }}" @submit="onSubmit">
        @csrf
        @method('PUT')

        {{-- Info utama --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6 mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Customer</label>
                    <div class="relative">
                        <select x-ref="customerSelect" name="customer_id" x-init="initCustomerSelect($el)">
                            <option value="">— Pilih customer —</option>
                            <template x-for="c in customers" :key="c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                    </div>
                    @error('customer_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Tanggal Transaksi</label>
                    <input type="date" name="so_date" value="{{ old('so_date', $salesOrder->so_date->toDateString()) }}"
                           class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                    @error('so_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Catatan</label>
                    <textarea name="note" rows="2" placeholder="Opsional"
                              class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">{{ old('note', $salesOrder->note) }}</textarea>
                    @error('note')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1.5">Asal Penjualan</label>
                    <select name="source"
                            class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm bg-white focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                        @foreach (\App\Models\SalesOrder::SOURCES as $value => $label)
                            <option value="{{ $value }}" @selected(old('source', $salesOrder->source) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('source')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Item barang --}}
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-ink/10 flex items-center justify-between">
                <h3 class="font-display font-semibold">Item Barang</h3>
                <button type="button" @click="addItem()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:text-amber-800">
                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Baris
                </button>
            </div>

            <div class="divide-y divide-ink/[0.06]">
                <template x-for="(item, index) in items" :key="item.key">
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-12 gap-3 sm:items-start">
                        <div class="sm:col-span-5">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0">Produk</label>
                            <div class="relative">
                                <select :name="'items['+index+'][product_id]'" x-init="initProductSelect($el, item)"></select>
                            </div>
                            <p class="text-xs mt-1" x-show="item.product_id"
                               :class="exceedsStock(item) ? 'text-red-600 font-medium' : 'text-ink/40'">
                                Stok tersedia: <span x-text="currentStock(item.product_id)"></span>
                                <span x-show="exceedsStock(item)"> — melebihi stok!</span>
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0">Qty</label>
                            <input type="number" min="1" :name="'items['+index+'][qty]'" x-model.number="item.qty"
                                   class="w-full rounded-xl border px-3.5 py-2.5 text-sm tnum focus:outline-none focus:ring-4 transition-shadow"
                                   :class="exceedsStock(item)
                                        ? 'border-red-400 focus:border-red-500 focus:ring-red-500/15'
                                        : 'border-ink/12 focus:border-amber-500 focus:ring-amber-500/15'">
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0">Harga Jual / Unit</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-ink/40">Rp</span>
                                <input type="text" inputmode="numeric"
                                       :value="formatRupiah(item.sell_price)"
                                       @input="item.sell_price = parseRupiah($event.target.value); $event.target.value = formatRupiah(item.sell_price)"
                                       class="w-full rounded-xl border pl-9 pr-3.5 py-2.5 text-sm tnum focus:outline-none focus:ring-4 transition-shadow"
                                       :class="belowCost(item)
                                            ? 'border-amber-400 focus:border-amber-500 focus:ring-amber-500/15'
                                            : 'border-ink/12 focus:border-amber-500 focus:ring-amber-500/15'">
                                <input type="hidden" :name="'items['+index+'][sell_price]'" :value="item.sell_price">
                            </div>
                            <p class="text-xs text-amber-700 font-medium mt-1" x-show="belowCost(item)" x-cloak>
                                Di bawah harga beli (Rp <span x-text="formatRupiah(buyPriceFor(item.product_id))"></span>)
                            </p>
                        </div>

                        <div class="sm:col-span-1">
                            <label class="block text-xs font-medium text-ink/50 mb-1.5" x-show="index === 0">Subtotal</label>
                            <p class="text-sm font-medium tnum py-2.5" x-text="formatRupiah((item.qty || 0) * (item.sell_price || 0))"></p>
                        </div>

                        <div class="sm:col-span-1 flex sm:justify-end sm:pt-6">
                            <button type="button" @click="removeItem(item.key)" x-show="items.length > 1"
                                    class="text-red-600/70 hover:text-red-700 p-1.5" title="Hapus baris">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-6 py-4 bg-ink/[0.02] flex items-center justify-between">
                <span class="text-sm font-medium text-ink/60">Total Transaksi</span>
                <span class="font-display font-semibold text-lg tnum" x-text="'Rp ' + formatRupiah(total)"></span>
            </div>
        </div>
        @error('items')<p class="text-xs text-red-600 -mt-4 mb-6">{{ $message }}</p>@enderror
        @error('error')<p class="text-xs text-red-600 -mt-4 mb-6">{{ $message }}</p>@enderror
        <p class="text-xs text-red-600 -mt-4 mb-6" x-show="hasStockError" x-cloak>
            Ada baris dengan qty melebihi stok tersedia — perbaiki dulu sebelum menyimpan.
        </p>

        <div class="flex justify-end gap-3">
            <a href="{{ route('sales-orders.show', $salesOrder) }}"
               class="text-sm font-medium px-5 py-2.5 rounded-xl border border-ink/12 hover:bg-ink/[0.03] transition-colors">Batal</a>
            <button type="submit"
                    class="text-sm font-semibold px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-ink shadow-glow hover:brightness-105 active:scale-[0.98] transition-all">
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function soEditForm(customers, products, initialItems) {
        return {
            customers,
            products,
            items: initialItems.map(i => ({
                key: Math.random().toString(36).slice(2),
                product_id: i.product_id || '',
                qty: i.qty || 1,
                sell_price: i.sell_price || '',
            })),

            addItem() {
                this.items.push({ key: Math.random().toString(36).slice(2), product_id: '', qty: 1, sell_price: '' });
            },

            removeItem(key) {
                if (this.items.length === 1) return;
                this.items = this.items.filter(i => i.key !== key);
            },

            currentStock(productId) {
                const p = this.products.find(p => p.id == productId);
                return p ? p.qty_on_hand + ' ' + p.unit : '—';
            },

            exceedsStock(item) {
                if (!item.product_id) return false;
                const p = this.products.find(p => p.id == item.product_id);
                if (!p) return false;
                return (parseInt(item.qty) || 0) > p.qty_on_hand;
            },

            get hasStockError() {
                return this.items.some(i => this.exceedsStock(i));
            },

            buyPriceFor(productId) {
                const p = this.products.find(p => p.id == productId);
                return p && p.next_buy_price !== null && p.next_buy_price !== undefined
                    ? parseFloat(p.next_buy_price)
                    : null;
            },

            belowCost(item) {
                if (!item.product_id || !item.sell_price) return false;
                const buyPrice = this.buyPriceFor(item.product_id);
                if (buyPrice === null) return false;
                return parseFloat(item.sell_price) < buyPrice;
            },

            get total() {
                return this.items.reduce((sum, i) => sum + ((parseInt(i.qty) || 0) * (parseFloat(i.sell_price) || 0)), 0);
            },

            onSubmit(e) {
                if (this.hasStockError) {
                    e.preventDefault();
                    return;
                }

                const belowCostItems = this.items.filter(i => this.belowCost(i));
                if (belowCostItems.length > 0) {
                    const names = belowCostItems
                        .map(i => (this.products.find(p => p.id == i.product_id) || {}).name)
                        .filter(Boolean)
                        .join(', ');
                    const ok = confirm(
                        'Harga jual untuk ' + names + ' ditulis di bawah harga beli, transaksi ini akan rugi. ' +
                        'Lanjutkan simpan transaksi ini?'
                    );
                    if (!ok) {
                        e.preventDefault();
                        return;
                    }
                }
            },

            initCustomerSelect(el) {
                $(el).select2({
                    placeholder: '— Pilih customer —',
                    width: '100%',
                    dropdownParent: $('body'),
                });
                const old = {{ Illuminate\Support\Js::from(old('customer_id', $salesOrder->customer_id)) }};
                if (old) this.$nextTick(() => $(el).val(String(old)).trigger('change.select2'));
            },

            initProductSelect(el, item) {
                const self = this;
                $(el).select2({
                    placeholder: '— Pilih produk —',
                    width: '100%',
                    dropdownParent: $('body'),
                    // Opsi kosong WAJIB ada duluan di data array, sama seperti di form
                    // create — supaya baris yang belum diisi tidak otomatis kebawa
                    // produk pertama.
                    data: [
                        { id: '', text: '— Pilih produk —' },
                        ...this.products.map(p => ({
                            id: p.id,
                            text: p.name + ' (' + p.unit + ') — stok ' + p.qty_on_hand,
                            disabled: p.qty_on_hand <= 0,
                        })),
                    ],
                }).on('change', function () {
                    const newVal = $(this).val();

                    if (newVal && self.items.some(i => i.key !== item.key && i.product_id == newVal)) {
                        alert('Produk ini sudah dipilih di baris lain. Ubah qty di baris tersebut, atau pilih produk lain.');
                        $(this).val(item.product_id || null).trigger('change.select2');
                        return;
                    }

                    item.product_id = newVal;
                });

                if (item.product_id) {
                    this.$nextTick(() => $(el).val(String(item.product_id)).trigger('change.select2'));
                }
            },
        };
    }
</script>
@endpush
