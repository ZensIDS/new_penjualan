{{--
    Partial filter tanggal, dipakai di reports.profit-loss & reports.cash-flow.
    Butuh variabel: $routeName (nama route saat ini), $startDate, $endDate.
--}}
<div class="mb-6 rounded-2xl border border-ink/10 bg-white shadow-card p-4 sm:p-5">
    <form method="GET" action="{{ route($routeName) }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-ink/50 mb-1 uppercase tracking-wide">Dari Tanggal</label>
            <input type="date" name="start_date" value="{{ $startDate }}"
                   class="rounded-lg border border-ink/15 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400/60">
        </div>
        <div>
            <label class="block text-xs font-medium text-ink/50 mb-1 uppercase tracking-wide">Sampai Tanggal</label>
            <input type="date" name="end_date" value="{{ $endDate }}"
                   class="rounded-lg border border-ink/15 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400/60">
        </div>
        <button type="submit"
                class="rounded-lg bg-ink text-white text-sm font-medium px-4 py-2 hover:bg-ink/90 transition-colors">
            Terapkan
        </button>

        <div class="flex items-center gap-1.5 ml-auto flex-wrap">
            @php
                $presets = [
                    'Hari Ini'          => [now()->toDateString(), now()->toDateString()],
                    'Minggu Ini'        => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
                    'Bulan Ini'         => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                    'Bulan Lalu'        => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
                    '30 Hari Terakhir'  => [now()->subDays(29)->toDateString(), now()->toDateString()],
                ];
            @endphp
            @foreach ($presets as $label => $range)
                <a href="{{ route($routeName, ['start_date' => $range[0], 'end_date' => $range[1]]) }}"
                   class="text-xs font-medium px-3 py-1.5 rounded-full border transition-colors
                          {{ $startDate === $range[0] && $endDate === $range[1] ? 'bg-amber-400/90 border-amber-400/90 text-ink' : 'border-ink/15 text-ink/60 hover:border-ink/30' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </form>
    <p class="text-xs text-ink/40 mt-3">
        Menampilkan data periode
        <span class="font-medium text-ink/60">{{ \Illuminate\Support\Carbon::parse($startDate)->translatedFormat('d M Y') }}</span>
        &ndash;
        <span class="font-medium text-ink/60">{{ \Illuminate\Support\Carbon::parse($endDate)->translatedFormat('d M Y') }}</span>
    </p>
</div>
