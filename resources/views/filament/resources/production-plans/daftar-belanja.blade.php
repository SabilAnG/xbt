@php
    $plan = $this->record;
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');

    $kebutuhan = $plan->requirements();
    $belanja = collect($kebutuhan)->reject(fn ($r) => $r['cukup']);
    $cukup = collect($kebutuhan)->filter(fn ($r) => $r['cukup']);

    $tier = $this->getTier();
    $hasil = $plan->revenueAt($tier);
@endphp

<x-filament-panels::page>
    <style>
        .bl-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .bl-table th, .bl-table td { padding: .5rem .75rem; border-bottom: 1px solid rgb(228 228 231); }
        .bl-table th { text-align: left; font-weight: 600; }
        .bl-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .bl-table tfoot td { font-weight: 700; border-top: 2px solid rgb(161 161 170); border-bottom: none; }
        .dark .bl-table th, .dark .bl-table td { border-color: rgb(63 63 70); }
        @media print {
            .fi-topbar, .fi-sidebar, .fi-header-actions { display: none !important; }
        }
    </style>

    {{-- ringkasan angka besar --}}
    <div class="grid gap-4 md:grid-cols-4">
        @foreach ([
            ['Target Produksi', $num($plan->targetUnits()) . ' unit', 'text-primary-600'],
            ['Modal Produksi', $rp($plan->productionCost()), 'text-warning-600'],
            ['Perlu Belanja Sekarang', $rp($plan->shoppingCost()), $plan->shoppingCost() > 0 ? 'text-danger-600' : 'text-success-600'],
            ['Perkiraan Laba' . ($tier ? ' (' . $tier->name . ')' : ''), $rp($hasil['laba']), 'text-success-600'],
        ] as [$label, $val, $tone])
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-xl font-bold {{ $tone }}">{{ $val }}</div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- apa yang direncanakan --}}
    <x-filament::section>
        <x-slot name="heading">Yang Direncanakan</x-slot>

        <div style="overflow-x:auto">
            <table class="bl-table">
                <thead>
                    <tr>
                        <th>Formula</th>
                        <th class="num">Target</th>
                        <th class="num">Jalan Resep</th>
                        <th class="num">Hasil Nyata</th>
                        <th class="num">HPP / unit</th>
                        <th class="num">Modal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plan->lines as $line)
                        <tr>
                            <td>
                                {{ $line->formula?->name ?? '—' }}
                                @if ($line->notes)
                                    <div class="text-xs text-gray-500">{{ $line->notes }}</div>
                                @endif
                            </td>
                            <td class="num">{{ $num($line->target_qty) }} {{ $line->formula?->output_unit }}</td>
                            <td class="num">{{ $num($line->batchCount()) }}x</td>
                            <td class="num">
                                {{ $num($line->actualOutput()) }}
                                @if ($line->actualOutput() > (float) $line->target_qty)
                                    <span class="text-xs text-gray-500">(+{{ $num($line->actualOutput() - (float) $line->target_qty) }} sisa resep)</span>
                                @endif
                            </td>
                            <td class="num">{{ $rp($line->hpp()) }}</td>
                            <td class="num">{{ $rp($line->productionCost()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-gray-500">Rencana ini belum diisi formula.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">Total modal produksi</td>
                        <td class="num">{{ $rp($plan->productionCost()) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>

    {{-- daftar belanja --}}
    <x-filament::section>
        <x-slot name="heading">Yang Harus Dibeli</x-slot>
        <x-slot name="description">
            Kekurangan diubah kembali ke satuan beli dan dibulatkan ke atas —
            tidak ada toko yang menjual pipa per milimeter. Kelebihannya tetap jadi stok.
        </x-slot>

        @if ($belanja->isEmpty())
            <div class="py-6 text-center text-sm text-success-600">
                Stok bahan sudah cukup untuk seluruh rencana ini. Tidak perlu belanja.
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="bl-table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th class="num">Dibutuhkan</th>
                            <th class="num">Stok</th>
                            <th class="num">Kurang</th>
                            <th class="num">Beli</th>
                            <th class="num">Harga Satuan</th>
                            <th class="num">Biaya</th>
                            <th class="num">Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($belanja as $r)
                            <tr>
                                <td>
                                    {{ $r['material']->name }}
                                    <div class="text-xs text-gray-500">
                                        {{ $r['material']->sku }} · {{ implode(', ', $r['formula']) }}
                                    </div>
                                </td>
                                <td class="num">{{ $r['butuh_label'] }}</td>
                                <td class="num">{{ $r['tersedia_label'] }}</td>
                                <td class="num text-danger-600">{{ $r['kurang_label'] }}</td>
                                <td class="num font-bold">{{ $r['beli_label'] }}</td>
                                <td class="num">{{ $rp($r['harga_satuan']) }}</td>
                                <td class="num">{{ $rp($r['biaya']) }}</td>
                                <td class="num text-xs text-gray-500">{{ $r['material']->formatBase($r['sisa']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Total belanja</td>
                            <td class="num">{{ $rp($plan->shoppingCost()) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- bahan yang sudah cukup, disembunyikan agar daftar belanja tetap ringkas --}}
    @if ($cukup->isNotEmpty())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Bahan yang Stoknya Sudah Cukup ({{ $cukup->count() }})</x-slot>

            <div style="overflow-x:auto">
                <table class="bl-table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th class="num">Dibutuhkan</th>
                            <th class="num">Stok</th>
                            <th class="num">Sisa Setelah Dipakai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cukup as $r)
                            <tr>
                                <td>{{ $r['material']->name }}</td>
                                <td class="num">{{ $r['butuh_label'] }}</td>
                                <td class="num">{{ $r['tersedia_label'] }}</td>
                                <td class="num">{{ $r['material']->formatBase($r['tersedia'] - $r['butuh']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($plan->notes)
        <x-filament::section>
            <x-slot name="heading">Catatan</x-slot>
            <div class="whitespace-pre-line text-sm">{{ $plan->notes }}</div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
