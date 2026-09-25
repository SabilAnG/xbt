@php
    $rp = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');
    $puncak = max(1, collect($tren)->max('nilai'));
@endphp

<x-panel.layout judul="Beranda" :keterangan="'Ringkasan '.now()->translatedFormat('F Y').'. Hanya nota yang sudah dibukukan yang dihitung.'">

    {{-- Kartu angka --}}
    <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Nilai Stok Barang', $rp($nilaiStok), $jumlahBarang.' barang aktif', 'ungu'],
            ['Nilai Stok Bahan', $rp($nilaiBahan), $jumlahBahan.' bahan aktif', 'ungu'],
            ['Saldo Kas', $rp($kas), $jumlahDompet.' dompet aktif', $kas < 0 ? 'merah' : 'mint'],
            ['Laba Bersih Bulan Ini', $rp($labaBersih), 'Setelah pengeluaran '.$rp($pengeluaran), $labaBersih < 0 ? 'merah' : 'mint'],
        ] as [$label, $nilai, $ket, $warna])
            <section class="kartu p-5">
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">{{ $label }}</p>
                <p @class([
                    'mt-1 text-2xl font-bold tracking-tight',
                    'text-ungu-600' => $warna === 'ungu',
                    'text-emerald-600' => $warna === 'mint',
                    'text-merah-500' => $warna === 'merah',
                ])>{{ $nilai }}</p>
                <p class="mt-1 text-[0.75rem] text-slate-400">{{ $ket }}</p>
            </section>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Grafik penjualan --}}
        <section class="kartu p-5 lg:col-span-2">
            <div class="mb-4 flex items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-800">Penjualan 7 hari terakhir</h2>
                <p class="text-[0.8125rem] text-slate-500">Bulan ini {{ $rp($penjualan) }} · laba kotor {{ $rp($labaKotor) }}</p>
            </div>

            {{-- Batang CSS, bukan pustaka grafik: tujuh angka tidak sebanding
                 dengan satu dependensi baru yang harus ikut dibangun. --}}
            <div class="flex h-40 items-end gap-2">
                @foreach ($tren as $hari)
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-1.5">
                        <span class="text-[0.65rem] text-slate-400">
                            {{ $hari['nilai'] > 0 ? number_format($hari['nilai'] / 1000, 0, ',', '.').'rb' : '' }}
                        </span>
                        <div class="w-full rounded-t-lg bg-gradient-to-t from-ungu-600 to-ungu-400"
                             style="height: {{ max(2, round($hari['nilai'] / $puncak * 100)) }}%"></div>
                        <span class="text-[0.7rem] text-slate-500">{{ $hari['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Dokumen draft --}}
        <section class="kartu p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Nota draft</h2>
            <p class="mb-3 text-[0.8125rem] text-slate-500">Belum dibukukan — stok dan kas belum bergerak.</p>

            <div class="space-y-1.5">
                @foreach ($draf as $label => $jumlah)
                    <div class="flex items-center justify-between rounded-xl bg-lantai px-3 py-2">
                        <span class="text-[0.8125rem] text-slate-600">{{ $label }}</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[0.75rem] font-semibold',
                            'bg-kuning-400/25 text-amber-700' => $jumlah > 0,
                            'text-slate-400' => $jumlah === 0,
                        ])>{{ $jumlah }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Stok menipis --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @foreach ([
            ['Barang menipis', $menipis, 'panel.stok-barang.index'],
            ['Bahan menipis', $bahanMenipis, 'panel.barang-produksi.index'],
        ] as [$judul, $daftar, $rute])
            <section class="kartu p-5">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">{{ $judul }}</h2>
                    <a href="{{ route($rute) }}" class="text-[0.8125rem] font-medium text-ungu-600">lihat semua</a>
                </div>

                <div class="space-y-1.5">
                    @forelse ($daftar as $b)
                        <div class="flex items-center gap-2 rounded-xl bg-lantai px-3 py-2">
                            <span class="min-w-0 flex-1 truncate text-[0.8125rem] text-slate-700">{{ $b->name }}</span>
                            <span class="shrink-0 rounded-full bg-merah-500/10 px-2 py-0.5 text-[0.75rem] font-medium text-merah-500">
                                {{ method_exists($b, 'displayStock') ? $b->displayStock() : rtrim(rtrim(number_format((float) $b->stock, 2, ',', '.'), '0'), ',').' '.$b->unit }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-[0.8125rem] text-slate-400">Tidak ada yang menipis.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-panel.layout>
