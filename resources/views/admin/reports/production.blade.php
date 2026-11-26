@extends('layouts.admin', ['title' => 'Laporan Produksi', 'subtitle' => $from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y')])

@section('actions')
    <button type="button" class="btn" onclick="window.print()">Cetak</button>
@endsection

@section('content')
    <x-admin.filters>
        <x-admin.field name="dari" label="Dari" type="date" :value="$from->toDateString()" />
        <x-admin.field name="sampai" label="Sampai" type="date" :value="$to->toDateString()" />
    </x-admin.filters>

    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Unit diproduksi" :value="qty($summary['units'])" :hint="$summary['count'].' nota dibukukan'" />
        <x-admin.stat label="Total biaya produksi" :value="rupiah($summary['total'])" :hint="'Bahan '.rupiah($summary['material']).' · ongkos '.rupiah($summary['other'])" />
        <x-admin.stat label="HPP rata-rata" :value="rupiah($summary['units'] > 0 ? $summary['total'] / $summary['units'] : 0)" hint="per unit" />
        <x-admin.stat label="Jasa vendor · afkir" :value="rupiah($summary['service'])" :hint="'Afkir/serbuk '.rupiah($summary['waste'])" tone="warning" />
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Per formula</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Formula</th><th class="num">Nota</th><th class="num">Unit</th><th class="num">Total biaya</th><th class="num">HPP rata-rata</th><th class="num">Afkir</th></tr></thead>
                <tbody>
                @forelse ($byFormula as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num font-semibold">{{ qty($row['units']) }}</td>
                        <td class="num">{{ rupiah($row['total']) }}</td>
                        <td class="num">{{ rupiah($row['hpp']) }}</td>
                        <td class="num text-rose-600">{{ rupiah($row['waste']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-muted">Belum ada produksi dibukukan di periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Per penanggung jawab</h2></div>
            <table class="table">
                <thead><tr><th>Pekerja</th><th class="num">Nota</th><th class="num">Unit</th><th class="num">Biaya</th></tr></thead>
                <tbody>
                @forelse ($byWorker as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="num">{{ $row['count'] }}</td><td class="num font-semibold">{{ qty($row['units']) }}</td><td class="num">{{ rupiah($row['total']) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-muted">—</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Jasa vendor</h2></div>
            <table class="table">
                <thead><tr><th>Vendor</th><th class="num">Tagihan</th><th class="num">Total</th><th class="num">Belum dibayar</th></tr></thead>
                <tbody>
                @forelse ($byVendor as $row)
                    <tr>
                        <td>{{ $row['vendor'] }}</td>
                        <td class="num">{{ $row['bills'] }}</td>
                        <td class="num">{{ rupiah($row['total']) }}</td>
                        <td class="num {{ $row['payable'] > 0 ? 'text-rose-600' : 'text-muted' }}">{{ $row['payable'] > 0 ? rupiah($row['payable']) : 'lunas' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-muted">Tidak ada jasa vendor di periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
