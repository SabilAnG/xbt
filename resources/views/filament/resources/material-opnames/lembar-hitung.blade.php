@php
    /** @var \App\Models\MaterialOpname $opname */
    $opname = $this->record;
    $kelompok = $this->kelompok();
    $sistem = $this->tampilkanSistem;
    $kosong = $this::BARIS_KOSONG;
    $total = $this->jumlahBaris();

    $kolom = $sistem ? 10 : 9;
    $nomor = 0;
@endphp

<x-filament-panels::page>
    @include('filament.cetak.gaya')

    <style>
        /* Bahan butuh kolom lebih banyak daripada aset, jadi kertasnya melintang. */
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
        }

        .lembar .konversi { font-size: 9px; color: #52525b; white-space: nowrap; }
        .lembar .satuan-kecil { font-size: 8.5px; color: #71717a; }
        .lembar .mati { background: repeating-linear-gradient(135deg, #fff, #fff 3px, #ebebeb 3px, #ebebeb 5px); }
    </style>

    <div class="lh-layar">
        Bahan punya dua satuan, jadi lembar ini menyediakan dua kolom tulis:
        <b>Utuh</b> dalam satuan beli (batang, lembar, kg) dan <b>Sisa</b> dalam satuan dasar (mm, mm², gram, ml).
        Kolom <b>Total</b> diisi dengan hasil <i>utuh × konversi + sisa</i> — angka itulah yang dimasukkan ke
        kolom Hitung Fisik pada form opname. Dicetak melintang (A4 landscape).
        @if ($sistem)
            Stok sistem sedang <b>ditampilkan</b>.
        @else
            Stok sistem sedang <b>disembunyikan</b> agar hasil hitungan tidak sekadar menyalin angka sistem.
        @endif
    </div>

    <div class="lembar">
        @include('filament.cetak.kop', [
            'judul' => 'Lembar Hitung Fisik Bahan',
            'sub' => 'Stok Opname — Bahan Baku Produksi',
            {{-- Tanpa sesi, nilainya sengaja kosong: kop tercetak bergaris
                 titik-titik supaya nomor dan tanggalnya ditulis tangan. --}}
            'meta' => [
                'No. Opname' => $opname?->opname_number,
                'Tanggal' => $opname?->opname_date?->translatedFormat('d F Y'),
                'Gudang' => $opname?->warehouse?->name ?: 'Semua gudang',
                'Petugas' => $opname?->counted_by,
                'Mulai hitung' => null,
                'Selesai' => null,
            ],
        ])

        <div class="petunjuk">
            <b>Cara isi:</b> hitung bahan per rak. Tulis jumlah potongan/lembar/karung yang masih utuh di kolom
            <b>Utuh</b>, lalu sisa potongannya di kolom <b>Sisa</b> memakai satuan dasar yang tertera.
            Kolom <b>Total</b> = utuh × angka konversi + sisa. Bahan yang habis tulis <b>0</b> — jangan dikosongkan.
            Bahan yang ketemu di rak tapi belum terdaftar ditulis di baris kosong tiap rak, sebutkan raknya di Catatan.
            @if ($sistem)
                Kolom <b>Stok Sistem</b> dibekukan saat rak dipilih di sesi opname; selisih = fisik − sistem.
            @else
                Selisih dihitung otomatis oleh sistem saat hasilnya dimasukkan.
            @endif
            Hasil akhir dimasukkan lewat <b>Produksi → Stok Opname Bahan → Ubah</b>, lalu ditekan <b>Bukukan</b>.
        </div>

        <table>
            <thead>
                <tr>
                    <th class="centang">✓</th>
                    <th style="width:26px">No</th>
                    <th style="width:80px">SKU</th>
                    <th>Nama Bahan</th>
                    <th style="width:104px">Konversi</th>
                    @if ($sistem)
                        <th class="num" style="width:78px">Stok Sistem</th>
                    @endif
                    <th class="num tulis" style="width:72px">Utuh</th>
                    <th class="num tulis" style="width:72px">Sisa</th>
                    <th class="num tulis" style="width:86px">Total (satuan dasar)</th>
                    <th class="kotak" style="width:130px">Catatan</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($kelompok as $rak => $baris)
                    <tr class="grup">
                        <td colspan="{{ $kolom }}">
                            Rak: {{ $rak }}
                            <span class="hitung">{{ $baris->count() }} baris</span>
                        </td>
                    </tr>

                    @foreach ($baris as $bahan)
                        <tr>
                            <td class="centang">☐</td>
                            <td class="num">{{ ++$nomor }}</td>
                            <td class="sku">{{ $bahan['sku'] ?: '—' }}</td>
                            <td class="nama">{{ $bahan['nama'] }}</td>
                            <td class="konversi">{{ $bahan['konversi'] }}</td>
                            @if ($sistem)
                                <td class="num">{{ $bahan['sistem_label'] }}</td>
                            @endif
                            <td class="tulis num">
                                <span class="satuan-kecil">{{ $bahan['satuan_beli'] }}</span>
                            </td>
                            {{-- Bahan hitungan satuan (pcs/set) tidak punya sisa potongan. --}}
                            <td class="{{ $bahan['berdimensi'] ? 'tulis num' : 'mati' }}">
                                @if ($bahan['berdimensi'])
                                    <span class="satuan-kecil">{{ $bahan['satuan_dasar'] }}</span>
                                @endif
                            </td>
                            <td class="tulis num">
                                <span class="satuan-kecil">{{ $bahan['satuan_dasar'] }}</span>
                            </td>
                            <td class="kotak"></td>
                        </tr>
                    @endforeach

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
                            <td class="tulis"></td>
                            <td class="tulis"></td>
                            <td class="kotak"></td>
                        </tr>
                    @endfor
                @empty
                    <tr>
                        <td colspan="{{ $kolom }}" class="tengah" style="height:48px">
                            Belum ada bahan aktif yang bisa dicetak.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($opname?->notes)
            <div class="petunjuk" style="margin-top:9px">
                <b>Catatan sesi:</b> {{ $opname->notes }}
            </div>
        @endif

        @include('filament.cetak.ttd', [
            'petugas' => $opname?->counted_by,
            'kaki' => match (true) {
                ! $opname => 'Lembar kosong dari catatan stok per rak — belum terikat sesi. '.$total.' baris.',
                $this->dariMaster() => 'Diambil dari catatan stok per rak — sesi '.$opname->opname_number.' belum berisi baris. '.$total.' baris.',
                default => 'Baris dari sesi '.$opname->opname_number.'. '.$total.' baris.',
            },
        ])
    </div>
</x-filament-panels::page>
