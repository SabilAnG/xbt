{{--
    Gaya bersama lembar cetak opname.

    Dipakai dua lembar (aset & bahan) supaya keduanya keluar dari printer
    dengan bentuk yang sama. Semua warna dikunci hitam-putih: lembar ini
    kertas, bukan layar, jadi mode gelap panel tidak ikut terbawa.
--}}
<style>
    .lembar {
        background: #fff;
        color: #111;
        padding: 6mm;
        border: 1px solid #d4d4d8;
        border-radius: .5rem;
        font-family: ui-sans-serif, system-ui, "Segoe UI", Arial, sans-serif;
    }

    /* --- kop --- */
    .lembar .kop { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
    .lembar .kop-atas { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .lembar .kop-pt { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
    .lembar .kop-sub { font-size: 10px; color: #52525b; margin-top: 1px; }
    .lembar .kop-judul { font-size: 15px; font-weight: 800; text-align: right; text-transform: uppercase; }
    .lembar .kop-judul small { display: block; font-size: 10px; font-weight: 500; text-transform: none; color: #52525b; }

    .lembar .kop-meta {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 2px 16px; margin-top: 7px; font-size: 10.5px;
    }
    .lembar .kop-meta div { display: flex; gap: 5px; }
    .lembar .kop-meta .lbl { color: #52525b; min-width: 74px; }
    .lembar .kop-meta .isi { font-weight: 600; }
    /* Petugas yang belum diisi di sistem dituliskan tangan di lapangan. */
    .lembar .kop-meta .garis { flex: 1; border-bottom: 1px dotted #71717a; min-width: 70px; }

    .lembar .petunjuk {
        margin: 7px 0 9px; padding: 5px 8px; font-size: 10px; line-height: 1.5;
        border: 1px solid #d4d4d8; border-left: 3px solid #111; background: #fafafa;
    }
    .lembar .petunjuk b { font-weight: 700; }

    /* --- tabel --- */
    .lembar table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
    .lembar th, .lembar td { border: 1px solid #a1a1aa; padding: 3px 5px; vertical-align: middle; }
    .lembar thead th {
        background: #e4e4e7; font-weight: 700; text-align: left;
        font-size: 9.5px; text-transform: uppercase; letter-spacing: .02em;
    }
    .lembar .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .lembar .tengah { text-align: center; }
    .lembar tbody td { height: 26px; }
    .lembar .nama { font-weight: 600; }
    .lembar .sku { font-size: 9px; color: #52525b; font-family: ui-monospace, "Cascadia Mono", Consolas, monospace; }

    /* Kolom yang diisi tangan: sedikit lebih terang dan bergaris tebal di kiri
       agar terlihat sebagai tempat menulis, bukan angka yang sudah jadi. */
    .lembar .tulis { background: #fcfcfc; border-left: 2px solid #111; }
    .lembar .kotak { background: #fcfcfc; }
    .lembar .centang { width: 22px; text-align: center; font-size: 12px; color: #71717a; }

    .lembar .grup td {
        background: #f4f4f5; font-weight: 700; font-size: 10px;
        text-transform: uppercase; letter-spacing: .03em; height: 20px;
    }
    .lembar .grup .hitung { float: right; font-weight: 500; text-transform: none; color: #52525b; }

    /* --- tanda tangan --- */
    .lembar .ttd {
        display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px; margin-top: 14px; font-size: 10.5px; text-align: center;
    }
    .lembar .ttd .peran { font-weight: 600; }
    .lembar .ttd .ruang { height: 46px; }
    .lembar .ttd .garis-ttd { border-top: 1px solid #111; padding-top: 3px; }
    .lembar .ttd .catatan { font-size: 9px; color: #52525b; }

    .lembar .kaki {
        margin-top: 10px; padding-top: 5px; border-top: 1px solid #d4d4d8;
        display: flex; justify-content: space-between; font-size: 9px; color: #71717a;
    }

    /* Petunjuk di layar saja — tidak ikut tercetak. */
    .lh-layar {
        margin-bottom: .75rem; padding: .6rem .8rem; font-size: .8rem; line-height: 1.5;
        border-radius: .5rem; border: 1px solid rgb(228 228 231); background: rgb(250 250 250);
        color: rgb(63 63 70);
    }
    .dark .lh-layar { background: rgb(39 39 42); border-color: rgb(63 63 70); color: rgb(212 212 216); }

    @media print {
        @page { size: A4 portrait; margin: 10mm 8mm 12mm; }

        /* Kerangka panel dilepas supaya yang keluar hanya kertasnya. */
        .fi-topbar, .fi-sidebar, .fi-main-sidebar, .fi-header,
        .fi-layout-sidebar-toggle-btn-ctn, .fi-sidebar-close-overlay,
        .fi-page-main-sub-navigation-mobile-menu-render-hook-ctn,
        .lh-layar { display: none !important; }

        html, body, .fi-body, .fi-layout, .fi-main-ctn { background: #fff !important; }

        .fi-main-ctn, .fi-main, .fi-main-content, .fi-page, .fi-page-main,
        .fi-page-content, .fi-page-header-main-ctn {
            display: block !important;
            margin: 0 !important; padding: 0 !important;
            gap: 0 !important; max-width: none !important; width: 100% !important;
        }

        .lembar { padding: 0; border: 0; border-radius: 0; }

        /* Judul kolom diulang di tiap halaman; baris tidak dipenggal. */
        .lembar thead { display: table-header-group; }
        .lembar tr { break-inside: avoid; page-break-inside: avoid; }
        .lembar .grup { break-after: avoid; page-break-after: avoid; }
        .lembar .ttd, .lembar .kop { break-inside: avoid; page-break-inside: avoid; }

        /* Arsiran kepala tabel harus tetap ikut tercetak. */
        .lembar, .lembar * { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    }
</style>
