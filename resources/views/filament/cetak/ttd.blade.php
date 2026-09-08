{{--
    Blok tanda tangan lembar cetak.

    $petugas — nama yang sudah tercatat di sesi opname, boleh null.
    $kaki    — keterangan kecil di baris paling bawah.
--}}
<div class="ttd">
    <div>
        <div class="peran">Dihitung oleh</div>
        <div class="ruang"></div>
        <div class="garis-ttd">{{ $petugas ?: '(...................................)' }}</div>
        <div class="catatan">Petugas hitung fisik</div>
    </div>
    <div>
        <div class="peran">Diperiksa</div>
        <div class="ruang"></div>
        <div class="garis-ttd">(...................................)</div>
        <div class="catatan">Kepala gudang</div>
    </div>
    <div>
        <div class="peran">Disetujui</div>
        <div class="ruang"></div>
        <div class="garis-ttd">(...................................)</div>
        <div class="catatan">Pemilik / manajer</div>
    </div>
</div>

<div class="kaki">
    <span>{{ $kaki }}</span>
    <span>Dicetak {{ now()->translatedFormat('d/m/Y H:i') }}</span>
</div>
