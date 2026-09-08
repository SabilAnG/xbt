{{--
    Kop lembar cetak.

    $judul  — judul besar di kanan atas
    $sub    — keterangan kecil di bawah judul
    $meta   — array label => nilai. Nilai null dicetak sebagai garis titik-titik
              supaya bisa ditulis tangan di lapangan (mis. petugas belum diisi).
--}}
@php
    $namaPt = \App\Models\Setting::get('site.name', config('app.name'));
    $alamatPt = \App\Models\Setting::get('site.address');
@endphp

<div class="kop">
    <div class="kop-atas">
        <div>
            <div class="kop-pt">{{ $namaPt }}</div>
            @if ($alamatPt)
                <div class="kop-sub">{{ $alamatPt }}</div>
            @endif
        </div>
        <div class="kop-judul">
            {{ $judul }}
            @isset($sub)
                <small>{{ $sub }}</small>
            @endisset
        </div>
    </div>

    <div class="kop-meta">
        @foreach ($meta as $label => $nilai)
            <div>
                <span class="lbl">{{ $label }}</span>
                <span>:</span>
                @if (filled($nilai))
                    <span class="isi">{{ $nilai }}</span>
                @else
                    <span class="garis"></span>
                @endif
            </div>
        @endforeach
    </div>
</div>
