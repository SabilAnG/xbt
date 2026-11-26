@php
    $short = function (float $n): string {
        return match (true) {
            abs($n) >= 1_000_000_000 => qty($n / 1_000_000_000, 1).' M',
            abs($n) >= 1_000_000 => qty($n / 1_000_000, 1).' jt',
            abs($n) >= 1_000 => qty($n / 1_000, 0).' rb',
            default => qty($n, 0),
        };
    };
    $money = fn (float $n) => ($n < 0 ? '−' : '').'Rp '.$short(abs($n));
    $nice = function (float $max): float {
        $max = max($max, 1);
        $magnitude = 10 ** max(floor(log10($max)) - 1, 0);

        return ceil($max / (4 * $magnitude)) * 4 * $magnitude;
    };

    // ---- Grafik garis: omzet, uang keluar, laba kotor per bulan.
    $series = [
        'revenue' => ['Omzet', '#f39a1e'],
        'total_out' => ['Uang keluar', '#4b5d7e'],
        'gross_profit' => ['Laba kotor', '#f7c79a'],
    ];
    $lineTop = $nice(max(collect(array_keys($series))->map(fn ($key) => $monthly->max($key) ?? 0)->all()));
    $chartW = 720;
    $chartH = 220;
    $count = max($monthly->count() - 1, 1);
    $points = fn (string $key) => $monthly->values()->map(fn ($row, $i) => [round($i / $count * $chartW, 2), round($chartH - max($row[$key], 0) / $lineTop * ($chartH - 12) - 6, 2)])->all();
    // Kurva halus (Catmull-Rom → Bézier).
    $smooth = function (array $pts): string {
        if (count($pts) < 2) {
            return '';
        }
        $d = 'M'.$pts[0][0].' '.$pts[0][1];
        for ($i = 0; $i < count($pts) - 1; $i++) {
            $p0 = $pts[max($i - 1, 0)];
            $p1 = $pts[$i];
            $p2 = $pts[$i + 1];
            $p3 = $pts[min($i + 2, count($pts) - 1)];
            $c1 = [round($p1[0] + ($p2[0] - $p0[0]) / 6, 2), round($p1[1] + ($p2[1] - $p0[1]) / 6, 2)];
            $c2 = [round($p2[0] - ($p3[0] - $p1[0]) / 6, 2), round($p2[1] - ($p3[1] - $p1[1]) / 6, 2)];
            $d .= " C{$c1[0]} {$c1[1]}, {$c2[0]} {$c2[1]}, {$p2[0]} {$p2[1]}";
        }

        return $d;
    };
    $lineData = $monthly->values()->map(fn ($row) => [
        'label' => $row['month']->translatedFormat('F Y'),
        'revenue' => rupiah($row['revenue']), 'total_out' => rupiah($row['total_out']), 'gross_profit' => rupiah($row['gross_profit']),
    ]);
    $linePoints = collect(array_keys($series))->mapWithKeys(fn ($key) => [$key => $points($key)]);

    // ---- Gauge: margin laba kotor.
    $margin = $headline['revenue'] > 0 ? max(0, min(100, $headline['gross_profit'] / $headline['revenue'] * 100)) : 0;
    $paidShare = $headline['revenue'] > 0 ? $headline['cash_in'] / $headline['revenue'] * 100 : 0;

    // ---- Batang: diproduksi vs terjual.
    $barTop = $nice(max($monthly->max('produced') ?? 0, $monthly->max('sold') ?? 0, 4));
    $barData = $monthly->values()->map(fn ($row) => [
        'label' => $row['month']->translatedFormat('F Y'),
        'produced' => qty($row['produced']), 'sold' => qty($row['sold']),
        'rate' => $row['produced'] > 0 ? min(100, round($row['sold'] / $row['produced'] * 100)) : 0,
    ]);

    $categoryTotal = $byCategory->sum('total');
    $gradients = ['from-[#fbb26a] to-[#ef6a2c]', 'from-[#5fb7f7] to-[#2f6be0]', 'from-[#5ee2b0] to-[#14b8a6]', 'from-[#d7a6ff] to-[#9b3fe0]'];

    $alerts = [
        ['Produk stok habis', $headline['products_out'], route('admin.items.index', ['status' => 'HABIS']), $headline['products_out'] ? 'danger' : 'success'],
        ['Produk stok menipis', $headline['products_low'], route('admin.items.index', ['status' => 'MENIPIS']), $headline['products_low'] ? 'warning' : 'success'],
        ['Bahan habis / menipis', $headline['materials_alert'], route('admin.materials.index'), $headline['materials_alert'] ? 'danger' : 'success'],
        ['Pengeluaran perlu dirinci', $headline['needs_detail'], route('admin.expenses.index', ['data_status' => 'PERLU DIRINCI']), $headline['needs_detail'] ? 'danger' : 'success'],
        ['Piutang belum dibayar', rupiah($headline['receivable']), route('admin.sales.index', ['payment' => 'unpaid']), $headline['receivable'] > 0 ? 'danger' : 'success'],
        ['Hutang pembelian bahan', rupiah($headline['purchase_payable']), route('admin.purchases.index', ['bayar' => 'hutang']), $headline['purchase_payable'] > 0 ? 'danger' : 'success'],
        ['Produksi masih draf', $headline['draft_productions'], route('admin.productions.index', ['status' => 'draft']), $headline['draft_productions'] ? 'warning' : 'success'],
        ['Total stok knalpot jadi', qty($headline['total_stock']).' unit', route('admin.items.index'), 'default'],
    ];
    $alertTone = ['danger' => 'bg-rose-500 shadow-rose-500/40', 'warning' => 'bg-amber-400 shadow-amber-400/40', 'success' => 'bg-emerald-500 shadow-emerald-500/40', 'default' => 'bg-slate-300 shadow-slate-300/40'];
    $openIcon = 'M14 4h6v6m0-6L10 14m-1-9H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1v-4';
