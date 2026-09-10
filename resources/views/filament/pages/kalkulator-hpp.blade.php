@php
    $formula = $this->formula;
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
    $jam = function ($menit) {
        $j = floor($menit / 60);
        return $j > 0 ? sprintf('%d jam %d menit', $j, $menit - $j * 60) : sprintf('%d menit', $menit);
    };
@endphp

<x-filament-panels::page>
    <style>
        .kalk-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .kalk-table th, .kalk-table td { padding: .5rem .75rem; border-bottom: 1px solid rgb(228 228 231); }
        .kalk-table th { text-align: left; font-weight: 600; }
        .kalk-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        .kalk-table tfoot td { font-weight: 600; border-top: 2px solid rgb(161 161 170); border-bottom: none; }
        .kalk-table tfoot tr + tr td { border-top: none; }
        .kalk-table tfoot tr:last-child td { font-weight: 700; }
        .dark .kalk-table th, .dark .kalk-table td { border-color: rgb(63 63 70); }
    </style>

    {{ $this->form }}

    @if (! $formula)
        <x-filament::section>
            <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                Pilih formula dulu untuk melihat HPP dan kapasitas produksinya.
            </div>
        </x-filament::section>
    @else
        @php
            $b = $formula->breakdown();
            $kap = $formula->capacity();
            $sim = $this->simulasi;
            $batch = $this->batch;
            $tot = $this->totalSimulasi;
            $adaKurang = collect($sim)->contains(fn ($r) => ! $r['cukup']);
            $grup = $formula->costByGroup();
        @endphp

        {{-- HPP per unit --}}
        <div class="grid gap-4 md:grid-cols-5">
            @foreach ([
                ['Bahan / unit', $b['bahan_per_unit'], 'text-primary-600'],
                ['Tenaga kerja / unit', $b['jasa_per_unit'], 'text-info-600'],
                ['Mesin / unit', $b['mesin_per_unit'], 'text-purple-600'],
                ['Overhead / unit', $b['overhead_per_unit'], 'text-warning-600'],
                ['HPP per unit', $b['per_unit'], 'text-success-600'],
            ] as [$label, $val, $tone])
                <x-filament::section>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-1 text-xl font-bold {{ $tone }}">{{ $rp($val) }}</div>
                </x-filament::section>
            @endforeach
        </div>

        {{-- daftar harga jual --}}
        <x-filament::section>
            <x-slot name="heading">Daftar Harga Jual</x-slot>
            <x-slot name="description">
                Harga dihitung HPP ÷ (1 − margin − potongan), lalu dibulatkan ke atas.
                Margin di sini berarti bagian dari uang yang masuk, bukan modal dikali sekian.
            </x-slot>

            <div style="overflow-x:auto">
                <table class="kalk-table">
                    <thead>
                        <tr>
                            <th>Tingkatan</th>
                            <th class="num">Margin</th>
                            <th class="num">Potongan</th>
                            <th class="num">Harga Jual</th>
                            <th class="num">Diterima</th>
                            <th class="num">Laba Bersih</th>
                            <th class="num">Margin Nyata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($formula->priceList() as $h)
                            <tr>
                                <td>
                                    {{ $h['tingkat']->name }}
                                    @if ($h['tingkat']->notes)
                                        <div class="text-xs text-gray-500">{{ $h['tingkat']->notes }}</div>
                                    @endif
                                </td>
                                <td class="num">{{ $num($h['tingkat']->margin_percent) }}%</td>
                                <td class="num">{{ (float) $h['tingkat']->fee_percent > 0 ? $num($h['tingkat']->fee_percent) . '%' : '—' }}</td>
                                <td class="num font-bold">{{ $rp($h['harga']) }}</td>
                                <td class="num">{{ $rp($h['diterima']) }}</td>
                                <td class="num text-success-600">{{ $rp($h['laba']) }}</td>
                                <td class="num">{{ number_format($h['margin_nyata'], 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-gray-500">Belum ada tingkatan harga. Isi di Master Produksi → Tingkatan Harga.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Modal per unit (HPP)</td>
                            <td class="num">{{ $rp($b['per_unit']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- rincian per bagian --}}
        <x-filament::section>
            <x-slot name="heading">Rincian per Bagian</x-slot>
            <x-slot name="description">
                Menjawab "silencer menghabiskan berapa". Satu resep menghasilkan
                {{ $num($formula->output_qty) }} {{ $formula->output_unit }}
                dengan waktu kerja {{ $jam($b['menit']) }}.
            </x-slot>

            <div style="overflow-x:auto">
                <table class="kalk-table">
                    <thead>
                        <tr>
                            <th>Bagian</th>
                            <th class="num">Bahan</th>
                            <th class="num">Tenaga Kerja</th>
                            <th class="num">Mesin</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grup as $key => $g)
                            <tr>
                                <td>{{ \App\Models\FormulaMaterial::BOM_GROUPS[$key] ?? $key }}</td>
                                <td class="num">{{ $rp($g['bahan']) }}</td>
                                <td class="num">{{ $rp($g['jasa']) }}</td>
                                <td class="num">{{ $rp($g['mesin']) }}</td>
                                <td class="num">{{ $rp($g['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-gray-500">Formula ini belum diisi.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">Overhead tetap ({{ $rp(\App\Models\OverheadItem::perUnit()) }}/unit)</td>
                            <td class="num">{{ $rp($b['overhead']) }}</td>
                        </tr>
                        <tr>
                            <td>Total satu resep</td>
                            <td class="num">{{ $rp($b['bahan']) }}</td>
                            <td class="num">{{ $rp($b['jasa']) }}</td>
                            <td class="num">{{ $rp($b['mesin']) }}</td>
                            <td class="num">{{ $rp($b['total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- pola potong plat --}}
        @php
            $nest = $formula->materials->filter(fn ($l) => $l->usesNesting());
        @endphp

        @if ($nest->isNotEmpty())
            <x-filament::section collapsible>
                <x-slot name="heading">Pola Potong Plat</x-slot>
                <x-slot name="description">
                    Yang dibebankan bukan luas potongannya, melainkan jatah lembaran per potongan —
                    sisa lembaran yang tidak terpakai tetap uang yang sudah keluar.
                    Kerf {{ $num(\App\Models\Material::kerf()) }} mm.
                </x-slot>

                <div style="overflow-x:auto">
                    <table class="kalk-table">
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th>Potongan</th>
                                <th class="num">Muat / Lembar</th>
                                <th class="num">Luas Bersih</th>
                                <th class="num">Jatah Lembar</th>
                                <th class="num">Sisa</th>
                                <th class="num">Rugi Sisa Potong</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($nest as $l)
                                @php $n = $l->nesting(); @endphp
                                <tr>
                                    <td>{{ $l->material->name }}</td>
                                    <td>{{ $l->inputLabel() }}</td>
                                    <td class="num">
                                        {{ $n['muat'] }}
                                        <span class="text-xs text-gray-500">
                                            ({{ $n['kolom'] }}&times;{{ $n['baris'] }}{{ $n['diputar'] ? ', diputar' : '' }})
                                        </span>
                                    </td>
                                    <td class="num">{{ $l->material->formatBase($l->netQty()) }}</td>
                                    <td class="num">{{ $l->material->formatBase($l->effectiveQty()) }}</td>
                                    <td class="num">{{ number_format($n['sisa_persen'], 1, ',', '.') }}%</td>
                                    <td class="num text-warning-600">{{ $rp($l->nestingWasteCost()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">Total rugi sisa potong (sudah termasuk di biaya bahan)</td>
                                <td class="num">{{ $rp($b['sisa_potong']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- kapasitas dari stok sekarang --}}
        <x-filament::section>
            <x-slot name="heading">Kapasitas dari Stok Sekarang</x-slot>
            <x-slot name="description">
                Berapa banyak yang masih bisa dibuat tanpa belanja bahan lagi.
            </x-slot>

            <div class="mb-4 flex flex-wrap items-baseline gap-3">
                <span class="text-3xl font-bold {{ $kap['unit'] > 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $num($kap['unit']) }} {{ $formula->output_unit }}
                </span>
                @if ($kap['pembatas'])
                    <x-filament::badge color="warning">dibatasi {{ $kap['pembatas'] }}</x-filament::badge>
                @endif
            </div>

            <div style="overflow-x:auto">
                <table class="kalk-table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th class="num">Butuh / resep</th>
                            <th class="num">Stok</th>
                            <th class="num">Cukup untuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kap['rincian'] as $r)
                            <tr>
                                <td>{{ $r['bahan'] }}</td>
                                <td class="num">{{ $r['butuh_label'] }}</td>
                                <td class="num">{{ $r['tersedia_label'] }}</td>
                                <td class="num">{{ $num($r['cukup_untuk_resep']) }} resep</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-gray-500">Formula ini belum punya baris bahan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- simulasi target --}}
        <x-filament::section>
            <x-slot name="heading">Simulasi: {{ $num($this->data['target_unit'] ?? 0) }} {{ $formula->output_unit }}</x-slot>
            <x-slot name="description">
                Perlu {{ $num($batch) }}x menjalankan resep. Baris merah berarti stok kurang.
            </x-slot>

            <div style="overflow-x:auto">
                <table class="kalk-table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th class="num">Dibutuhkan</th>
                            <th class="num">Stok</th>
                            <th class="num">Kurang</th>
                            <th class="num">Perlu beli</th>
                            <th class="num">Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sim as $r)
                            <tr @class(['text-danger-600' => ! $r['cukup']])>
                                <td>{{ $r['bahan'] }}</td>
                                <td class="num">{{ $r['butuh_label'] }}</td>
                                <td class="num">{{ $r['tersedia_label'] }}</td>
                                <td class="num">{{ $r['kurang_label'] ?? '—' }}</td>
                                <td class="num">{{ $r['beli_label'] ?? '—' }}</td>
                                <td class="num">{{ $rp($r['biaya']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">Bahan</td>
                            <td class="num">{{ $rp($tot['bahan']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="5">Tenaga kerja ({{ $jam($b['menit'] * $batch) }})</td>
                            <td class="num">{{ $rp($tot['jasa']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="5">Mesin</td>
                            <td class="num">{{ $rp($tot['mesin']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="5">Overhead</td>
                            <td class="num">{{ $rp($tot['overhead']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="5">Total modal</td>
                            <td class="num">{{ $rp($tot['total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($adaKurang)
                <div class="mt-3 text-sm text-danger-600">
                    Stok belum cukup untuk target ini — lengkapi bahan yang kurang dulu.
                </div>
            @endif
        </x-filament::section>

        {{-- Ke mana bahan yang dibeli itu pergi. Tiga kolomnya menjumlah tepat
             menjadi "Beli", jadi angkanya bisa diperiksa sendiri. --}}
        @php $sisa = $this->sisa; @endphp

        <x-filament::section>
            <x-slot name="heading">Sisa &amp; Sampah</x-slot>
            <x-slot name="description">
                Bahan yang dibeli selalu satuan utuh. Yang menempel di produk, yang hilang saat dikerjakan,
                dan yang tersisa dijumlah tepat sama dengan yang dibeli.
            </x-slot>

            <div style="overflow-x:auto">
                <table class="kalk-table">
                    <thead>
                        <tr>
                            <th>Bahan</th>
                            <th class="num">Beli</th>
                            <th class="num">Menempel di produk</th>
                            <th class="num">Susut</th>
                            <th class="num">Sisa</th>
                            <th>Sisa itu</th>
                            <th class="num">Nilai terbuang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sisa['baris'] as $r)
                            <tr>
                                <td>
                                    {{ $r['material']->name }}
                                    @if ($r['potong'])
                                        <div class="text-xs text-gray-500">{{ $r['potong'] }}</div>
                                    @endif
                                </td>
                                <td class="num">
                                    {{ $r['beli_label'] }}
                                    <div class="text-xs text-gray-500">{{ $r['dibeli_label'] }}</div>
                                </td>
                                <td class="num">{{ $r['bersih_label'] }}</td>
                                <td class="num text-danger-600">{{ $r['susut'] > 0 ? $r['susut_label'] : '—' }}</td>
                                <td class="num">{{ $r['sisa'] > 0 ? $r['sisa_label'] : '—' }}</td>
                                <td>
                                    @if ($r['sisa'] <= 0)
                                        <span class="text-xs text-gray-500">habis terpakai</span>
                                    @elseif ($r['sisa_berguna'])
                                        <span class="text-xs text-success-600">kembali jadi stok</span>
                                    @else
                                        <span class="text-xs text-danger-600">terlalu kecil — dibuang</span>
                                    @endif
                                </td>
                                <td class="num">
                                    {{ $rp($r['rp_susut'] + ($r['sisa_berguna'] ? 0 : $r['rp_sisa'])) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-gray-500">Formula ini belum berisi bahan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">Susut saat dikerjakan</td>
                            <td class="num">{{ $rp($sisa['rp_susut']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="6">Sisa yang terlalu kecil untuk dipakai lagi</td>
                            <td class="num">{{ $rp($sisa['rp_sisa_terbuang']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="6">Total terbuang</td>
                            <td class="num text-danger-600">{{ $rp($sisa['rp_sampah']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="6">Sisa yang kembali jadi stok</td>
                            <td class="num text-success-600">{{ $rp($sisa['rp_sisa_berguna']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-3 text-xs text-gray-500">
                Batas "terlalu kecil" diatur per bahan lewat kolom <b>Sisa terkecil yang masih terpakai</b>
                di Produksi &rarr; Stok Bahan. Selama masih 0, semua sisa dianggap kembali jadi stok.
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
