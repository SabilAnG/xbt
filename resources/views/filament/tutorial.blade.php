{{--
    Tombol buku di pojok kanan bawah panel.

    Panduannya mengikuti menu yang sedang dibuka — yang dicari orang saat bingung
    adalah cara memakai layar di depannya, bukan daftar isi seluruh aplikasi.

    Menu yang belum punya panduan tidak memunculkan tombol sama sekali. Tombol
    yang dibuka lalu ternyata kosong lebih mengecewakan daripada tidak ada.
--}}
@php
    $namaRoute = request()->route()?->getName();
    $panduan = \App\Support\Tutorial::untukRoute($namaRoute);
    $kunci = $panduan ? preg_replace('/^filament\.[^.]+\./', '', $namaRoute) : null;
    $gambar = $kunci ? \App\Support\Tutorial::gambar($kunci) : null;
@endphp

@if ($panduan)
    <div x-data="{ buka: false }" class="fi-tutorial">
        <button type="button"
            x-on:click="buka = true"
            class="fi-tutorial-tombol"
            title="Cara pakai menu ini"
            aria-label="Cara pakai menu ini">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
            </svg>
        </button>

        <div x-show="buka" x-cloak
            x-on:keydown.escape.window="buka = false"
            class="fi-tutorial-latar"
            x-transition.opacity>
            <div class="fi-tutorial-kotak" x-on:click.outside="buka = false">
                <div class="fi-tutorial-kepala">
                    <div>
                        <h2>{{ $panduan['judul'] }}</h2>
                        <p>{{ $panduan['ringkas'] }}</p>
                    </div>
                    <button type="button" x-on:click="buka = false" aria-label="Tutup">&times;</button>
                </div>

                <div class="fi-tutorial-isi">
                    @if ($gambar)
                        <img src="{{ $gambar }}" alt="Tampilan menu {{ $panduan['judul'] }}">
                    @endif

                    <ol>
                        @foreach ($panduan['langkah'] as $langkah)
                            <li>{{ $langkah }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        .fi-tutorial-tombol {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border: 0;
            border-radius: 999px;
            background: var(--primary-600);
            color: #fff;
            cursor: pointer;
            box-shadow: 0 8px 24px rgb(0 0 0 / 0.25);
        }

        .fi-tutorial-tombol:hover { background: var(--primary-500); }

        .fi-tutorial-latar {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgb(0 0 0 / 0.6);
        }

        .fi-tutorial-kotak {
            width: min(720px, 100%);
            max-height: 86vh;
            overflow-y: auto;
            border-radius: var(--radius-xl);
            background: var(--gray-50);
            color: var(--gray-950);
        }

        .fi-tutorial-kepala {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
        }

        .fi-tutorial-kepala h2 { margin: 0 0 4px; font-size: 1.25rem; font-weight: 700; }
        .fi-tutorial-kepala p { margin: 0; font-size: 0.9rem; color: var(--gray-500); }

        .fi-tutorial-kepala button {
            border: 0;
            background: none;
            font-size: 1.75rem;
            line-height: 1;
            color: var(--gray-500);
            cursor: pointer;
        }

        .fi-tutorial-isi { padding: 20px 24px 24px; }

        .fi-tutorial-isi img {
            width: 100%;
            margin-bottom: 20px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
        }

        /* Reset Filament membuang penanda daftar; langkah bernomor perlu nomornya. */
        .fi-tutorial-isi ol { margin: 0; padding-left: 24px; list-style: decimal outside; }
        .fi-tutorial-isi li { margin-bottom: 12px; line-height: 1.6; }
        .fi-tutorial-isi li:last-child { margin-bottom: 0; }

        .dark .fi-tutorial-kotak { background: var(--gray-900); color: var(--gray-50); }
        .dark .fi-tutorial-kepala { border-color: var(--gray-700); }
        .dark .fi-tutorial-isi img { border-color: var(--gray-700); }
    </style>
@endif