@endphp
@extends('layouts.admin', ['title' => 'Dashboard'])

@section('actions')
    <a href="{{ route('admin.productions.create') }}" class="btn">+ Produksi</a>
    <a href="{{ route('admin.sales.create') }}" class="btn btn-primary">+ Penjualan</a>
@endsection

@section('content')
    {{-- KPI bercincin --}}
    <section class="stat-grid grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-admin.stat label="Omzet" :value="$money($headline['revenue'])" :title="rupiah($headline['revenue'])" :percent="$paidShare" :hint="'Terbayar '.qty($paidShare, 0).'%'" :href="route('admin.sales.index')" />
        <x-admin.stat label="Uang keluar" :value="$money($headline['expenses'])" :title="rupiah($headline['expenses'])" trend="down" :percent="$headline['revenue'] > 0 ? $headline['expenses'] / $headline['revenue'] * 100 : 40" :href="route('admin.expenses.index')" />
        <x-admin.stat label="Nilai stok" :value="$money($headline['stock_value'])" :title="rupiah($headline['stock_value'])" :hint="qty($headline['total_stock']).' unit'" :href="route('admin.warehouses.show', 'finish-good')" />
        <x-admin.stat label="Laba kotor" :value="$money($headline['gross_profit'])" :title="rupiah($headline['gross_profit'])" :percent="$margin" :hint="'Margin '.qty($margin, 1).'%'" />
        <x-admin.stat label="Hutang" hint="pembelian bahan" :value="$money($headline['purchase_payable'])" :title="rupiah($headline['purchase_payable'])" trend="down" :percent="$headline['purchase_payable'] > 0 ? 55 : 8" :href="route('admin.purchases.index', ['bayar' => 'hutang'])" />
    </section>

    {{-- Grafik garis + gauge --}}
    <section class="grid gap-6 xl:grid-cols-[minmax(0,2.1fr)_minmax(0,1fr)]">
        <div class="card p-5 sm:p-6" data-line-chart>
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <select class="pill h-11 min-w-[170px] cursor-pointer appearance-none pr-11 pl-3.5 text-[14px]" data-series aria-label="Seri yang ditonjolkan">
                        @foreach ($series as $key => [$name])<option value="{{ $key }}">Statistik {{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden text-[13px] font-medium text-ink-soft sm:inline">Periode</span>
                    <span class="pill">{{ $monthly->count() }} bulan</span>
                    <a href="{{ route('admin.finance.index') }}" class="tile" title="Buka laporan keuangan">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $openIcon }}"/></svg>
                    </a>
                </div>
            </div>
            <div class="mt-5 flex gap-3">
                <div class="flex h-[236px] w-12 shrink-0 flex-col justify-between pb-6 text-right text-[11px] text-ink-soft tabular-nums">
                    @foreach ([4, 3, 2, 1, 0] as $step)<span class="-translate-y-1/2">{{ $short($lineTop * $step / 4) }}</span>@endforeach
                </div>
                <div class="scrollbar-thin min-w-0 flex-1 overflow-x-auto lg:overflow-visible">
                    <div class="relative mr-8 ml-3 min-w-[520px]" data-plot>
                        <svg viewBox="-8 -8 {{ $chartW + 16 }} {{ $chartH + 16 }}" preserveAspectRatio="none" class="h-[212px] w-full overflow-visible">
                            <defs>
                                @foreach ($series as $key => [$name, $color])
                                    <linearGradient id="fade-{{ $key }}" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="{{ $chartW }}" y2="0">
                                        <stop offset="0" stop-color="{{ $color }}" stop-opacity="0"/>
                                        <stop offset="0.14" stop-color="{{ $color }}"/>
                                        <stop offset="0.86" stop-color="{{ $color }}"/>
                                        <stop offset="1" stop-color="{{ $color }}" stop-opacity="0"/>
                                    </linearGradient>
                                @endforeach
                            </defs>
                            @foreach ($series as $key => [$name, $color])
                                <path d="{{ $smooth($linePoints[$key]) }}" fill="none" stroke="url(#fade-{{ $key }})" stroke-width="4" stroke-linecap="round" vector-effect="non-scaling-stroke"
                                      data-line="{{ $key }}" data-color="{{ $color }}" class="transition-opacity duration-300"/>
                            @endforeach
                        </svg>
                        <span class="pointer-events-none absolute hidden size-[18px] -translate-1/2 rounded-full border-[3px] border-white bg-[#f39a1e] shadow-[0_3px_8px_rgb(240_98_31/0.55)]" data-dot></span>
                        <div class="pointer-events-none absolute z-10 hidden w-[196px] -translate-x-1/2 rounded-[12px] border border-white bg-white px-3.5 py-2.5 shadow-[0_12px_28px_-12px_rgb(60_66_110/0.45)]" data-tip>
                            <p class="text-[13px] font-semibold text-ink" data-tip-title></p>
                            <div class="mt-1 grid gap-0.5 text-[10.5px] leading-snug text-ink-soft" data-tip-body></div>
                        </div>
                        <div class="relative mt-3 h-4 text-[11.5px] text-ink-soft">
                            @foreach ($monthly as $row)
                                <span class="absolute -translate-x-1/2 whitespace-nowrap first:text-muted last:text-muted" style="left: {{ round((8 + $loop->index / $count * $chartW) / ($chartW + 16) * 100, 3) }}%">{{ $row['month']->translatedFormat('M y') }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card flex flex-col p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="card-title">Margin laba kotor</h2>
                <a href="{{ route('admin.finance.index') }}" class="tile" title="Buka laporan keuangan">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $openIcon }}"/></svg>
                </a>
            </div>
            <div class="relative mx-auto mt-4 w-full max-w-[300px] flex-1">
                <svg viewBox="0 0 220 190" class="w-full overflow-visible">
                    <defs>
                        <linearGradient id="gauge-fill" x1="0" y1="1" x2="1" y2="0">
                            <stop offset="0" stop-color="#f05a24"/><stop offset="1" stop-color="#fbb36b"/>
                        </linearGradient>
                        <filter id="gauge-shadow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="6" stdDeviation="7" flood-color="#4a5280" flood-opacity="0.18"/></filter>
                    </defs>
                    <path d="M40.72 150 A80 80 0 1 1 179.28 150" fill="none" stroke="var(--color-white)" stroke-width="36" stroke-linecap="butt" filter="url(#gauge-shadow)"/>
                    <path d="M40.72 150 A80 80 0 1 1 179.28 150" fill="none" stroke="url(#gauge-fill)" stroke-width="36" pathLength="100" stroke-dasharray="{{ round($margin, 1) }} 100"/>
                    <text x="34" y="182" font-size="11" fill="var(--color-ink-soft)" transform="rotate(12 34 182)">0%</text>
                    <text x="168" y="186" font-size="11" fill="var(--color-ink-soft)" transform="rotate(-14 168 186)">100%</text>
                </svg>
                <div class="absolute inset-x-0 top-[26%] flex flex-col items-center">
                    <span class="grid size-13 place-items-center rounded-full bg-white text-ink shadow-(--shadow-soft)">
                        <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 11v9H4v-9h3Zm0 0 4-7a2 2 0 0 1 3 2l-1 4h5a2 2 0 0 1 2 2.3l-1.2 6A2 2 0 0 1 16.8 20H7"/></svg>
                    </span>
                    <span class="mt-2.5 text-[27px] font-bold tracking-tight text-ink tabular-nums">{{ qty($margin, 0) }}%</span>
                </div>
            </div>
            <p class="mt-1 text-center text-[11.5px] text-muted">Laba kotor {{ rupiah($headline['gross_profit']) }} dari omzet {{ rupiah($headline['revenue']) }}</p>
        </div>
    </section>

    {{-- Progres kategori + grafik batang --}}
    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.85fr)]">
        <div class="card p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="card-title">Pengeluaran per kategori</h2>
                    <p class="text-xs text-ink-soft">Total {{ rupiah($categoryTotal) }}</p>
                </div>
                <a href="{{ route('admin.expenses.index') }}" class="tile" title="Buka pengeluaran">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $openIcon }}"/></svg>
                </a>
            </div>
            <ul class="mt-6 space-y-5">
                @forelse ($byCategory as $row)
                    @php $share = $categoryTotal > 0 ? $row['total'] / $categoryTotal * 100 : 0; @endphp
                    <li>
                        <div class="mb-2 flex items-baseline justify-between gap-2 text-[13px]">
                            <span class="truncate font-medium text-ink" title="{{ rupiah($row['total']) }}">{{ $row['name'] }}</span>
                            <span class="shrink-0 font-semibold text-ink tabular-nums">{{ qty($share, 0) }}%</span>
                        </div>
                        <div class="h-[5px] overflow-hidden rounded-full bg-line">
                            <div class="h-full rounded-full bg-linear-to-r {{ $gradients[$loop->index % 4] }}" style="width: {{ max(round($share, 2), 2) }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-muted">Belum ada pengeluaran.</li>
                @endforelse
            </ul>
        </div>

        <div class="card p-5 sm:p-6" data-bar-chart>
            <div class="flex flex-wrap items-center gap-3">
                <span class="pill h-11 pr-4 pl-3.5 text-[14px]">Produksi &amp; Penjualan</span>
                <div class="ml-auto flex items-center gap-3">
                    <span class="flex items-center gap-1.5 text-xs text-ink-soft"><span class="size-2.5 rounded-full bg-[#b8bfd0]"></span>Diproduksi</span>
                    <span class="flex items-center gap-1.5 text-xs text-ink-soft"><span class="size-2.5 rounded-full bg-brand-500"></span>Terjual</span>
                    <a href="{{ route('admin.productions.index') }}" class="tile" title="Buka produksi">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $openIcon }}"/></svg>
                    </a>
                </div>
            </div>
            <div class="mt-5 flex gap-3">
                <div class="flex h-[236px] w-9 shrink-0 flex-col justify-between pb-6 text-right text-[11px] text-ink-soft tabular-nums">
                    @foreach ([4, 3, 2, 1, 0] as $step)<span class="-translate-y-1/2">{{ qty($barTop * $step / 4) }}</span>@endforeach
                </div>
                <div class="scrollbar-thin min-w-0 flex-1 overflow-x-auto lg:overflow-visible">
                    <div class="relative min-w-[520px]" data-plot>
                        <div class="relative flex h-[212px] items-end justify-between gap-1">
                            @foreach ($monthly as $row)
                                <div class="flex h-full flex-1 items-end justify-center gap-[5px]" data-col="{{ $loop->index }}">
                                    <span class="w-[34%] max-w-[18px] rounded-t-full rounded-b-[4px] bg-linear-to-b from-[#a7afc2] via-[#cfd4df] to-surface" style="height: {{ max(round($row['produced'] / $barTop * 100, 2), 4) }}%"></span>
                                    <span class="relative w-[34%] max-w-[18px] overflow-hidden rounded-t-full rounded-b-[4px] bg-white shadow-[0_4px_10px_-5px_rgb(74_90_124/0.45)]" style="height: {{ max(round(max($row['produced'], $row['sold']) / $barTop * 100, 2), 4) }}%">
                                        <span class="absolute inset-x-0 bottom-0 rounded-b-[4px] bg-linear-to-b from-[#f2661f] via-[#f89b54] to-[#fde0c8]" style="height: {{ $row['produced'] > 0 || $row['sold'] > 0 ? round($row['sold'] / max($row['produced'], $row['sold']) * 100, 2) : 0 }}%"></span>
                                    </span>
                                </div>
                            @endforeach
                            <svg class="pointer-events-none absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none">
                                <polyline fill="none" stroke="var(--color-muted)" stroke-width="1.4" stroke-dasharray="2 3" vector-effect="non-scaling-stroke"
                                    points="{{ $monthly->values()->map(fn ($row, $i) => round(($i + 0.5) / max($monthly->count(), 1) * 100, 2).','.round(100 - $row['sold'] / $barTop * 100, 2))->implode(' ') }}"/>
                            </svg>
                        </div>
                        <span class="pointer-events-none absolute hidden w-px bg-brand-500" data-stem></span>
                        <div class="pointer-events-none absolute z-10 hidden -translate-x-1/2 items-center gap-3 rounded-2xl border border-white bg-white/95 px-4 py-2.5 shadow-(--shadow-pop)" data-tip>
                            <div>
                                <p class="text-sm font-semibold text-ink" data-tip-title></p>
                                <p class="text-[11px] text-ink-soft" data-tip-body></p>
                            </div>
                            <span class="ring size-11" style="--c1:#fbb08a;--c2:#ef6a3c" data-tip-ring><span class="text-[10px] font-semibold" data-tip-rate></span></span>
                        </div>
                        <div class="mt-3 flex justify-between gap-1 text-[11.5px] text-ink-soft">
                            @foreach ($monthly as $row)<span class="flex-1 text-center whitespace-nowrap">{{ $row['month']->translatedFormat('M y') }}</span>@endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Perlu perhatian --}}
    <section class="card p-5 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="card-title">Perlu perhatian</h2>
            <span class="hidden text-xs text-muted sm:block">klik untuk membuka daftarnya</span>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($alerts as [$label, $value, $href, $tone])
                <a href="{{ $href }}" class="flex items-center gap-3 rounded-xl border border-white bg-white px-4 py-3 shadow-(--shadow-soft) transition hover:-translate-y-px">
                    <span class="size-2.5 shrink-0 rounded-full shadow-[0_0_0_4px] {{ $alertTone[$tone] }}"></span>
                    <span class="min-w-0 flex-1 truncate text-[13px] text-ink-soft">{{ $label }}</span>
                    <span class="shrink-0 font-semibold text-ink tabular-nums">{{ $value }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Per merek --}}
    @if ($byBrand->isNotEmpty())
        <section class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Ringkasan per merek</h2>
                <a href="{{ route('admin.brands.index') }}" class="btn btn-sm">Kelola merek</a>
            </div>
            <div class="scrollbar-thin overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Merek</th><th class="num">Produk</th><th class="num">Sisa stok</th><th class="num">Nilai stok</th><th class="num">Diproduksi</th><th class="num">Terjual</th><th class="num">Omzet</th></tr></thead>
                    <tbody>
                    @foreach ($byBrand as $row)
                        <tr>
                            <td class="whitespace-nowrap"><span class="mr-2 inline-block size-2.5 rounded-full align-middle" style="background: {{ $row['color'] }}"></span><a href="{{ route('admin.items.index', ['brand' => $row['name']]) }}" class="link">{{ $row['name'] }}</a></td>
                            <td class="num">{{ qty($row['products']) }}</td>
                            <td class="num font-semibold">{{ qty($row['stock']) }}</td>
                            <td class="num">{{ rupiah($row['stock_value']) }}</td>
                            <td class="num">{{ qty($row['produced']) }}</td>
                            <td class="num">{{ qty($row['sold']) }}</td>
                            <td class="num">{{ rupiah($row['revenue']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Rekap per bulan --}}
    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Rekap per bulan</h2>
            <span class="text-xs text-muted lg:hidden">geser tabel ke samping</span>
        </div>
        <div class="scrollbar-thin overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Bulan</th><th class="num">Diproduksi</th><th class="num">Terjual</th><th class="num">Omzet</th><th class="num">Laba kotor</th>
                    <th class="num">Uang masuk</th><th class="num">Pengeluaran</th><th class="num">Belanja bahan</th><th class="num">Total keluar</th><th class="num">Selisih kas</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($monthly as $row)
                    @php $empty = ! $row['produced'] && ! $row['sold'] && ! $row['revenue'] && ! $row['total_out']; @endphp
                    <tr @class(['[&_td]:text-muted' => $empty])>
                        <td class="font-medium whitespace-nowrap text-ink">{{ $row['month']->translatedFormat('F Y') }}</td>
                        <td class="num">{{ qty($row['produced']) }}</td>
                        <td class="num">{{ qty($row['sold']) }}</td>
                        <td class="num">{{ rupiah($row['revenue']) }}</td>
                        <td class="num">{{ rupiah($row['gross_profit']) }}</td>
                        <td class="num">{{ rupiah($row['cash_in']) }}</td>
                        <td class="num">{{ rupiah($row['expenses']) }}</td>
                        <td class="num">{{ rupiah($row['purchases']) }}</td>
                        <td class="num">{{ rupiah($row['total_out']) }}</td>
                        <td @class(['num font-medium', 'text-rose-600' => $row['net_cash'] < 0, 'text-emerald-600' => $row['net_cash'] > 0])>{{ rupiah($row['net_cash']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="font-semibold">
                <tr>
                    <td>Total</td>
                    <td class="num">{{ qty($monthly->sum('produced')) }}</td>
                    <td class="num">{{ qty($monthly->sum('sold')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('revenue')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('gross_profit')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('cash_in')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('expenses')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('purchases')) }}</td>
                    <td class="num">{{ rupiah($monthly->sum('total_out')) }}</td>
                    <td @class(['num', 'text-rose-600' => $monthly->sum('net_cash') < 0, 'text-emerald-600' => $monthly->sum('net_cash') >= 0])>{{ rupiah($monthly->sum('net_cash')) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Stok yang perlu ditindak --}}
    <section class="grid gap-6 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Bahan perlu dibeli</h2>
                <a href="{{ route('admin.purchases.create') }}" class="btn btn-sm">+ Pembelian</a>
            </div>
            <div class="scrollbar-thin max-h-[420px] overflow-auto">
                <table class="table">
                    <thead><tr><th>Bahan</th><th class="num">Sisa</th><th class="num">Minimum</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($alertMaterials as $material)
                        <tr>
                            <td><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->label() }}</a></td>
                            <td class="num {{ $material->stock < 0 ? 'text-rose-600' : '' }}">{{ qty(round((float) $material->stock, 2)) }} <span class="text-muted">{{ $material->stockUnit() }}</span></td>
                            <td class="num text-muted">{{ qty($material->min_stock) }}</td>
                            <td><x-admin.status :value="$material->stockStatus()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-muted">Semua bahan aman.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Knalpot stok menipis</h2>
                <a href="{{ route('admin.items.index', ['status' => 'MENIPIS']) }}" class="btn btn-sm">Lihat semua</a>
            </div>
            <div class="scrollbar-thin max-h-[420px] overflow-auto">
                <table class="table">
                    <thead><tr><th>Produk</th><th class="num">Sisa</th><th class="num">Minimum</th></tr></thead>
                    <tbody>
                    @forelse ($lowProducts as $item)
                        <tr>
                            <td><a href="{{ route('admin.items.show', $item) }}" class="link">{{ $item->name }}</a></td>
                            <td class="num font-medium text-amber-600">{{ qty($item->stock) }}</td>
                            <td class="num text-muted">{{ qty($item->min_stock) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-muted">Tidak ada produk yang menipis.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Aktivitas --}}
    <section class="grid gap-6 lg:grid-cols-3">
        @foreach ([
            ['Stok terbanyak', null, $topStock, 'stock'],
            ['Produksi terakhir', route('admin.productions.index'), $recentProductions, 'production'],
            ['Penjualan terakhir', route('admin.sales.index'), $recentSales, 'sale'],
        ] as [$heading, $more, $rows, $kind])
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h2 class="card-title">{{ $heading }}</h2>
                    @if ($more)<a href="{{ $more }}" class="btn btn-sm">Semua</a>@endif
                </div>
                <ul class="space-y-2 px-4 pb-4 sm:px-5 sm:pb-5">
                    @forelse ($rows as $row)
                        <li class="flex items-center gap-3 rounded-xl bg-white/80 px-3.5 py-3 shadow-[0_4px_12px_-10px_rgb(70_78_120/0.5)]">
                            <div class="min-w-0 flex-1">
                                @if ($kind === 'stock')
                                    <a href="{{ route('admin.items.show', $row) }}" class="block truncate text-[13px] text-ink hover:text-brand-600">{{ $row->name }}</a>
                                @elseif ($kind === 'production')
                                    <p class="truncate text-[13px] text-ink">{{ $row->item?->name ?? $row->formula?->name }}</p>
                                    <p class="text-xs text-muted">{{ $row->produced_at->translatedFormat('d M Y') }} · {{ $row->production_number }}</p>
                                @else
                                    <p class="truncate text-[13px] text-ink">{{ $row->items->first()?->item?->name }}</p>
                                    <p class="text-xs text-muted">{{ $row->sold_at->translatedFormat('d M Y') }} · {{ $row->channel ?? '—' }}</p>
                                @endif
                            </div>
                            @if ($kind === 'stock')
                                <span class="rounded-lg bg-surface px-2 py-0.5 text-xs font-semibold text-ink tabular-nums">{{ qty($row->stock) }}</span>
                            @elseif ($kind === 'production')
                                <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 tabular-nums">+{{ qty($row->qty) }}</span>
                            @else
                                <div class="space-y-1 text-right">
                                    <p class="text-[13px] font-semibold text-ink tabular-nums">{{ rupiah($row->total) }}</p>
                                    <x-admin.status :value="$row->isPaidOff() ? 'LUNAS' : 'BELUM LUNAS'" />
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="py-8 text-center text-sm text-muted">Belum ada data.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </section>
@endsection

@push('scripts')
<script>
    (() => {
        // Grafik garis: pilih seri yang ditonjolkan, tooltip per bulan.
        const line = document.querySelector('[data-line-chart]');
        if (line) {
            const data = @json($lineData);
            const pts = @json($linePoints);
            const names = @json(collect($series)->map(fn ($s) => $s[0]));
            const plot = line.querySelector('[data-plot]');
            const svg = plot.querySelector('svg');
            const dot = plot.querySelector('[data-dot]');
            const tip = plot.querySelector('[data-tip]');
            const select = line.querySelector('[data-series]');
            const W = {{ $chartW }}, H = {{ $chartH }};

            const highlight = () => {
                line.querySelectorAll('[data-line]').forEach(path => {
                    const on = path.dataset.line === select.value;
                    path.style.opacity = on ? '1' : '0.45';
                    path.setAttribute('stroke-width', on ? '4.5' : '3');
                    if (on) path.parentNode.appendChild(path);
                });
                dot.style.background = line.querySelector(`[data-line="${select.value}"]`).dataset.color;
            };
            select.addEventListener('change', highlight);
            highlight();

            plot.addEventListener('mousemove', e => {
                const box = svg.getBoundingClientRect();
                const ratio = Math.min(Math.max((e.clientX - box.left) / box.width, 0), 1);
                const i = Math.round(ratio * (data.length - 1));
                const [x, y] = pts[select.value][i];
                const left = (x + 8) / (W + 16) * box.width;
                const top = (y + 8) / (H + 16) * box.height;
                dot.style.left = left + 'px';
                dot.style.top = top + 'px';
                dot.classList.remove('hidden');
                tip.style.left = Math.min(Math.max(left, 112), box.width - 112) + 'px';
                tip.style.top = Math.max(top - 96, -48) + 'px';
                tip.querySelector('[data-tip-title]').textContent = data[i].label;
                tip.querySelector('[data-tip-body]').innerHTML = Object.keys(names)
                    .map(k => `<span class="flex justify-between gap-2"><span>${names[k]}:</span><span class="font-medium text-ink">${data[i][k]}</span></span>`).join('');
                tip.classList.remove('hidden');
            });
            plot.addEventListener('mouseleave', () => { dot.classList.add('hidden'); tip.classList.add('hidden'); });
        }

        // Grafik batang: tooltip berisi diproduksi, terjual, dan % terjual.
        const bars = document.querySelector('[data-bar-chart]');
        if (bars) {
            const data = @json($barData);
            const plot = bars.querySelector('[data-plot]');
            const tip = plot.querySelector('[data-tip]');
            const stem = plot.querySelector('[data-stem]');
            plot.querySelectorAll('[data-col]').forEach(col => {
                col.addEventListener('mouseenter', () => {
                    const d = data[col.dataset.col];
                    const box = col.getBoundingClientRect();
                    const host = plot.getBoundingClientRect();
                    const center = box.left - host.left + box.width / 2;
                    tip.style.left = Math.min(Math.max(center, 120), host.width - 120) + 'px';
                    tip.style.top = '-18px';
                    tip.querySelector('[data-tip-title]').textContent = d.label;
                    tip.querySelector('[data-tip-body]').textContent = `Diproduksi: ${d.produced} · Terjual: ${d.sold}`;
                    tip.querySelector('[data-tip-rate]').textContent = d.rate + '%';
                    tip.querySelector('[data-tip-ring]').style.setProperty('--p', Math.max(d.rate, 4));
                    tip.classList.remove('hidden');
                    tip.classList.add('flex');
                    stem.style.left = center + 'px';
                    stem.style.top = '44px';
                    stem.style.height = '120px';
                    stem.classList.remove('hidden');
                });
            });
            plot.addEventListener('mouseleave', () => { tip.classList.add('hidden'); tip.classList.remove('flex'); stem.classList.add('hidden'); });
        }
    })();
</script>
@endpush
