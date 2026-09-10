@php
    $s = $this->summary;
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

<x-filament-panels::page>
    {{-- Only the report itself goes on paper. --}}
    <style>
        @media print {
            .fi-topbar, .fi-sidebar, .fi-header-actions, .no-print { display: none !important; }
            .fi-main, .fi-page { padding: 0 !important; margin: 0 !important; }
            .print-only { display: block !important; }
            .rekap-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
            body { background: #fff !important; }
        }
        .print-only { display: none; }
        .rekap-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .rekap-table th, .rekap-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid rgb(228 228 231 / 1); }
        .rekap-table th { text-align: left; font-weight: 600; }
        .rekap-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .rekap-table tfoot td { font-weight: 700; border-top: 2px solid rgb(161 161 170 / 1); border-bottom: none; }
        .dark .rekap-table th, .dark .rekap-table td { border-color: rgb(63 63 70 / 1); }
    </style>

    <div class="no-print">
        {{ $this->form }}
    </div>

    <div class="print-only" style="margin-bottom:1rem">
        <h1 style="font-size:1.25rem;font-weight:700">{{ setting('site.name', config('app.name')) }} — Rekap Laporan</h1>
        <p>Periode {{ \Illuminate\Support\Carbon::parse($this->data['dari'])->format('d M Y') }}
            s/d {{ \Illuminate\Support\Carbon::parse($this->data['sampai'])->format('d M Y') }}</p>
    </div>

    {{-- ringkasan --}}
    <div class="grid gap-4 md:grid-cols-3 rekap-card">
        @foreach ([
            ['Total Pembelian', $s['pembelian'], 'text-primary-600'],
            ['Total Penjualan', $s['penjualan'], 'text-success-600'],
            ['Total Pengeluaran', $s['pengeluaran'], 'text-danger-600'],
            ['Modal Terjual', $s['modal'], 'text-gray-600'],
            ['Laba Kotor', $s['laba_kotor'], 'text-success-600'],
            ['Laba Bersih', $s['laba_bersih'], $s['laba_bersih'] < 0 ? 'text-danger-600' : 'text-success-600'],
        ] as [$label, $value, $tone])
            <x-filament::section class="rekap-card">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-xl font-bold {{ $tone }}">{{ $rp($value) }}</div>
            </x-filament::section>
        @endforeach
    </div>

    @if ($this->shows('pembelian'))
        <x-filament::section class="rekap-card">
            <x-slot name="heading">Pembelian ({{ $this->purchases->count() }} nota)</x-slot>
            <div style="overflow-x:auto">
                <table class="rekap-table">
                    <thead>
                        <tr><th>Tanggal</th><th>No. Nota</th><th>Supplier</th><th>Dompet</th><th class="num">Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($this->purchases as $p)
                            <tr>
                                <td>{{ $p->purchased_at?->format('d/m/Y') }}</td>
                                <td>{{ $p->invoice_number }}</td>
                                <td>{{ $p->supplier_name ?: '—' }}</td>
                                <td>{{ $p->wallet?->name ?: '—' }}</td>
                                <td class="num">{{ $rp($p->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-gray-500">Tidak ada pembelian dibukukan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><td colspan="4">Subtotal</td><td class="num">{{ $rp($s['pembelian']) }}</td></tr></tfoot>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($this->shows('penjualan'))
        <x-filament::section class="rekap-card">
            <x-slot name="heading">Penjualan ({{ $this->sales->count() }} nota)</x-slot>
            <div style="overflow-x:auto">
                <table class="rekap-table">
                    <thead>
                        <tr><th>Tanggal</th><th>No. Nota</th><th>Pembeli</th><th class="num">Total</th><th class="num">Modal</th><th class="num">Laba</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($this->sales as $sale)
                            <tr>
                                <td>{{ $sale->sold_at?->format('d/m/Y') }}</td>
                                <td>{{ $sale->invoice_number }}</td>
                                <td>{{ $sale->customer_name ?: '—' }}</td>
                                <td class="num">{{ $rp($sale->total) }}</td>
                                <td class="num">{{ $rp($sale->total_cost) }}</td>
                                <td class="num">{{ $rp($sale->grossProfit()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-gray-500">Tidak ada penjualan dibukukan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3">Subtotal</td>
                            <td class="num">{{ $rp($s['penjualan']) }}</td>
                            <td class="num">{{ $rp($s['modal']) }}</td>
                            <td class="num">{{ $rp($s['laba_kotor']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($this->shows('pengeluaran'))
        <x-filament::section class="rekap-card">
            <x-slot name="heading">Pengeluaran ({{ $this->expenses->count() }} bukti)</x-slot>
            <div style="overflow-x:auto">
                <table class="rekap-table">
                    <thead>
                        <tr><th>Tanggal</th><th>No. Bukti</th><th>Kategori</th><th>Jenis</th><th>Kepada</th><th class="num">Jumlah</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($this->expenses as $e)
                            <tr>
                                <td>{{ $e->spent_at?->format('d/m/Y') }}</td>
                                <td>{{ $e->reference_number }}</td>
                                <td>{{ $e->category?->name ?: '—' }}</td>
                                <td>{{ $e->category?->type?->name ?: '—' }}</td>
                                <td>{{ $e->paid_to ?: '—' }}</td>
                                <td class="num">{{ $rp($e->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-gray-500">Tidak ada pengeluaran dibukukan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><td colspan="5">Subtotal</td><td class="num">{{ $rp($s['pengeluaran']) }}</td></tr></tfoot>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Grafik arus kas harian. Batang CSS, bukan library — supaya ikut   --}}
    {{-- tercetak dengan benar dan tidak menambah beban JavaScript.        --}}
    {{-- ---------------------------------------------------------------- --}}
    @php $flow = $this->dailyFlow; $peak = max(1, $flow->max(fn ($d) => max($d->masuk, $d->keluar)) ?: 1); @endphp
    @if ($flow->isNotEmpty())
        <x-filament::section class="rekap-card">
            <x-slot name="heading">Arus Kas Harian</x-slot>
            <x-slot name="description">Hijau = uang masuk (penjualan) · Merah = uang keluar (pembelian + pengeluaran)</x-slot>

            <div style="display:flex;flex-direction:column;gap:.5rem">
                @foreach ($flow as $d)
                    <div style="display:grid;grid-template-columns:5.5rem 1fr;gap:.75rem;align-items:center">
                        <div style="font-size:.75rem;color:#71717a">{{ \Illuminate\Support\Carbon::parse($d->tanggal)->format('d M') }}</div>
                        <div style="display:flex;flex-direction:column;gap:2px">
                            <div style="display:flex;align-items:center;gap:.5rem">
                                <div style="height:10px;border-radius:3px;background:#16a34a;width:{{ max(1, round($d->masuk / $peak * 100)) }}%"></div>
                                <span style="font-size:.7rem;color:#16a34a;white-space:nowrap">{{ $rp($d->masuk) }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:.5rem">
                                <div style="height:10px;border-radius:3px;background:#dc2626;width:{{ max(1, round($d->keluar / $peak * 100)) }}%"></div>
                                <span style="font-size:.7rem;color:#dc2626;white-space:nowrap">{{ $rp($d->keluar) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @if ($this->shows('penjualan'))
        @php $byCat = $this->salesByCategory; $byType = $this->salesByType; $top = $this->topItems; @endphp

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ([['Rekap per Kategori Produk', $byCat], ['Rekap per Produk', $byType]] as [$heading, $rows])
                <x-filament::section class="rekap-card">
                    <x-slot name="heading">{{ $heading }}</x-slot>
                    @php $maxOmzet = max(1, $rows->max('omzet') ?: 1); @endphp
                    <table class="rekap-table">
                        <thead>
                            <tr><th>Nama</th><th class="num">Qty</th><th class="num">Omzet</th><th class="num">Laba</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $r)
                                <tr>
                                    <td>
                                        {{ $r->nama }}
                                        <div style="height:4px;border-radius:2px;background:#3b82f6;margin-top:4px;width:{{ max(2, round($r->omzet / $maxOmzet * 100)) }}%"></div>
                                    </td>
                                    <td class="num">{{ rtrim(rtrim((string) $r->qty, '0'), '.') }}</td>
                                    <td class="num">{{ $rp($r->omzet) }}</td>
                                    <td class="num">{{ $rp($r->omzet - $r->modal) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-gray-500">Belum ada penjualan pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section class="rekap-card">
            <x-slot name="heading">10 Barang Terlaris</x-slot>
            <table class="rekap-table">
                <thead><tr><th>#</th><th>Barang</th><th class="num">Qty Terjual</th><th class="num">Omzet</th></tr></thead>
                <tbody>
                    @forelse ($top as $i => $r)
                        <tr>
                            <td style="width:2rem;color:#71717a">{{ $i + 1 }}</td>
                            <td>{{ $r->nama }}</td>
                            <td class="num">{{ rtrim(rtrim((string) $r->qty, '0'), '.') }}</td>
                            <td class="num">{{ $rp($r->omzet) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-gray-500">Belum ada penjualan pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif

    @if ($this->shows('pengeluaran'))
        @php $byExpType = $this->expensesByType; $maxExp = max(1, $byExpType->max('jumlah') ?: 1); @endphp
        <x-filament::section class="rekap-card">
            <x-slot name="heading">Pengeluaran per Jenis</x-slot>
            <table class="rekap-table">
                <thead><tr><th>Jenis</th><th class="num">Banyak</th><th class="num">Jumlah</th></tr></thead>
                <tbody>
                    @forelse ($byExpType as $r)
                        <tr>
                            <td>
                                {{ $r->nama }}
                                <div style="height:4px;border-radius:2px;background:#dc2626;margin-top:4px;width:{{ max(2, round($r->jumlah / $maxExp * 100)) }}%"></div>
                            </td>
                            <td class="num">{{ $r->banyak }}</td>
                            <td class="num">{{ $rp($r->jumlah) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-gray-500">Belum ada pengeluaran pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
