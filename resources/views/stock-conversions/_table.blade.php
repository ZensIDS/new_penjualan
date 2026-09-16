{{--
    Dirender penuh saat load awal (di-include index.blade.php) DAN dikirim sebagai
    response AJAX saat user mengetik di pencarian / klik pagination
    (lihat StockConversionController@index & initAjaxListSearch di layouts/app.blade.php).
--}}
<div data-total="{{ $conversions->total() }}">
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-ink/[0.03] text-left text-ink/50">
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">No. Bongkar</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Tanggal</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Unit Dibongkar</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Qty</th>
                        {{-- <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Nilai HPP</th> --}}
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Komponen</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3.5 font-semibold text-xs uppercase tracking-wide text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/[0.06]">
                    @forelse ($conversions as $conversion)
                        <tr class="hover:bg-amber-50/40 transition-colors">
                            <td class="px-5 py-3.5 font-medium tnum">{{ $conversion->conversion_number }}</td>
                            <td class="px-5 py-3.5 text-ink/60 tnum">{{ $conversion->conversion_date->format('d M Y') }}</td>
                            <td class="px-5 py-3.5">{{ $conversion->sourceProduct->name ?? '-' }}</td>
                            <td class="px-5 py-3.5 text-right tnum">
                                {{ $conversion->source_qty }} {{ $conversion->sourceProduct->unit ?? '' }}
                            </td>
                            {{-- <td class="px-5 py-3.5 text-right tnum">Rp {{ number_format($conversion->total_hpp, 0, ',', '.') }}</td> --}}
                            <td class="px-5 py-3.5 text-right tnum text-ink/60">{{ $conversion->results_count }} jenis</td>
                            <td class="px-5 py-3.5">
                                @if ($conversion->isDraft())
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">Belum Selesai</span>
                                @else
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800">Selesai</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('stock-conversions.show', $conversion) }}" class="text-ink/60 hover:text-ink font-medium transition-colors">
                                    Lihat &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-ink/40">
                                {{ request('search') ? 'Tidak ada pembongkaran yang cocok dengan pencarian.' : 'Belum ada pembongkaran unit.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 ajax-pagination">
        {{ $conversions->links() }}
    </div>
</div>