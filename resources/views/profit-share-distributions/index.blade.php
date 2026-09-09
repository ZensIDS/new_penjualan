@extends('layouts.app')

@section('page-title', 'Distribusi Bagi Hasil')

@section('content')
<div
    x-data="profitShareDistributionPage({{ Illuminate\Support\Js::from($activeProfitShares) }})"
    x-cloak
>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <p class="text-2xl font-display font-semibold tracking-tight">Rp{{ number_format($totalAllTime, 0, ',', '.') }}</p>
            <p class="text-sm text-ink/50">total sudah pernah dibagikan (sepanjang waktu)</p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('profit-shares.index') }}"
                class="inline-flex items-center gap-2 text-sm font-semibold border border-ink/12 text-ink px-5 py-2.5 rounded-xl hover:bg-ink/[0.03] transition-colors"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-8.13a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm-6 1a4 4 0 1 1 0 8"/></svg>
                Pengaturan Bagi Hasil
            </a>
        @if (auth()->user()->isSuperadmin())
            <button
                @click="openCreate()"
                class="inline-flex items-center gap-2 text-sm font-semibold bg-gradient-to-r from-amber-400 to-amber-500 text-ink px-5 py-2.5 rounded-xl shadow-glow hover:brightness-105 active:scale-[0.98] transition-all"
            >
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Bagikan Sekarang
            </button>
        @endif
        </div>
    </div>

    <div x-show="flash" x-cloak x-transition
         class="mb-4 rounded-xl text-sm px-4 py-3 shadow-card"
         :class="flashType === 'error' ? 'bg-red-50 text-red-900 border border-red-600/15' : 'bg-emerald-50 text-emerald-900 border border-emerald-600/15'">
        <span x-text="flash"></span>
    </div>

    {{-- Catatan penting supaya tidak ada yang salah paham soal dampak transaksi ini --}}
    <div class="mb-6 rounded-2xl border border-sky-600/15 bg-sky-50 px-5 py-4 flex gap-3 items-start">
        <svg viewBox="0 0 24 24" class="h-5 w-5 text-sky-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8h.01M11 12h1v4h1"/></svg>
        <p class="text-sm text-sky-900">
            Pencatatan di halaman ini <strong>tidak memengaruhi Laporan Laba Rugi</strong> (laba sudah dihitung apa adanya),
            tapi <strong>tetap mengurangi kas</strong> dan otomatis muncul di
            <a href="{{ route('reports.cash-flow') }}" class="underline decoration-dotted underline-offset-2 hover:text-sky-700">Laporan Arus Kas</a>
            sebagai kas keluar. Pengaturan nama orang & persentase ada di halaman
            <a href="{{ route('profit-shares.index') }}" class="underline decoration-dotted underline-offset-2 hover:text-sky-700">Bagi Hasil</a>.
        </p>
    </div>

    @include('partials.date-range-filter', ['routeName' => 'profit-share-distributions.index', 'dateLabel' => 'Tgl Distribusi', 'extraParams' => ['search' => $search]])

    <div class="relative mb-4 max-w-sm">
        <svg viewBox="0 0 24 24" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
        <input
            id="distribution-search"
            type="text"
            value="{{ $search }}"
            placeholder="Cari nama penerima atau catatan..."
            autocomplete="off"
            class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow"
        >
    </div>

    <div id="distribution-table-container">
        @include('profit-share-distributions._table')
    </div>

    {{-- Modal: Bagikan Sekarang --}}
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div x-show="modalOpen" x-transition.opacity @click="modalOpen = false" class="absolute inset-0 bg-ink/50 backdrop-blur-sm"></div>

        <div
            x-show="modalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="relative bg-white w-full max-w-lg rounded-2xl shadow-panel max-h-[90vh] overflow-y-auto scroll-thin-light"
        >
            <div class="h-1.5 bg-gradient-to-r from-amber-400 to-amber-500 rounded-t-2xl"></div>

            <div class="p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="h-10 w-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-8.13a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm-6 1a4 4 0 1 1 0 8"/></svg>
                    </span>
                    <h2 class="font-display font-semibold text-lg">Bagikan Sekarang</h2>
                </div>

                <template x-if="activeProfitShares.length === 0">
                    <p class="text-sm text-red-700 bg-red-50 border border-red-600/15 rounded-xl px-4 py-3">
                        Belum ada orang aktif di halaman Bagi Hasil. Tambahkan dulu di sana sebelum membagikan.
                    </p>
                </template>

                <form @submit.prevent="submit()" x-show="activeProfitShares.length > 0">
                    <div class="space-y-4">
                        {{-- Ambil otomatis dari Laba Rugi periode tertentu. Hasilnya cuma
                             ngisi Total Dibagi & rincian per orang di bawah — semuanya
                             tetap bisa diedit manual sebelum disimpan. --}}
                        <div class="rounded-xl border border-ink/10 bg-ink/[0.02] p-3.5">
                            <p class="text-xs font-semibold text-ink/50 uppercase tracking-wide mb-2">Ambil Otomatis dari Laba Rugi</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-ink/50 mb-1">Periode Dari</label>
                                    <input type="date" x-model="period.start" @change="fetchNetProfit()"
                                           class="w-full rounded-lg border border-ink/12 px-3 py-2 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                </div>
                                <div>
                                    <label class="block text-xs text-ink/50 mb-1">Sampai</label>
                                    <input type="date" x-model="period.end" @change="fetchNetProfit()"
                                           class="w-full rounded-lg border border-ink/12 px-3 py-2 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                </div>
                            </div>
                            <p class="text-xs mt-2 text-ink/40" x-show="loadingProfit" x-cloak>Menghitung laba periode ini...</p>
                            <template x-if="!loadingProfit && netProfitInfo">
                                <div class="text-xs mt-2 space-y-0.5">
                                    <p :class="netProfitInfo.net_profit > 0 ? 'text-emerald-700' : 'text-red-700'">
                                        Laba Bersih periode ini: <span class="font-semibold" x-text="'Rp' + formatRupiah(Math.round(netProfitInfo.net_profit))"></span>
                                    </p>
                                    <p x-show="netProfitInfo.already_distributed > 0" x-cloak class="text-amber-700">
                                        &#9888; Sudah pernah dibagikan Rp<span x-text="formatRupiah(Math.round(netProfitInfo.already_distributed))"></span> pada periode ini sebelumnya — cek lagi supaya tidak dobel.
                                    </p>
                                </div>
                            </template>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1.5">Tanggal Distribusi</label>
                                <input type="date" x-model="form.distribution_date"
                                       class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                <p class="text-xs text-red-600 mt-1" x-text="errors.distribution_date?.[0]"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1.5">Total Dibagi</label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-ink/40">Rp</span>
                                    <input type="text" inputmode="numeric"
                                           :value="formatRupiah(poolAmount)"
                                           @input="poolAmount = parseRupiah($event.target.value); $event.target.value = formatRupiah(poolAmount); recalcFromPool()"
                                           placeholder="0"
                                           class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm tnum focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-ink/40 -mt-2">
                            Total Dibagi otomatis terisi dari Laba Bersih periode di atas — bisa diketik ulang manual kalau perlu, jumlah per orang di bawah juga bisa disesuaikan.
                        </p>

                        <div class="rounded-xl border border-ink/10 divide-y divide-ink/[0.06] overflow-hidden">
                            <template x-for="(item, idx) in form.items" :key="item.profit_share_id">
                                <div class="flex items-center gap-3 px-4 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium truncate" x-text="item.name"></p>
                                        <p class="text-xs text-ink/40" x-text="item.percentage + '%'"></p>
                                    </div>
                                    <div class="relative w-40 shrink-0">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-ink/40">Rp</span>
                                        <input type="text" inputmode="numeric"
                                               :value="formatRupiah(item.amount)"
                                               @input="item.amount = parseRupiah($event.target.value); $event.target.value = formatRupiah(item.amount)"
                                               class="w-full rounded-lg border border-ink/12 pl-8 pr-2.5 py-2 text-sm tnum text-right focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-between text-sm px-1">
                            <span class="text-ink/50">Total yang akan tercatat</span>
                            <span class="font-semibold tnum" x-text="'Rp' + formatRupiah(itemsTotal)"></span>
                        </div>
                        <p class="text-xs text-red-600 px-1" x-text="errors.items?.[0]"></p>

                        <div>
                            <label class="block text-sm font-medium mb-1.5">Catatan</label>
                            <input type="text" x-model="form.note" placeholder="Opsional, mis. Bagi hasil bulan Agustus"
                                   class="w-full rounded-xl border border-ink/12 px-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
                            <p class="text-xs text-red-600 mt-1" x-text="errors.note?.[0]"></p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" @click="modalOpen = false"
                                class="text-sm font-medium px-4 py-2.5 rounded-xl border border-ink/12 hover:bg-ink/[0.03] transition-colors">Batal</button>
                        <button type="submit" :disabled="saving"
                                class="text-sm font-semibold px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-ink shadow-glow hover:brightness-105 disabled:opacity-50 transition-all">
                            <span x-text="saving ? 'Menyimpan...' : 'Simpan & Catat Kas Keluar'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function profitShareDistributionPage(activeProfitShares) {
        return {
            activeProfitShares,
            modalOpen: false,
            saving: false,
            errors: {},
            flash: null,
            flashType: 'success',
            poolAmount: '',
            period: { start: '', end: '' },
            netProfitInfo: null,
            loadingProfit: false,
            form: { distribution_date: '', note: '', items: [] },

            get itemsTotal() {
                return this.form.items.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
            },

            openCreate() {
                this.errors = {};
                this.poolAmount = '';
                this.netProfitInfo = null;
                // Default periode = bulan berjalan, langsung ditarik otomatis saat
                // modal dibuka — tetap bisa diganti user ke rentang lain.
                const now = new Date();
                const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                this.period = {
                    start: startOfMonth.toISOString().substring(0, 10),
                    end: now.toISOString().substring(0, 10),
                };
                this.form = {
                    distribution_date: now.toISOString().substring(0, 10),
                    note: '',
                    items: this.activeProfitShares.map(ps => ({
                        profit_share_id: ps.id,
                        name: ps.name,
                        percentage: parseFloat(ps.percentage),
                        amount: '',
                    })),
                };
                this.modalOpen = true;
                this.fetchNetProfit();
            },

            // Tarik Laba Bersih untuk periode yang dipilih (pakai rumus yang sama
            // dengan Laporan Laba Rugi), lalu jadikan itu isian awal Total Dibagi +
            // otomatis kesplit ke tiap orang sesuai persentase. Kalau labanya
            // negatif/nihil, dibiarkan kosong supaya tidak salah isi otomatis
            // dengan angka minus — user tetap bisa isi manual kalau mau.
            async fetchNetProfit() {
                if (!this.period.start || !this.period.end) return;

                this.loadingProfit = true;
                try {
                    const params = new URLSearchParams({ start_date: this.period.start, end_date: this.period.end });
                    const res = await fetch(`{{ route('profit-share-distributions.net-profit') }}?${params}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('Gagal mengambil data laba.');
                    const data = await res.json();
                    this.netProfitInfo = data;

                    if (data.net_profit > 0) {
                        this.poolAmount = Math.round(data.net_profit);
                        this.recalcFromPool();
                    }
                } catch (e) {
                    // Diam-diam gagal — user masih bisa isi Total Dibagi manual.
                } finally {
                    this.loadingProfit = false;
                }
            },

            // Hitung ulang jumlah tiap orang berdasarkan persentase x total yang
            // diisi. Baris terakhir menampung sisa pembulatan supaya jumlah semua
            // baris selalu persis sama dengan Total Dibagi yang diketik. Dibulatkan
            // ke rupiah bulat (bukan desimal) — sama seperti input nominal lain di
            // aplikasi ini (lihat window.formatRupiah/parseRupiah).
            recalcFromPool() {
                const pool = Math.round(parseFloat(this.poolAmount) || 0);
                let running = 0;
                this.form.items.forEach((item, idx) => {
                    if (idx === this.form.items.length - 1) {
                        item.amount = Math.max(0, pool - running);
                    } else {
                        const amt = Math.round(pool * (item.percentage / 100));
                        item.amount = amt;
                        running += amt;
                    }
                });
            },

            async submit() {
                this.saving = true;
                this.errors = {};

                const payload = {
                    distribution_date: this.form.distribution_date,
                    note: this.form.note,
                    items: this.form.items.filter(item => parseFloat(item.amount) > 0),
                };

                const { ok, status, data } = await window.ajaxSend(`{{ route('profit-share-distributions.store') }}`, 'POST', payload);
                this.saving = false;

                if (ok) {
                    this.modalOpen = false;
                    this.flashType = 'success';
                    this.flash = data.message;
                    setTimeout(() => window.location.reload(), 500);
                    return;
                }

                if (status === 422) {
                    this.errors = data.errors || {};
                    return;
                }

                this.flashType = 'error';
                this.flash = data.message || 'Terjadi kesalahan.';
            },

            async remove(id) {
                if (!confirm('Hapus catatan distribusi ini? Kas yang sudah dikurangi akan dikembalikan.')) return;

                const { ok, data } = await window.ajaxSend(`{{ url('profit-share-distributions') }}/${id}`, 'DELETE');

                this.flashType = ok ? 'success' : 'error';
                this.flash = data.message || (ok ? 'Berhasil dihapus.' : 'Gagal menghapus.');

                if (ok) setTimeout(() => window.location.reload(), 500);
            },
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.initAjaxListSearch({
            inputEl: document.getElementById('distribution-search'),
            containerEl: document.getElementById('distribution-table-container'),
            baseUrl: '{{ route('profit-share-distributions.index') }}',
            getExtraParams: () => ({
                start_date: document.querySelector('input[name="start_date"]')?.value || '',
                end_date: document.querySelector('input[name="end_date"]')?.value || '',
            }),
        });
    });
</script>
@endpush
