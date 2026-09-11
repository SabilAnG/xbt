{{--
    Tombol terang/gelap di topbar.

    Filament sudah punya pilihan tema di menu profil, tapi terkubur dua klik ke
    dalam. Ini yang sama, satu klik, di tempat yang terlihat.

    Ditulis langsung ke localStorage dan class `dark` — persis cara Filament
    membacanya saat halaman dimuat, jadi pilihannya bertahan di halaman
    berikutnya dan tidak berkelahi dengan pilihan di menu profil.
--}}
<button
    type="button"
    class="fi-tema-tombol"
    aria-label="Ganti mode terang atau gelap"
    x-data="{
        ganti() {
            const gelap = document.documentElement.classList.toggle('dark')
            localStorage.setItem('theme', gelap ? 'dark' : 'light')

            // Komponen Filament lain — tooltip, grafik — ikut mendengarkan ini.
            window.dispatchEvent(new CustomEvent('theme-changed', {
                detail: gelap ? 'dark' : 'light',
            }))
        },
    }"
    x-on:click="ganti()"
>
    <svg class="ikon-matahari" width="18" height="18" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </svg>

    <svg class="ikon-bulan" width="18" height="18" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
    </svg>
</button>
