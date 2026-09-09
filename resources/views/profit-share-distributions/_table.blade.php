{{--
    Partial ini dirender penuh saat load awal halaman (di-include index.blade.php)
    DAN dikirim sebagai response AJAX saat user mengetik di kolom pencarian atau
    klik pagination (lihat ProfitShareDistributionController@index &
    initAjaxListSearch di layouts/app.blade.php).

    Tiap baris punya x-data lokal (open) buat toggle rincian per-orang tanpa
    perlu request tambahan, karena item sudah di-eager-load dari controller.
    Tombol Hapus merujuk ke scope "profitShareDistributionPage" milik elemen
    x-data induk di index.blade.php.
--}}
<div>
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-ink/[0.03] text-left text-ink/50">
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Penerima</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Catatan</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Total</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/[0.06]">
                    @forelse ($distributions as $distribution)
                        <tr x-data="{ open: false }" class="hover:bg-amber-50/40 transition-colors align-top">
                            <td class="px-5 py-3.5 tnum text-ink/70 whitespace-nowrap">{{ $distribution->distribution_date->format('d M Y') }}</td>
                            <td class="px-5 py-3.5">
                                <button @click="open = !open" class="inline-flex items-center gap-1.5 text-ink/70 hover:text-ink font-medium">
                                    <span>{{ $distribution->items->count() }} orang</span>
                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div x-show="open" x-cloak x-transition class="mt-2 space-y-1">
                                    @foreach ($distribution->items as $item)
                                        <div class="flex items-center justify-between gap-3 text-xs text-ink/60 max-w-xs">
                                            <span class="truncate">{{ $item->name }}@if($item->percentage !== null) <span class="text-ink/35">({{ number_format($item->percentage, 2, ',', '.') }}%)</span>@endif</span>
                                            <span class="tnum font-medium text-ink/80 shrink-0">Rp{{ number_format($item->amount, 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-ink/50">{{ $distribution->note ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right tnum font-semibold text-red-700/90">Rp{{ number_format($distribution->total_amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @if (auth()->user()->isSuperadmin())
                                    <button
                                        @click="remove({{ $distribution->id }})"
                                        class="text-red-600/80 hover:text-red-700 font-medium transition-colors"
                                    >Hapus</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-ink/40">
                                {{ request('search') ? 'Tidak ada distribusi yang cocok dengan pencarian.' : 'Belum pernah ada distribusi bagi hasil.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 ajax-pagination">
        {{ $distributions->links() }}
    </div>
</div>
