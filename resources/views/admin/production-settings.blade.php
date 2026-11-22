@extends('layouts.admin', ['title' => 'Pengaturan Produksi', 'subtitle' => 'Angka dasar perhitungan HPP di semua formula'])

@section('content')
    <form method="POST" action="{{ route('admin.production-settings.update') }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')
        <section class="card grid gap-4 p-6 sm:grid-cols-2">
            <x-admin.field name="tarif_listrik" label="Tarif listrik per kWh (Rp)" type="number" step="any" :value="$values['tarif_listrik']" required
                hint="Dipakai menghitung biaya listrik mesin per jam." />
            <x-admin.field name="kerf_mm" label="Lebar mata potong / kerf (mm)" type="number" step="any" :value="$values['kerf_mm']" required
                hint="Serbuk yang hilang tiap memotong plat; memengaruhi berapa potongan yang muat per lembar." />
            <x-admin.field name="target_produksi_bulanan" label="Target produksi per bulan (unit)" type="number" step="any" :value="$values['target_produksi_bulanan']" required
                :hint="'Pembagi overhead bulanan. Beban overhead per unit saat ini: '.rupiah($overheadPerUnit).'.'" />
            <x-admin.field name="pembulatan_harga" label="Pembulatan harga jual (Rp)" type="number" step="any" :value="$values['pembulatan_harga']" required
                hint="Harga per tingkatan dibulatkan ke atas, mis. ke ribuan terdekat." />
        </section>
        <div class="flex justify-end"><button class="btn btn-primary">Simpan</button></div>
    </form>
@endsection
