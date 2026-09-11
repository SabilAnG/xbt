{{--
    Tampilan panel: kaca gelap dengan cahaya keemasan dari atas.

    Ditulis sebagai satu lapis di atas gaya bawaan Filament, bukan tema yang
    dibangun ulang: Filament memperbarui gayanya sendiri tiap rilis, dan tema
    yang menyalin seluruhnya akan tertinggal diam-diam sampai ada yang rusak.

    Aturan yang dipegang di sini:

    - Cahaya datang dari satu tempat — atas-tengah, seperti lampu sorot. Glow
      yang datang dari mana-mana membuat layar terasa berkabut, bukan mewah.
    - Kaca hanya di lapisan yang memang mengambang: topbar, sidebar, kartu,
      modal. Isi tabel tetap padat, karena angka yang dibaca lama di atas latar
      buram melelahkan mata.
    - Terang dan gelap sama-sama dikerjakan. Tema gelap yang tidak diuji di
      mode terang selalu menyisakan teks yang nyaris tidak terbaca di sana.

    Catatan warna: Filament 5 menyimpan --primary-500 dan kawan-kawan sebagai
    oklch() utuh, bukan tiga angka kanal. Jadi pengencerannya lewat color-mix,
    bukan rgb(var(--primary-500) / 0.4) — bentuk terakhir itu tidak sah dan
    gugur tanpa pesan apa pun, menyisakan panel tanpa cahaya sama sekali.
--}}

