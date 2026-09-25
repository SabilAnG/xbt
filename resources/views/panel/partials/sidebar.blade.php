{{--
    Sidebar ungu pekat.

    Susunannya mengikuti alur kerja: yang dibuka tiap hari di atas, yang diisi
    sekali saat setup di kelompok terakhir. Menu yang belum dipindahkan dari
    panel lama ditandai "segera" dan tidak bisa diklik — lebih jujur daripada
    tautan yang membawa ke halaman kosong.
--}}
@php
    $kelompok = [
        'Ringkasan' => [
            ['Beranda', 'panel.beranda', 'rumah', true],
        ],

        'Produksi' => [
            ['Barang Produksi', 'panel.barang-produksi.index', 'kubus', true],
            ['Pembelian Bahan', 'panel.pembelian-bahan.index', 'truk', true],
            ['Hitung Ulang Stok', 'panel.stok-opname.index', 'papan', true],
            ['Resep Knalpot', 'panel.resep.index', 'labu', true],
            ['Bagian Knalpot', 'panel.komponen.index', 'papan', true],
            ['Hitung Modal', null, 'kalkulator', false],
        ],
        'Operasional' => [
            ['Stok Barang', 'panel.stok-barang.index', 'kubus', true],
            ['Pembelian', 'nota:pembelian', 'truk', true],
            ['Penjualan', 'nota:penjualan', 'papan', true],
            ['Pengeluaran', 'nota:pengeluaran', 'kalkulator', true],
            ['Opname Toko', 'nota:opname-toko', 'papan', true],
        ],

        'Website' => [
            ['Katalog', 'panel.katalog.index', 'kubus', true],
            ['Orders', 'panel.order.index', 'truk', true],
            ['Konten Halaman', 'panel.konten.index', 'papan', true],
            ['Iklan', 'panel.iklan.index', 'kubus', true],
            ['Partners', 'panel.partner.index', 'gudang', true],
            ['Pengaturan Situs', 'panel.pengaturan.index', 'rumah', true],
        ],

        'Master Data' => [
            ['Jenis Barang Produksi', 'jenis-barang-produksi', 'papan', true],
            ['Jasa Produksi', 'jasa-produksi', 'labu', true],
            ['Kategori Produk', 'kategori-produk', 'kubus', true],
            ['Produk', 'produk', 'kubus', true],
            ['Brand Motor', 'brand-motor', 'truk', true],
            ['Type Motor', 'type-motor', 'truk', true],
            ['Jenis Pengeluaran', 'jenis-pengeluaran', 'papan', true],
            ['Kategori Pengeluaran', 'kategori-pengeluaran', 'papan', true],
            ['Tingkatan Harga', 'tingkatan-harga', 'kalkulator', true],
            ['Gudang', 'gudang', 'gudang', true],
            ['Dompet & Kas', 'dompet', 'rumah', true],
        ],
    ];
@endphp

<div data-tirai class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-sm lg:hidden"></div>

