/*
 * Panel admin — JS seadanya, tanpa kerangka kerja.
 *
 * Panel ini dirender di server. Yang perlu JavaScript cuma hal-hal yang memang
 * tidak bisa dikerjakan HTML: laci sidebar di layar sempit, menu lipat, dan
 * pencarian yang tidak menunggu tombol ditekan. Semuanya di bawah 100 baris,
 * jadi tidak ada dependensi yang perlu dipasang.
 */

/** Laci sidebar untuk layar sempit. */
function lasiSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const tirai = document.querySelector('[data-tirai]');

    if (!sidebar || !tirai) {
        return;
    }

    const buka = (jadi) => {
        sidebar.classList.toggle('-translate-x-full', !jadi);
        tirai.classList.toggle('hidden', !jadi);
    };

    document.querySelectorAll('[data-buka-sidebar]').forEach((el) =>
        el.addEventListener('click', () => buka(true)),
    );

    tirai.addEventListener('click', () => buka(false));

    // Escape menutup laci — kebiasaan yang diharapkan orang dari apa pun yang
    // menutupi layar.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            buka(false);
        }
    });
}

/** Menu buka-tutup: tombol mana pun ber-data-menu menyasar panel ber-id sama. */
function menuLipat() {
    document.querySelectorAll('[data-menu]').forEach((tombol) => {
        const panel = document.getElementById(tombol.dataset.menu);

        if (!panel) {
            return;
        }

        tombol.addEventListener('click', (e) => {
            e.stopPropagation();
            panel.classList.toggle('hidden');
        });
    });

    // Klik di luar menutup semuanya. Tanpa ini menu yang terbuka menempel
    // sampai halaman berpindah.
    document.addEventListener('click', () => {
        document.querySelectorAll('[data-menu]').forEach((tombol) => {
            document.getElementById(tombol.dataset.menu)?.classList.add('hidden');
        });
    });
}

/**
 * Pencarian yang mengirim sendiri setelah orang berhenti mengetik.
 *
 * Jeda 350 ms: cukup lama untuk tidak mengirim per huruf, cukup pendek untuk
 * tidak terasa seperti menunggu.
 */
function cariOtomatis() {
    const isian = document.querySelector('[data-cari]');

    if (!isian) {
        return;
    }

    let jeda;

    isian.addEventListener('input', () => {
        clearTimeout(jeda);
        jeda = setTimeout(() => isian.form.submit(), 350);
    });
}


/**
 * Isian ukuran mengikuti bentuk yang dipilih.
 *
 * Menanyakan lebar lembaran untuk sepotong pipa hanya membingungkan. Yang
 * disembunyikan tetap terkirim sebagai string kosong, dan itu memang yang
 * diharapkan server: kolomnya nullable.
 */
function ukuranIkutBentuk() {
    const pilihan = document.querySelector('[data-bentuk-pilihan]');

    if (!pilihan) {
        return;
    }

    const terapkan = () => {
        document.querySelectorAll('[data-bentuk]').forEach((el) => {
            const untuk = el.dataset.bentuk.split(' ');
            el.classList.toggle('hidden', !untuk.includes(pilihan.value));
        });
    };

    pilihan.addEventListener('change', terapkan);
    terapkan();
}

document.addEventListener('DOMContentLoaded', () => {
    lasiSidebar();
    menuLipat();
    cariOtomatis();
    ukuranIkutBentuk();
});
