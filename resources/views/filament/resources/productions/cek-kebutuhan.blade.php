@php
    /** @var \App\Models\Formula|null $formula */
    /** @var float $target */
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
@endphp

<div class="cek-kebutuhan">
    <style>
        .cek-kebutuhan { font-size: .875rem; }
        .cek-kebutuhan table { width: 100%; border-collapse: collapse; }
        .cek-kebutuhan th, .cek-kebutuhan td { padding: .4rem .6rem; border-bottom: 1px solid rgb(228 228 231); }
        .cek-kebutuhan th { text-align: left; font-weight: 600; white-space: nowrap; }
        .cek-kebutuhan .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .cek-kebutuhan tfoot td { font-weight: 700; border-top: 2px solid rgb(161 161 170); border-bottom: none; }
        .cek-kebutuhan .scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .cek-kebutuhan .kurang td { color: rgb(185 28 28); }
        .dark .cek-kebutuhan th, .dark .cek-kebutuhan td { border-color: rgb(63 63 70); }
        .dark .cek-kebutuhan .kurang td { color: rgb(248 113 113); }
        .cek-kebutuhan .kabar {
            border-radius: .5rem; padding: .6rem .8rem; margin-bottom: .75rem;
            border: 1px solid transparent;
        }
        .cek-kebutuhan .kabar.cukup { background: rgb(240 253 244); border-color: rgb(187 247 208); color: rgb(21 128 61); }
        .cek-kebutuhan .kabar.kurang { background: rgb(254 242 242); border-color: rgb(254 202 202); color: rgb(185 28 28); }
        .dark .cek-kebutuhan .kabar.cukup { background: rgb(5 46 22); border-color: rgb(22 101 52); color: rgb(134 239 172); }
        .dark .cek-kebutuhan .kabar.kurang { background: rgb(69 10 10); border-color: rgb(153 27 27); color: rgb(252 165 165); }
    </style>

    @if (! $formula)
        <p class="text-gray-500">Pilih formula dulu, lalu isi mau buat berapa biji.</p>
    @elseif ($target <= 0)
        <p class="text-gray-500">Isi jumlah yang mau dibuat untuk melihat kebutuhan bahannya.</p>
    @else
        @php $cek = $formula->requirementFor($target); @endphp

        @if ($cek['cukup'])
            <div class="kabar cukup">
                <strong>Stok bahan cukup.</strong>
                {{ $num($cek['batch']) }}x jalan resep menghasilkan {{ $num($cek['hasil']) }} {{ $formula->output_unit }},
                menghabiskan bahan senilai {{ $rp($cek['biaya_bahan']) }}.
            </div>
        @else
            <div class="kabar kurang">
                <strong>{{ count($cek['kurang']) }} bahan kurang — harus beli dulu.</strong>
                Perkiraan belanja {{ $rp($cek['biaya_belanja']) }}.
                Nota masih boleh disimpan, tapi tidak bisa dibukukan sebelum bahannya ada.
                <div style="margin-top:.35rem">
                    @foreach ($cek['kurang'] as $k)
                        {{ $k['material']->name }} — kurang {{ $k['kurang_label'] }}, beli {{ $k['beli_label'] }}@if (! $loop->last); @endif
                    @endforeach
                </div>
            </div>
        @endif

        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Bahan</th>
                        <th>Bagian</th>
                        <th class="num">Butuh</th>
                        <th class="num">Stok</th>
                        <th class="num">Kurang</th>
                        <th class="num">Perlu beli</th>
                        <th class="num">Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cek['baris'] as $r)
                        <tr @class(['kurang' => ! $r['cukup']])>
                            <td>{{ $r['material']->name }}</td>
                            <td>{{ $r['bagian'] }}</td>
                            <td class="num">{{ $r['butuh_label'] }}</td>
                            <td class="num">{{ $r['tersedia_label'] }}</td>
                            <td class="num">{{ $r['kurang_label'] ?? '—' }}</td>
                            <td class="num">{{ $r['beli_label'] ?? '—' }}</td>
                            <td class="num">{{ $rp($r['biaya']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-gray-500">Formula ini belum punya baris bahan.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">Total bahan untuk {{ $num($cek['hasil']) }} {{ $formula->output_unit }}</td>
                        <td class="num">{{ $rp($cek['biaya_bahan']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p style="margin-top:.6rem;color:rgb(113 113 122);font-size:.8rem">
            Tenaga kerja {{ $rp($formula->serviceCost() * $cek['batch']) }} ·
            mesin {{ $rp($formula->machineCost() * $cek['batch']) }} ·
            overhead {{ $rp($formula->overheadCost() * $cek['batch']) }} ·
            <strong>modal total {{ $rp($formula->totalCost() * $cek['batch']) }}</strong>
            (HPP {{ $rp($formula->hppPerUnit()) }} per {{ $formula->output_unit }})
        </p>
    @endif
</div>