<style>
    :root {
        --kaca-blur: 18px;
        --kaca-radius: 1.25rem;
        --kaca-transisi: 220ms cubic-bezier(0.4, 0, 0.2, 1);

        /* Warna cahaya ikut --primary-500, jadi berubah sendiri kalau warna
           panel diganti. */
        --kaca-glow: color-mix(in srgb, var(--primary-500) 40%, transparent);
        --kaca-glow-lembut: color-mix(in srgb, var(--primary-500) 15%, transparent);
        --kaca-tepi-nyala: color-mix(in srgb, var(--primary-500) 30%, transparent);

        /* Terang */
        --kaca-bg: rgb(255 255 255 / 0.74);
        --kaca-bg-topbar: rgb(255 255 255 / 0.82);
        --kaca-bg-pekat: rgb(255 255 255 / 0.97);
        --kaca-garis: rgb(255 255 255 / 0.7);

        /* Garis kilau di bibir atas permukaan — tempat cahaya jatuh. Ini yang
           membuat panel terbaca sebagai kaca, bukan sekadar kotak berwarna. */
        --kaca-kilau: rgb(255 255 255 / 0.9);
        --kaca-bayang: 0 8px 32px rgb(15 23 42 / 0.08);
        --kaca-lantai: #f4f4f6;
        --kaca-sorot: 22%;
    }

    .dark {
        /*
         * Permukaan kaca di sini adalah lapisan PUTIH tipis, bukan abu-abu
         * gelap. Abu-abu gelap di atas latar yang hampir hitam menghasilkan
         * warna yang nyaris sama dengan latarnya — panelnya jadi tak terlihat
         * terangkat sama sekali, persis seperti bawaan Filament. Putih tipis
         * mengangkatnya, dan blur di belakangnya baru ada gunanya.
         */
        --kaca-bg: rgb(255 255 255 / 0.055);
        --kaca-bg-topbar: rgb(13 13 16 / 0.62);
        --kaca-bg-pekat: rgb(26 26 30 / 0.96);
        --kaca-garis: rgb(255 255 255 / 0.1);
        --kaca-kilau: rgb(255 255 255 / 0.07);
        --kaca-bayang: 0 10px 40px rgb(0 0 0 / 0.55);
        --kaca-lantai: #0a0a0b;

        /* Jauh lebih kuat daripada mode terang: topbar dan sidebar berdiri di
           depan cahaya ini dan menyerap sebagian besarnya, jadi angka yang
           terlihat wajar di latar putih hilang sama sekali di latar hitam. */
        --kaca-sorot: 58%;
    }

    /* ---------------------------------------------------------------- latar */

    .fi-body {
        background: var(--kaca-lantai) !important;
        transition: background-color 260ms ease;
    }

    /*
     * Lampu sorot dari atas-tengah. Satu kerucut cahaya, bukan beberapa
     * bulatan: dua sumber cahaya di satu layar membuat mata tidak tahu harus
     * membaca dari mana.
     */
    .fi-body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        background:
            radial-gradient(
                64rem 38rem at 50% -7rem,
                color-mix(in srgb, var(--primary-400) var(--kaca-sorot), transparent),
                transparent 72%
            ),
            radial-gradient(
                26rem 20rem at 50% -4rem,
                color-mix(in srgb, var(--primary-300) calc(var(--kaca-sorot) * 0.9), transparent),
                transparent 66%
            );
        transition: opacity 260ms ease;
    }

    /* Isi halaman harus berada di atas cahaya, bukan tertimbun olehnya. */
    .fi-layout,
    .fi-topbar,
    .fi-sidebar,
    .fi-main-ctn {
        position: relative;
        z-index: 1;
    }

    /* ------------------------------------------------------- topbar & sidebar */

    .fi-topbar,
    .fi-sidebar {
        background: var(--kaca-bg) !important;
        backdrop-filter: blur(var(--kaca-blur)) saturate(150%);
        -webkit-backdrop-filter: blur(var(--kaca-blur)) saturate(150%);
        border-color: var(--kaca-garis) !important;
    }

    .fi-topbar {
        background: var(--kaca-bg-topbar) !important;
        border-bottom: 1px solid var(--kaca-garis) !important;
        box-shadow: var(--kaca-bayang), inset 0 1px 0 var(--kaca-kilau);
    }

    .fi-sidebar {
        border-inline-end: 1px solid var(--kaca-garis) !important;
    }

    /* Menu yang sedang dibuka diberi cahaya tipis, bukan sekadar blok warna. */
    .fi-sidebar-item-active > .fi-sidebar-item-btn {
        background: linear-gradient(
            90deg,
            color-mix(in srgb, var(--primary-500) 22%, transparent),
            color-mix(in srgb, var(--primary-500) 3%, transparent)
        ) !important;
        box-shadow: inset 0 0 0 1px var(--kaca-tepi-nyala),
                    0 0 20px -6px var(--kaca-glow);
    }

    .fi-sidebar-item-btn {
        border-radius: 0.7rem;
        transition: background var(--kaca-transisi),
                    box-shadow var(--kaca-transisi),
                    transform var(--kaca-transisi);
    }

    .fi-sidebar-item-btn:hover {
        transform: translateX(3px);
    }

    /* ---------------------------------------------------------------- kartu */

    .fi-section,
    .fi-wi-stats-overview-stat,
    .fi-modal-window,
    .fi-dropdown-panel {
        background: var(--kaca-bg) !important;
        backdrop-filter: blur(var(--kaca-blur)) saturate(150%);
        -webkit-backdrop-filter: blur(var(--kaca-blur)) saturate(150%);
        border: 1px solid var(--kaca-garis) !important;
        border-radius: var(--kaca-radius) !important;
        box-shadow: var(--kaca-bayang), inset 0 1px 0 var(--kaca-kilau);
        transition: box-shadow var(--kaca-transisi),
                    transform var(--kaca-transisi),
                    background var(--kaca-transisi),
                    border-color var(--kaca-transisi);
    }

    /*
     * Yang mengambang di atas isi halaman dibuat pekat, bukan kaca tipis.
     * Menu dan modal harus terbaca di atas apa pun yang kebetulan ada di
     * belakangnya — teks tipis di atas teks lain tidak terbaca siapa pun.
     */
    .fi-modal-window,
    .fi-dropdown-panel {
        background: var(--kaca-bg-pekat) !important;
    }

    /*
     * Kartu angka di dashboard. Satu-satunya yang bergerak saat disentuh,
     * karena hanya di sana gerak itu menjelaskan sesuatu: ini bisa diklik.
     */
    .fi-wi-stats-overview-stat {
        position: relative;
        overflow: hidden;
    }

    /* Garis cahaya tipis di bibir atas kartu — tempat cahaya dari atas jatuh. */
    .fi-wi-stats-overview-stat::before {
        content: '';
        position: absolute;
        inset-inline: 15%;
        top: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, var(--kaca-glow), transparent);
        opacity: 0;
        transition: opacity var(--kaca-transisi);
    }

    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-4px);
        border-color: var(--kaca-tepi-nyala) !important;
        box-shadow: var(--kaca-bayang), inset 0 1px 0 var(--kaca-kilau),
                    0 0 34px -12px var(--kaca-glow);
    }

    .fi-wi-stats-overview-stat:hover::before {
        opacity: 1;
    }

    /* Angka besar di kartu statistik — itu yang dicari orang lebih dulu. */
    .fi-wi-stats-overview-stat-value {
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    /* Tabel tetap padat: angka yang dibaca lama di atas latar buram melelahkan. */
    .fi-ta-ctn {
        border-radius: var(--kaca-radius) !important;
        border-color: var(--kaca-garis) !important;
        box-shadow: var(--kaca-bayang);
        overflow: hidden;
    }

    /* --------------------------------------------------------------- tombol */

    .fi-btn,
    .fi-icon-btn,
    .fi-input,
    .fi-select-input,
    .fi-input-wrp {
        transition: box-shadow var(--kaca-transisi),
                    border-color var(--kaca-transisi),
                    background var(--kaca-transisi),
                    transform 120ms ease;
    }

    .fi-btn:active,
    .fi-icon-btn:active {
        transform: scale(0.97);
    }

    .fi-btn.fi-color-primary:hover {
        box-shadow: 0 0 24px -6px var(--kaca-glow);
    }

    /* Isian yang sedang diketik diberi lingkar cahaya, bukan sekadar garis. */
    .fi-input-wrp:focus-within {
        box-shadow: 0 0 0 3px var(--kaca-glow-lembut),
                    0 0 22px -8px var(--kaca-glow);
    }

    /* ------------------------------------------------------ tombol mode terang */

    .fi-tema-tombol {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        margin-inline-end: 0.5rem;
        border: 1px solid var(--kaca-garis);
        border-radius: 999px;
        background: var(--kaca-bg-pekat);
        color: var(--gray-500);
        cursor: pointer;
        transition: box-shadow var(--kaca-transisi),
                    color var(--kaca-transisi),
                    transform var(--kaca-transisi);
    }

    .fi-tema-tombol:hover {
        color: var(--primary-500);
        box-shadow: 0 0 22px -6px var(--kaca-glow);
        transform: rotate(-15deg);
    }

    .dark .fi-tema-tombol { color: var(--gray-400); }

    /* Matahari saat terang, bulan saat gelap — satu tombol, dua keadaan. */
    .fi-tema-tombol .ikon-bulan { display: none; }
    .dark .fi-tema-tombol .ikon-matahari { display: none; }
    .dark .fi-tema-tombol .ikon-bulan { display: block; }

    /* ------------------------------------------------------------- perpindahan */

    /* Perpindahan terang↔gelap disapukan, bukan berkedip. */
    .fi-body,
    .fi-main,
    .fi-sidebar,
    .fi-topbar,
    .fi-section,
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat {
        transition: background-color 260ms ease,
                    border-color 260ms ease,
                    color 260ms ease;
    }

    /* ------------------------------------------------------------- cadangan */

    /*
     * Peramban tanpa backdrop-filter mendapat warna padat. Tanpa ini panel
     * tampil dengan teks di atas latar setengah tembus yang tidak terbaca.
     */
    @supports not (backdrop-filter: blur(1px)) {
        .fi-topbar,
        .fi-sidebar,
        .fi-section,
        .fi-wi-stats-overview-stat,
        .fi-modal-window,
        .fi-dropdown-panel {
            background: var(--gray-50) !important;
        }

        .dark .fi-topbar,
        .dark .fi-sidebar,
        .dark .fi-section,
        .dark .fi-wi-stats-overview-stat,
        .dark .fi-modal-window,
        .dark .fi-dropdown-panel {
            background: #121214 !important;
        }
    }

    /* Yang meminta gerak lebih sedikit mendapat panel yang diam dan jernih. */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            transition-duration: 0.01ms !important;
            animation-duration: 0.01ms !important;
        }

        .fi-sidebar-item-btn:hover,
        .fi-wi-stats-overview-stat:hover,
        .fi-tema-tombol:hover {
            transform: none;
        }
    }
</style>