<aside data-sidebar
       class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col transition-transform
              lg:inset-y-3 lg:left-3 lg:translate-x-0">
    <div class="flex min-h-0 flex-1 flex-col rounded-3xl bg-gradient-to-b from-ungu-500 to-ungu-700
                shadow-[0_18px_40px_-16px_rgb(90_52_214/0.6)]">

        {{-- Merek --}}
        <div class="flex items-center gap-3 px-5 pb-5 pt-6">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-merah-500 text-lg font-bold text-white
                         shadow-[0_0_0_6px_rgb(255_255_255/0.12)]">
                {{ str(config('app.name'))->substr(0, 1)->upper() }}
            </span>

            <span class="rounded-lg border border-white/40 px-3 py-1 text-[0.8125rem] font-semibold uppercase tracking-wide text-white">
                {{ str(config('app.name'))->words(1, '') }}
            </span>
        </div>

        {{-- Menu --}}
        <nav class="min-h-0 flex-1 space-y-6 overflow-y-auto px-3 pb-4
                    [scrollbar-color:rgb(255_255_255/0.25)_transparent] [scrollbar-width:thin]">
            @foreach ($kelompok as $judul => $menu)
                <div>
                    <p class="px-3 pb-2 text-[0.6875rem] font-bold uppercase tracking-[0.12em] text-white/55">
                        {{ $judul }}
                    </p>

                    @foreach ($menu as $i => [$label, $rute, $ikon, $siap])
                        @php
                            // Menu master data dirujuk lewat jenisnya ("produk"),
                            // bukan nama rute — semuanya berbagi satu rute.
                            // Tiga bentuk rujukan: nama rute biasa, "nota:<jenis>"
                            // untuk nota operasional, dan sisanya jenis master data.
                            $dokumen = $siap && str_starts_with((string) $rute, 'nota:');
                            $master = $siap && ! $dokumen && ! str_starts_with((string) $rute, 'panel.');
                            $kunci = $dokumen ? substr($rute, 5) : $rute;

                            $alamat = match (true) {
                                $dokumen => route('panel.dokumen.index', $kunci),
                                $master => route('panel.master.index', $kunci),
                                (bool) $siap => route($rute),
                                default => null,
                            };

                            $aktif = match (true) {
                                $dokumen => request()->routeIs('panel.dokumen.*') && request()->route('jenis') === $kunci,
                                $master => request()->routeIs('panel.master.*') && request()->route('jenis') === $kunci,
                                default => (bool) $rute && request()->routeIs(str($rute)->beforeLast('.').'.*'),
                            };
                        @endphp

                        {{-- Garis tipis antar baris, kecuali sebelum yang pertama. --}}
                        <div @class(['border-t border-white/10' => $i > 0])>
                            <a @if ($alamat) href="{{ $alamat }}" @endif
                               @class([
                                   'menu-sidebar',
                                   'menu-sidebar-aktif' => $aktif,
                                   'cursor-default text-white/35 hover:bg-transparent hover:text-white/35' => ! $siap,
                               ])>
                                <x-panel.ikon :nama="$ikon" kelas="h-5 w-5 shrink-0" />
                                <span class="truncate">{{ $label }}</span>

                                @unless ($siap)
                                    <span class="ml-auto rounded-full bg-white/10 px-1.5 py-0.5 text-[0.625rem] font-medium">
                                        segera
                                    </span>
                                @endunless
                            </a>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </nav>

        {{-- Kartu promo --}}
        <div class="mx-3 mb-3 rounded-2xl bg-ungu-800/60 p-4">
            <p class="text-[0.8125rem] font-bold uppercase tracking-wide text-white">Panel baru</p>
            <p class="mt-1 text-[0.75rem] leading-relaxed text-white/65">
                Dua layar sudah pindah ke sini. Sisanya masih di panel lama.
            </p>
            <a href="/admin" class="mt-3 inline-flex rounded-full bg-kuning-400 px-4 py-1.5 text-[0.75rem] font-bold uppercase tracking-wide text-ungu-800 transition hover:bg-kuning-500">
                Panel lama
            </a>
        </div>

        {{-- Pengguna --}}
        <div class="flex items-center gap-2.5 border-t border-white/10 px-4 py-3">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-mint-500 text-xs font-bold text-white">
                {{ str(auth()->user()?->name ?? '?')->substr(0, 1)->upper() }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[0.8125rem] font-medium text-white">{{ auth()->user()?->name }}</p>
                <p class="truncate text-[0.7rem] text-white/50">{{ auth()->user()?->email }}</p>
            </div>

            <form method="POST" action="{{ route('panel.keluar') }}">
                @csrf
                <button type="submit" title="Keluar"
                        class="grid h-8 w-8 place-items-center rounded-lg text-white/60 transition hover:bg-white/10 hover:text-white">
                    <x-panel.ikon nama="keluar" kelas="h-[1.05rem] w-[1.05rem]" />
                </button>
            </form>
        </div>
    </div>
</aside>
