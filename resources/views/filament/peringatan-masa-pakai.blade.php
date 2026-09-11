{{--
    Pengingat masa pakai di panel partner.

    Tanpa ini partner baru tahu langganannya habis ketika tokonya sudah mati —
    dan yang pertama memberitahunya adalah pembeli yang menemukan halaman hitam.

    Hanya muncul di minggu terakhir. Peringatan yang tampil sepanjang tahun
    berhenti dibaca jauh sebelum ia benar-benar perlu dibaca.
--}}
@php
    $partner = \App\Support\HakPartner::partner();

    // Dihitung per tanggal, bukan per jam. Masa pakai yang habis pukul sepuluh
    // pagi ini tetap "habis hari ini" — bukan "sudah lewat sejak tadi", yang
    // membuat peringatannya hilang justru di hari ia paling perlu dibaca.
    $sisaHari = $partner?->expires_at
        ? now()->startOfDay()->diffInDays($partner->expires_at->startOfDay(), false)
        : null;

    $tampil = $partner
        && $sisaHari !== null
        && $sisaHari >= 0
        && $sisaHari <= 7;
@endphp

@if ($tampil)
    <div class="fi-masa-pakai {{ $sisaHari <= 2 ? 'fi-masa-mendesak' : '' }}">
        <span>
            Masa pakai toko Anda berakhir
            <strong>{{ $partner->expires_at->translatedFormat('d F Y') }}</strong>
            ({{ $sisaHari === 0 ? 'hari ini' : $sisaHari . ' hari lagi' }}).
            Sesudah itu toko dan panel ini terkunci sampai diperpanjang — datanya tetap utuh.
        </span>

        <a href="{{ \App\Support\HakPartner::tautanPerpanjang() }}" target="_blank" rel="noopener">
            Perpanjang
        </a>
    </div>

    <style>
        .fi-masa-pakai {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: #fef3c7;
            color: #78350f;
            font-size: 0.9rem;
        }

        .fi-masa-pakai a {
            flex-shrink: 0;
            padding: 6px 16px;
            border-radius: 999px;
            background: #78350f;
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }

        .fi-masa-mendesak {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .fi-masa-mendesak a { background: #7f1d1d; }
    </style>
@endif
