@php
    /** @var \App\Models\StockOpname $opname */
    $opname = $this->record;
    $kelompok = $this->kelompok();
    $sistem = $this->tampilkanSistem;
    $kosong = $this::BARIS_KOSONG;
    $total = $this->jumlahBaris();

    // Angka gudang jarang berkoma; nolnya dibuang agar "12,00" tidak ramai.
    $num = function ($n) {
        $teks = number_format((float) $n, 2, ',', '.');

        return str_contains($teks, ',') ? rtrim(rtrim($teks, '0'), ',') : $teks;
    };

    $kolom = $sistem ? 9 : 7;
    $nomor = 0;
@endphp

<x-filament-panels::page>
    @include('filament.cetak.gaya')

    <div class="lh-layar">
        Ini tampilan kertasnya. Tekan <b>Cetak</b> di kanan atas — menu dan tombol tidak ikut tercetak.
        Ukuran kertas A4 tegak, satu baris dibuat setinggi tulisan tangan.
        @if ($sistem)
            Stok sistem sedang <b>ditampilkan</b>; sembunyikan bila petugas tidak boleh melihat angka sistem saat menghitung.
        @else
            Stok sistem sedang <b>disembunyikan</b> agar hasil hitungan tidak sekadar menyalin angka sistem.
        @endif
    </div>

    <div class="lembar">
        @include('filament.cetak.kop', [
            'judul' => 'Lembar Hitung Fisik',
            'sub' => 'Stok Opname — Aset / Barang Jadi',
            'meta' => [
                'No. Opname' => $opname->opname_number,
                'Tanggal' => $opname->opname_date?->translatedFormat('d F Y'),
                'Status' => \App\Models\StockOpname::STATUSES[$opname->status] ?? $opname->status,
                'Petugas' => $opname->counted_by,
                'Mulai hitung' => null,
                'Selesai' => null,
            ],
        ])

        <div class="petunjuk">
            <b>Cara isi:</b> hitung barang di rak, tulis jumlahnya di kolom <b>Hitung Fisik</b> sesuai satuan yang tertera,
            lalu centang kotak paling kiri. Barang yang tidak ditemukan tulis <b>0</b> — jangan dikosongkan, karena
            baris kosong dianggap belum dihitung. Barang yang ada di rak tapi belum terdaftar ditulis di baris kosong
            tiap kelompok.
            @if ($sistem)
                Kolom <b>Stok Sistem</b> adalah angka yang dibekukan sistem; selisih = fisik − sistem.
            @else
                Selisih dihitung otomatis oleh sistem saat hasilnya dimasukkan, jadi tidak perlu dihitung di lapangan.
            @endif
            Hasil akhir dimasukkan lewat <b>Aset → Stok Opname → Ubah</b>, lalu ditekan <b>Bukukan</b> agar stok terkoreksi.
        </div>

        <table>
            <thead>
                <tr>
                    <th class="centang">✓</th>
                    <th style="width:26px">No</th>
                    <th style="width:88px">SKU</th>
                    <th>Nama Barang</th>
                    <th style="width:52px">Satuan</th>
                    @if ($sistem)
                        <th class="num" style="width:74px">Stok Sistem</th>
                    @endif
                    <th class="num tulis" style="width:88px">Hitung Fisik</th>
                    @if ($sistem)
                        <th class="num kotak" style="width:74px">Selisih</th>
                    @endif
                    <th class="kotak" style="width:118px">Catatan</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($kelompok as $nama => $baris)
                    <tr class="grup">
                        <td colspan="{{ $kolom }}">
                            {{ $nama }}
                            <span class="hitung">{{ $baris->count() }} baris</span>
                        </td>
                    </tr>

                    @foreach ($baris as $item)
                        <tr>
                            <td class="centang">☐</td>
                            <td class="num">{{ ++$nomor }}</td>
                            <td class="sku">{{ $item['sku'] ?: '—' }}</td>
                            <td class="nama">{{ $item['nama'] }}</td>
                            <td class="tengah">{{ $item['satuan'] }}</td>
                            @if ($sistem)
                                <td class="num">{{ $num($item['sistem']) }}</td>
                            @endif
                            <td class="tulis"></td>
                            @if ($sistem)
                                <td class="kotak"></td>
                            @endif
                            <td class="kotak"></td>
                        </tr>
                    @endforeach

                    {{-- Ruang untuk barang yang ketemu di rak tapi belum ada di daftar. --}}
                    @for ($k = 0; $k < $kosong; $k++)
                        <tr>
                            <td class="centang">☐</td>
                            <td class="num kotak"></td>
                            <td class="kotak"></td>
                            <td class="kotak"></td>
                            <td class="kotak"></td>
                            @if ($sistem)
                                <td class="kotak"></td>
                            @endif
                            <td class="tulis"></td>
                            @if ($sistem)
                                <td class="kotak"></td>
                            @endif
                            <td class="kotak"></td>
                        </tr>
                    @endfor
                @empty
                    <tr>
                        <td colspan="{{ $kolom }}" class="tengah" style="height:48px">
                            Belum ada barang aktif yang bisa dicetak.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($opname->notes)
            <div class="petunjuk" style="margin-top:9px">
                <b>Catatan sesi:</b> {{ $opname->notes }}
            </div>
        @endif

        @include('filament.cetak.ttd', [
            'petugas' => $opname->counted_by,
            'kaki' => $this->dariMaster()
                ? 'Diambil dari seluruh barang aktif — sesi '.$opname->opname_number.' belum berisi baris. '.$total.' barang.'
                : 'Baris dari sesi '.$opname->opname_number.'. '.$total.' barang.',
        ])
    </div>
</x-filament-panels::page>
