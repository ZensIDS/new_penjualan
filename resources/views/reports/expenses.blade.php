@extends('layouts.app')

@section('title', 'Laporan Pengeluaran')
@section('page-title', 'Laporan Pengeluaran')

@section('content')

    {{-- Filter: rentang tanggal + kategori dalam satu form --}}
    <div class="mb-6 rounded-2xl border border-ink/10 bg-white shadow-card p-4 sm:p-5">
        <form method="GET" action="{{ route('reports.expenses') }}" class="flex flex-wrap items-end gap-3">
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
            <div>
                <label class="block text-xs font-medium text-ink/50 mb-1 uppercase tracking-wide">Kategori</label>
                <select name="category_id"
                        class="rounded-lg border border-ink/15 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400/60 bg-white">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" {{ (int) $categoryId === $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="rounded-lg bg-ink text-white text-sm font-medium px-4 py-2 hover:bg-ink/90 transition-colors">
                Terapkan
            </button>

            <a href="{{ route('reports.export.expenses', ['start_date' => $startDate, 'end_date' => $endDate, 'category_id' => $categoryId]) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-600/20 bg-emerald-50 text-emerald-700 text-sm font-medium px-4 py-2 hover:bg-emerald-100 transition-colors">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                Export Excel
            </a>

            <div class="flex items-center gap-1.5 ml-auto flex-wrap">
                @php
                    $presets = [
                        'Hari Ini'         => [now()->toDateString(), now()->toDateString()],
                        'Minggu Ini'       => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
                        'Bulan Ini'        => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                        'Bulan Lalu'       => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
                        '30 Hari Terakhir' => [now()->subDays(29)->toDateString(), now()->toDateString()],
                    ];
                @endphp
                @foreach ($presets as $label => $range)
                    <a href="{{ route('reports.expenses', ['start_date' => $range[0], 'end_date' => $range[1], 'category_id' => $categoryId]) }}"
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
            @if ($categoryId)
                &middot; Kategori:
                <span class="font-medium text-ink/60">{{ $categories->firstWhere('id', $categoryId)->name ?? '—' }}</span>
            @endif
        </p>
    </div>

    {{-- KPI: agregasi SQL atas SELURUH baris pada filter ini, independen dari pagination/pencarian deskripsi --}}
    <div class="grid grid-cols-1 @4xl:grid-cols-3 gap-4 mb-6">
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Jumlah Transaksi</p>
            <p class="font-display font-semibold text-2xl tnum">{{ number_format($kpis['count'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-ink to-[#0a0a0b] text-white shadow-panel p-6">
            <p class="text-xs font-medium text-white/50 mb-2 uppercase tracking-wide">Total Pengeluaran</p>
            <p class="font-display font-semibold text-2xl tnum text-amber-400">Rp {{ number_format($kpis['total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl border border-ink/10 bg-white shadow-card p-6">
            <p class="text-xs font-medium text-ink/50 mb-2 uppercase tracking-wide">Rata-rata / Transaksi</p>
            <p class="font-display font-semibold text-2xl tnum">Rp {{ number_format($kpis['average'], 0, ',', '.') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.expenses') }}" class="relative max-w-sm mb-4">
        <input type="hidden" name="start_date" value="{{ $startDate }}">
        <input type="hidden" name="end_date" value="{{ $endDate }}">
        <input type="hidden" name="category_id" value="{{ $categoryId }}">
        <svg viewBox="0 0 24 24" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari deskripsi pengeluaran..." onchange="this.form.submit()"
               class="w-full rounded-xl border border-ink/12 pl-10 pr-3.5 py-2.5 text-sm focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/15 transition-shadow">
    </form>

    {{-- Tabel per item, urut tanggal terbaru --}}
    <div class="rounded-2xl border border-ink/10 bg-white shadow-card overflow-hidden">
        @if ($data->isEmpty())
            <p class="px-5 py-10 text-center text-ink/40 text-sm">
                @if ($search)
                    Tidak ada pengeluaran yang cocok dengan pencarian.
                @else
                    Tidak ada pengeluaran pada periode / kategori ini.
                @endif
            </p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-ink/10">
                        <th class="px-5 py-3 font-medium">Tanggal</th>
                        <th class="px-5 py-3 font-medium">Kategori</th>
                        <th class="px-5 py-3 font-medium">Deskripsi</th>
                        <th class="px-5 py-3 font-medium text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink/[0.06]">
                    @foreach ($data as $e)
                        <tr class="hover:bg-amber-50/30 transition-colors">
                            <td class="px-5 py-3 tnum whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($e['expense_date'])->translatedFormat('d M Y') }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-ink/5 text-ink/60">
                                    {{ $e['category'] }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-ink/70">{{ $e['description'] ?: '—' }}</td>
                            <td class="px-5 py-3 tnum text-right font-medium text-red-700">Rp {{ number_format($e['amount'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">
        {{ $data->links() }}
    </div>

@endsection
