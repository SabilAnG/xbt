@php
    /** @var \App\Models\Formula $formula */
    $b = $formula->breakdown();
    $groups = $formula->costByGroup();
    $penolong = $formula->consumableCost();
    $bahanUtama = $b['bahan'] - $penolong;
    $bop = $penolong + $b['mesin'] + $b['overhead'];
    $out = max((float) $formula->output_qty, 0.0001);
    $pct = fn ($value) => $b['total'] > 0 ? number_format($value / $b['total'] * 100, 1, ',', '.').'%' : '0%';
    $minutes = $b['menit'];
    $capacity = $formula->capacity();
    $prices = $formula->priceList();
@endphp

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-admin.stat label="Hasil per resep" :value="qty($formula->output_qty).' '.$formula->output_unit" />
    <x-admin.stat label="Waktu kerja" :value="floor($minutes / 60) > 0 ? floor($minutes / 60).' jam '.round($minutes - floor($minutes / 60) * 60).' menit' : round($minutes).' menit'" />
    <x-admin.stat :label="'HPP per '.$formula->output_unit" :value="rupiah($b['per_unit'])" tone="success" />
    <x-admin.stat label="Bisa dibuat dari stok" :value="qty($capacity['unit']).' '.$formula->output_unit" :tone="$capacity['unit'] > 0 ? 'default' : 'danger'"
        :hint="$capacity['pembatas'] ? 'dibatasi '.$capacity['pembatas'] : null" />
</section>

<section class="card overflow-hidden">
    <div class="card-header"><h3 class="card-title">Kartu harga pokok produksi</h3><span class="text-xs text-slate-500">format BBB · BTKL · BOP</span></div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Pos biaya</th><th class="num">Per resep</th><th class="num">Per {{ $formula->output_unit }}</th><th class="num">%</th></tr></thead>
            <tbody>
                <tr class="bg-slate-50 font-semibold"><td>A. Biaya Bahan Baku</td><td class="num">{{ rupiah($bahanUtama) }}</td><td class="num">{{ rupiah($bahanUtama / $out) }}</td><td class="num">{{ $pct($bahanUtama) }}</td></tr>
                @foreach ($groups as $key => $group)
                    @if (($group['bahan'] ?? 0) > 0 && $key !== \App\Models\FormulaMaterial::GRUP_PENOLONG)
                        <tr class="text-slate-600"><td class="pl-8">{{ \App\Models\FormulaMaterial::BOM_GROUPS[$key] ?? $key }}</td><td class="num">{{ rupiah($group['bahan']) }}</td><td class="num">{{ rupiah($group['bahan'] / $out) }}</td><td></td></tr>
                    @endif
                @endforeach

                <tr class="bg-slate-50 font-semibold"><td>B. Biaya Tenaga Kerja Langsung</td><td class="num">{{ rupiah($b['jasa']) }}</td><td class="num">{{ rupiah($b['jasa'] / $out) }}</td><td class="num">{{ $pct($b['jasa']) }}</td></tr>
                @foreach ($groups as $key => $group)
                    @if (($group['jasa'] ?? 0) > 0)
                        <tr class="text-slate-600"><td class="pl-8">{{ \App\Models\FormulaMaterial::BOM_GROUPS[$key] ?? $key }}</td><td class="num">{{ rupiah($group['jasa']) }}</td><td class="num">{{ rupiah($group['jasa'] / $out) }}</td><td></td></tr>
                    @endif
                @endforeach

                <tr class="bg-slate-50 font-semibold"><td>C. Biaya Overhead Pabrik</td><td class="num">{{ rupiah($bop) }}</td><td class="num">{{ rupiah($bop / $out) }}</td><td class="num">{{ $pct($bop) }}</td></tr>
                <tr class="text-slate-600"><td class="pl-8">Bahan penolong (kawat las, gas, amplas, poles)</td><td class="num">{{ rupiah($penolong) }}</td><td class="num">{{ rupiah($penolong / $out) }}</td><td></td></tr>
                <tr class="text-slate-600"><td class="pl-8">Penyusutan, listrik &amp; perawatan mesin</td><td class="num">{{ rupiah($b['mesin']) }}</td><td class="num">{{ rupiah($b['mesin'] / $out) }}</td><td></td></tr>
                <tr class="text-slate-600"><td class="pl-8">Overhead tetap (sewa, listrik penerangan, admin)</td><td class="num">{{ rupiah($b['overhead']) }}</td><td class="num">{{ rupiah($b['overhead'] / $out) }}</td><td></td></tr>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-300 font-bold"><td class="px-4 py-2.5">Harga Pokok Produksi (A + B + C)</td><td class="num px-4">{{ rupiah($b['total']) }}</td><td class="num px-4 text-emerald-700">{{ rupiah($b['per_unit']) }}</td><td class="num px-4">100%</td></tr>
            </tfoot>
        </table>
    </div>
    @if ($b['sisa_potong'] > 0)
        <p class="px-5 py-3 text-xs text-slate-500">Termasuk {{ rupiah($b['sisa_potong']) }} sisa potong plat yang tidak terpakai — sudah masuk pos bahan baku, bukan biaya tambahan.</p>
    @endif
</section>

<section class="grid gap-6 xl:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">Harga jual per tingkatan</h3>
            <a href="{{ route('admin.masters.index', 'tingkatan-harga') }}" class="link text-xs">Atur tingkatan</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tingkatan</th><th class="num">Margin</th><th class="num">Potongan</th><th class="num">Harga jual</th><th class="num">Laba bersih</th></tr></thead>
                <tbody>
                @forelse ($prices as $price)
                    <tr>
                        <td>{{ $price['tingkat']->name }}</td>
                        <td class="num">{{ qty($price['tingkat']->margin_percent, 2) }}%</td>
                        <td class="num">{{ (float) $price['tingkat']->fee_percent > 0 ? qty($price['tingkat']->fee_percent, 2).'%' : '—' }}</td>
                        <td class="num font-semibold">{{ rupiah($price['harga']) }}</td>
                        <td class="num text-emerald-700">{{ rupiah($price['laba']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Belum ada tingkatan harga.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h3 class="card-title">Kapasitas dari stok bahan</h3><span class="text-xs text-slate-500">per resep</span></div>
        <div class="max-h-96 overflow-auto">
            <table class="table">
                <thead><tr><th>Bahan</th><th class="num">Butuh</th><th class="num">Tersedia</th><th class="num">Cukup untuk</th></tr></thead>
                <tbody>
                @forelse (collect($capacity['rincian'])->sortBy('cukup_untuk_resep') as $row)
                    <tr>
                        <td @class(['font-semibold text-rose-600' => $row['bahan'] === $capacity['pembatas']])>{{ $row['bahan'] }}</td>
                        <td class="num">{{ $row['butuh_label'] }}</td>
                        <td class="num">{{ $row['tersedia_label'] }}</td>
                        <td class="num">{{ qty(max($row['cukup_untuk_resep'], 0)) }}×</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500">Belum ada bahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
