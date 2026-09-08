@php
    /** @var \App\Models\Formula $formula */
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');

    $b = $formula->breakdown();
    $grup = $formula->costByGroup();
    $penolong = $formula->consumableCost();
    $bahanUtama = $b['bahan'] - $penolong;
    $bop = $penolong + $b['mesin'] + $b['overhead'];
    $out = max((float) $formula->output_qty, 0.0001);

    $menitJam = function ($menit) {
        $j = floor($menit / 60);
        return $j > 0 ? sprintf('%d jam %d menit', $j, $menit - $j * 60) : sprintf('%d menit', $menit);
    };
@endphp

<div class="kartu-hpp">
    <style>
        .kartu-hpp { font-size: .875rem; }
        .kartu-hpp table { width: 100%; border-collapse: collapse; }
        .kartu-hpp th, .kartu-hpp td { padding: .45rem .6rem; border-bottom: 1px solid rgb(228 228 231); }
        .kartu-hpp th { text-align: left; font-weight: 600; }
        .kartu-hpp .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .kartu-hpp .pos { font-weight: 600; background: rgb(244 244 245); }
        .kartu-hpp .sub td { padding-left: 1.75rem; color: rgb(82 82 91); }
        .kartu-hpp .jumlah td { font-weight: 700; border-top: 2px solid rgb(161 161 170); }
        .kartu-hpp .scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .dark .kartu-hpp th, .dark .kartu-hpp td { border-color: rgb(63 63 70); }
        .dark .kartu-hpp .pos { background: rgb(39 39 42); }
        .dark .kartu-hpp .sub td { color: rgb(161 161 170); }
        .kartu-hpp .ringkas {
            display: grid; gap: .75rem; margin-bottom: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        @media (min-width: 768px) { .kartu-hpp .ringkas { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .kartu-hpp .kotak {
            border: 1px solid rgb(228 228 231); border-radius: .5rem; padding: .6rem .75rem;
        }
        .dark .kartu-hpp .kotak { border-color: rgb(63 63 70); }
        .kartu-hpp .kotak .label { font-size: .75rem; color: rgb(113 113 122); }
        .kartu-hpp .kotak .nilai { font-size: 1.05rem; font-weight: 700; margin-top: .15rem; }
    </style>

    <div class="ringkas">
        <div class="kotak">
            <div class="label">Hasil per resep</div>
            <div class="nilai">{{ $num($formula->output_qty) }} {{ $formula->output_unit }}</div>
        </div>
        <div class="kotak">
            <div class="label">Waktu kerja</div>
            <div class="nilai">{{ $menitJam($b['menit']) }}</div>
        </div>
        <div class="kotak">
            <div class="label">HPP per {{ $formula->output_unit }}</div>
            <div class="nilai" style="color: rgb(22 163 74)">{{ $rp($b['per_unit']) }}</div>
        </div>
        <div class="kotak">
            <div class="label">Bisa dibuat dari stok</div>
            <div class="nilai">{{ $num($formula->capacity()['unit']) }} {{ $formula->output_unit }}</div>
        </div>
    </div>

    {{-- Susunan pos mengikuti format harga pokok produksi yang lazim dipakai
         UMKM dan diminta bank/koperasi: BBB, BTKL, lalu BOP. --}}
    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>Pos Biaya</th>
                    <th class="num">Per Resep</th>
                    <th class="num">Per {{ $formula->output_unit }}</th>
                    <th class="num">%</th>
                </tr>
            </thead>
            <tbody>
                <tr class="pos">
                    <td>A. Biaya Bahan Baku</td>
                    <td class="num">{{ $rp($bahanUtama) }}</td>
                    <td class="num">{{ $rp($bahanUtama / $out) }}</td>
                    <td class="num">{{ $b['total'] > 0 ? number_format($bahanUtama / $b['total'] * 100, 1, ',', '.') : 0 }}%</td>
                </tr>
                @foreach ($grup as $key => $g)
                    @if (($g['bahan'] ?? 0) > 0 && $key !== 'consumable')
                        <tr class="sub">
                            <td>{{ \App\Models\FormulaMaterial::BOM_GROUPS[$key] ?? $key }}</td>
                            <td class="num">{{ $rp($g['bahan']) }}</td>
                            <td class="num">{{ $rp($g['bahan'] / $out) }}</td>
                            <td class="num"></td>
                        </tr>
                    @endif
                @endforeach

                <tr class="pos">
                    <td>B. Biaya Tenaga Kerja Langsung</td>
                    <td class="num">{{ $rp($b['jasa']) }}</td>
                    <td class="num">{{ $rp($b['jasa'] / $out) }}</td>
                    <td class="num">{{ $b['total'] > 0 ? number_format($b['jasa'] / $b['total'] * 100, 1, ',', '.') : 0 }}%</td>
                </tr>
                @foreach ($grup as $key => $g)
                    @if (($g['jasa'] ?? 0) > 0)
                        <tr class="sub">
                            <td>{{ \App\Models\FormulaMaterial::BOM_GROUPS[$key] ?? $key }}</td>
                            <td class="num">{{ $rp($g['jasa']) }}</td>
                            <td class="num">{{ $rp($g['jasa'] / $out) }}</td>
                            <td class="num"></td>
                        </tr>
                    @endif
                @endforeach

                <tr class="pos">
                    <td>C. Biaya Overhead Pabrik</td>
                    <td class="num">{{ $rp($bop) }}</td>
                    <td class="num">{{ $rp($bop / $out) }}</td>
                    <td class="num">{{ $b['total'] > 0 ? number_format($bop / $b['total'] * 100, 1, ',', '.') : 0 }}%</td>
                </tr>
                <tr class="sub">
                    <td>Bahan penolong (kawat las, gas, amplas, poles)</td>
                    <td class="num">{{ $rp($penolong) }}</td>
                    <td class="num">{{ $rp($penolong / $out) }}</td>
                    <td class="num"></td>
                </tr>
                <tr class="sub">
                    <td>Penyusutan, listrik &amp; perawatan mesin</td>
                    <td class="num">{{ $rp($b['mesin']) }}</td>
                    <td class="num">{{ $rp($b['mesin'] / $out) }}</td>
                    <td class="num"></td>
                </tr>
                <tr class="sub">
                    <td>Overhead tetap (sewa, listrik penerangan, admin)</td>
                    <td class="num">{{ $rp($b['overhead']) }}</td>
                    <td class="num">{{ $rp($b['overhead'] / $out) }}</td>
                    <td class="num"></td>
                </tr>

                <tr class="jumlah">
                    <td>Harga Pokok Produksi (A + B + C)</td>
                    <td class="num">{{ $rp($b['total']) }}</td>
                    <td class="num">{{ $rp($b['per_unit']) }}</td>
                    <td class="num">100%</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($b['sisa_potong'] > 0)
        <p style="margin-top:.75rem; color: rgb(113 113 122); font-size: .8rem">
            Termasuk {{ $rp($b['sisa_potong']) }} sisa potong plat yang tidak terpakai —
            sudah masuk pos bahan baku, bukan biaya tambahan.
        </p>
    @endif

    {{-- harga jual --}}
    @php $daftar = $formula->priceList(); @endphp

    @if ($daftar !== [])
        <h3 style="margin:1.25rem 0 .5rem; font-weight:600">Harga Jual</h3>
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Tingkatan</th>
                        <th class="num">Margin</th>
                        <th class="num">Potongan</th>
                        <th class="num">Harga Jual</th>
                        <th class="num">Laba Bersih</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $h)
                        <tr>
                            <td>{{ $h['tingkat']->name }}</td>
                            <td class="num">{{ $num($h['tingkat']->margin_percent) }}%</td>
                            <td class="num">{{ (float) $h['tingkat']->fee_percent > 0 ? $num($h['tingkat']->fee_percent) . '%' : '—' }}</td>
                            <td class="num" style="font-weight:700">{{ $rp($h['harga']) }}</td>
                            <td class="num" style="color: rgb(22 163 74)">{{ $rp($h['laba']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
