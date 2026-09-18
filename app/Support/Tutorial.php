<?php

namespace App\Support;

/**
 * Panduan singkat tiap menu admin.
 *
 * Ditaruh di kode, bukan di database: isinya menerangkan cara kerja menu, dan
 * cara kerja menu berubah bersama kodenya. Panduan yang disimpan di database
 * akan tetap menjelaskan versi lama lama setelah menunya berubah.
 *
 * Kuncinya nama route Filament tanpa awalan panel, mis.
 * "resources.formulas.index". Menu yang belum punya panduan tidak memunculkan
 * apa-apa — lebih baik diam daripada menampilkan kotak kosong.
 */
class Tutorial
{
    /**
     * @return array<string, array{judul: string, ringkas: string, langkah: array<int, string>, gambar?: string}>
     */
    public static function semua(): array
    {
        return [
            'pages.dashboard' => [
                'judul' => 'Dashboard',
                'ringkas' => 'Ringkasan keadaan toko hari ini: nilai stok, kas, penjualan bulan berjalan, dan barang yang menipis.',
                'langkah' => [
                    'Angka di kartu atas dihitung ulang tiap kali halaman dibuka — tidak ada yang perlu ditekan.',
                    'Daftar "Stok Menipis" memuat barang yang stoknya sudah di bawah batas minimum. Klik "Kartu stok" untuk melihat riwayat keluar-masuknya.',
                    '"Dokumen Draft" memuat nota yang belum dibukukan. Selama masih draft, stok dan kas belum bergerak.',
                ],
            ],

            'resources.production-items.index' => [
                'judul' => 'Barang Produksi',
                'ringkas' => 'Master bahan untuk membuat knalpot: pipa, plat, baut, pegas.',
                'langkah' => [
                    'Tekan "Tambah Barang" — form terbuka sebagai jendela, halaman daftarnya tetap di belakang.',
                    'Kolom "Bentuk" menentukan segalanya. Batangan untuk pipa, Lembaran untuk plat, Satuan untuk barang beli jadi.',
                    'Untuk Batangan dan Lembaran, ukuran WAJIB diisi. Pipa Rp600.000 per batang tanpa panjang akan terbaca Rp600.000 per milimeter, dan modal formula meleset ribuan kali lipat.',
                    'Harga diisi per satuan beli — per batang, per lembar. Harga per satuan pakai dihitung sendiri.',
                ],
            ],

            'resources.exhaust-components.index' => [
                'judul' => 'Bagian Knalpot',
                'ringkas' => 'Daftar bagian penyusun knalpot. Murni daftar — bahan dan ukurannya ada di formula.',
                'langkah' => [
                    'Daftar utama hanya memuat bagian induknya: Header dan Silincer.',
                    'Klik kartunya untuk membuka isi bagian itu, lalu "Tambah Komponen" untuk menambah bagian di dalamnya.',
                    'Bagiannya tidak ditanyakan lagi saat menambah dari halaman itu — sudah pasti dari halaman mana Anda menekannya.',
                ],
            ],

            'resources.formulas.index' => [
                'judul' => 'Resep Knalpot',
                'ringkas' => 'Resep satu knalpot untuk satu type motor: komponennya apa, bahannya apa, ukurannya berapa.',
                'langkah' => [
                    'Resep baru langsung terisi seluruh bagian. Yang tidak dipakai tinggal dihapus barisnya — itu lebih cepat daripada mengingat apa saja yang harus ditambah.',
                    'Isi kolom Bahan, lalu ukurannya. Barang beli jadi cukup jumlahnya; pipa dan plat minta ukuran potongan.',
                    'Ukuran diketik dalam satuan bengkel: "20 cm x 2 potong", bukan milimeter. Konversinya dikerjakan sendiri.',
                    'Bagian "Biaya Lain-lain" untuk ongkos kerja — chrome, poles, las. Tarifnya diambil dari menu Jasa Produksi.',
                    'Garis oranye di tabel menandai pergantian bagian, dari Header ke Silincer.',
                    'Tombol "Salin" di daftar menggandakan seluruh resep berikut jasanya. Resep motor sebelah biasanya sama, hanya beberapa panjang yang berubah.',
                ],
            ],

            'resources.production-services.index' => [
                'judul' => 'Jasa Produksi',
                'ringkas' => 'Biaya membuat knalpot yang bukan bahan: chrome, poles, las argon, bending.',
                'langkah' => [
                    'Isi tarif per satuan: chrome Rp150.000 per unit, las Rp5.000 per titik.',
                    '"Satuan Tarif" menentukan tarifnya dihitung per apa. Las ditagih per titik, bukan per unit — menyamakannya membuat modal meleset belasan kali.',
                    'Tarif di sini dipanggil formula, tidak disalin. Ongkos chrome yang naik cukup diubah sekali di sini.',
                    'Jasa yang sudah dipakai sebuah resep tidak bisa dihapus.',
                ],
            ],

            'pages.kalkulator-hpp' => [
                'judul' => 'Hitung Modal',
                'ringkas' => 'Dari resep ke harga jual: modal berapa, dijual berapa, dan dipasang berapa di toko online.',
                'langkah' => [
                    'Pilih formula dan berapa set yang mau dibuat.',
                    'Bagian "Rincian HPP" memecah modalnya sampai ke tiap komponen dan tiap jasa. Angka yang tidak bisa diperiksa akhirnya dipercaya begitu saja sampai ada yang rugi.',
                    'Markup dihitung dari HPP. Perhatikan angka margin di sebelahnya — markup 40% hanya bermargin 28,6%, dan mengira keduanya sama adalah cara paling lazim mematok harga terlalu murah.',
                    'Untuk toko online, isi potongan aplikasinya. Harga yang perlu dipasang DIBAGI sisa potongan, bukan ditambah sebesar potongan — menambah 10% pada harga yang dipotong 10% selalu kurang.',
                ],
            ],

            'resources.production-purchases.index' => [
                'judul' => 'Pembelian Bahan',
                'ringkas' => 'Nota pembelian bahan produksi. Stok gudang naik dan kas turun setelah dibukukan.',
                'langkah' => [
                    'Isi persis seperti nota tokonya: "7 batang @ Rp90.000". Konversi ke satuan pakai dikerjakan sistem — kolom "Jadi Stok" menunjukkan hasilnya sebelum Anda menyimpan.',
                    'Tiap baris menyebut gudang tujuannya sendiri. Satu nota boleh memuat pipa untuk gudang bahan mentah dan baut untuk gudang lain.',
                    'Selama status masih Draft, tidak ada yang bergerak. Tekan "Bukukan" untuk menaikkan stok dan mengurangi kas.',
                    '"Batalkan" mengembalikan semuanya, lalu saldo dihitung ulang dari nota yang tersisa.',
                ],
            ],

            'resources.warehouses.index' => [
                'judul' => 'Gudang',
                'ringkas' => 'Empat gudang produksi. Stok bahan dihitung per gudang, bukan hanya totalnya.',
                'langkah' => [
                    'Klik kartu gudang untuk melihat isinya — yang tampil stok DI GUDANG ITU, bukan total seluruh gudang.',
                    'Dari halaman isi gudang, tombol "Ubah barang" membuka master barangnya, bukan baris stoknya.',
                ],
            ],

            'resources.stock-opnames.index' => [
                'judul' => 'Stok Opname',
                'ringkas' => 'Menghitung fisik stok barang jual, lalu mengoreksi selisihnya.',
                'langkah' => [
                    'Pilih barangnya — stok sistem terisi sendiri dan dibekukan saat itu juga.',
                    'Isi hasil hitungan fisik. Kolom Selisih menghitung sendiri: hijau berarti lebih, merah berarti kurang.',
                    'Barang yang ketemu di rak tapi belum ada di master bisa didaftarkan di tempat lewat tombol + di sebelah pilihan barang, berikut brand dan type motornya.',
                    'Kolom "Harga Jual" boleh ikut diperbarui. Dikosongkan berarti harga lama dibiarkan.',
                    'Hanya baris yang selisih yang mengoreksi stok — tapi harga tetap diperbarui walau jumlahnya cocok.',
                ],
            ],

            'resources.partners.index' => [
                'judul' => 'Partner',
                'ringkas' => 'Orang yang mendaftar lewat tombol "Jadi Partner" di situs, untuk punya toko sendiri.',
                'langkah' => [
                    'Pendaftar baru berstatus "Menunggu Persetujuan". Sampai disetujui, belum ada database, akun, maupun subdomain yang dibuat.',
                    'Tekan "Setujui" lalu centang menu apa saja yang boleh dibuka partner ini, dan berapa lama masa pakainya.',
                    'Saat disetujui, database toko partner dibuat berikut akun adminnya dari email dan sandi pendaftaran.',
                    '"Perpanjang" menambah masa pakai dari sisa yang ada, jadi memperpanjang lebih awal tidak merugikan.',
                    'Masa habis berarti panelnya terkunci dan tokonya offline — datanya tetap utuh dan kembali begitu diperpanjang.',
                    'Menghapus partner ikut membuang databasenya. Tidak bisa dibatalkan.',
                ],
            ],

            'resources.advertisements.index' => [
                'judul' => 'Iklan',
                'ringkas' => 'Banner orang luar di situs Hypersonic.',
                'langkah' => [
                    'Pemasang mengirim fotonya lewat WhatsApp; Anda yang menentukan posisinya.',
                    'Isi "Diklik menuju" dengan alamat tujuan — situs, Instagram, atau WhatsApp pemasang.',
                    'Masa tayang menurunkan banner sendiri saat lewat tanggalnya. Kolom "Sekarang" menunjukkan apakah ia benar-benar tampil hari ini.',
                    'Kolom Klik dihitung di server, jadi tetap terhitung walau pengunjung memakai pemblokir iklan.',
                ],
            ],

            'resources.purchases.index' => [
                'judul' => 'Pembelian',
                'ringkas' => 'Nota pembelian barang jual dari supplier. Stok bertambah dan kas berkurang setelah dibukukan.',
                'langkah' => [
                    'Isi nomor nota, tanggal, dan supplier sesuai nota aslinya.',
                    'Pilih dompet bila sudah dibayar. Dikosongkan berarti kas belum bergerak.',
                    'Tambahkan barangnya — harga beli terakhir terisi sendiri, tinggal diperbaiki bila berbeda.',
                    'Selama masih Draft tidak ada yang bergerak. Tekan "Bukukan" untuk menaikkan stok dan mengurangi kas.',
                    'Harga beli di nota yang dibukukan menjadi harga pokok berjalan barang itu.',
                ],
            ],

            'resources.sales.index' => [
                'judul' => 'Penjualan',
                'ringkas' => 'Nota penjualan ke pembeli. Stok berkurang, kas bertambah, dan labanya dihitung.',
                'langkah' => [
                    'Pilih barang dan jumlahnya; harga jual terisi dari master barang.',
                    'Modal tiap baris dibekukan saat dibukukan, jadi laba historis tidak ikut berubah kalau harga pokok naik belakangan.',
                    'Tekan "Bukukan" untuk mengurangi stok dan menambah kas.',
                    '"Batalkan" mengembalikan semuanya, lalu stok dan saldo dihitung ulang dari nota yang tersisa.',
                ],
            ],

            'resources.expenses.index' => [
                'judul' => 'Pengeluaran',
                'ringkas' => 'Biaya di luar pembelian barang: listrik, sewa, gaji, ongkos kirim.',
                'langkah' => [
                    'Pilih kategori biayanya — itu yang mengelompokkan pengeluaran di laporan.',
                    'Pilih dompet sumber dananya. Dikosongkan berarti belum dibayar dan kas tidak bergerak.',
                    'Tekan "Bukukan" untuk mengurangi saldo dompet.',
                ],
            ],

            'resources.products.index' => [
                'judul' => 'Katalog Website',
                'ringkas' => 'Produk yang tampil di halaman toko Anda.',
                'langkah' => [
                    'Ini yang dilihat pengunjung — berbeda dari Stok Barang, yang mengurus persediaan.',
                    'Isi harga dalam rupiah, lalu unggah fotonya. Foto pertama jadi gambar utama.',
                    'Yang tidak aktif hilang dari halaman toko tanpa dihapus datanya.',
                    'Satu produk bisa ditautkan ke barang di Stok Barang, supaya stoknya ikut terbaca.',
                ],
            ],

            'resources.orders.index' => [
                'judul' => 'Orders',
                'ringkas' => 'Pesanan yang masuk dari halaman toko.',
                'langkah' => [
                    'Pesanan datang sendiri saat pengunjung memesan lewat situs.',
                    'Ubah statusnya seiring pesanan diproses, supaya riwayatnya terbaca.',
                    'Pesanan yang sudah dibayar dicatat sebagai penjualan lewat menu Penjualan.',
                ],
            ],

            'resources.wallets.index' => [
                'judul' => 'Dompet',
                'ringkas' => 'Tempat uang: kas laci, rekening bank, dompet digital.',
                'langkah' => [
                    'Saldo tidak diketik langsung. Ia bergerak lewat nota yang dibukukan.',
                    '"Saldo awal" hanya untuk keadaan saat pertama kali dicatat di sistem.',
                    'Tiap nota pembelian, penjualan, dan pengeluaran menunjuk satu dompet — itu yang membuat saldonya bergerak.',
                ],
            ],

            'resources.price-tiers.index' => [
                'judul' => 'Tingkat Harga',
                'ringkas' => 'Aturan harga untuk tiap jenis pembeli: umum, reseller, marketplace.',
                'langkah' => [
                    'Margin dihitung dari harga jual, bukan dari modal. Margin 40% berarti empat puluh persen dari uang yang masuk benar-benar jadi laba.',
                    '"Potongan Marketplace" untuk penjualan lewat aplikasi. Isi 0 untuk penjualan langsung.',
                    'Contoh perhitungannya tampil di bawah form, memakai barang bermodal tertinggi.',
                ],
            ],

            'resources.item-categories.index' => [
                'judul' => 'Kategori Produk',
                'ringkas' => 'Pengelompokan barang jual: Full Set, Silincer, Leheran.',
                'langkah' => [
                    'Dipakai menyaring daftar dan mengelompokkan laporan.',
                    'Kategori yang masih dipakai barang tidak bisa dihapus.',
                ],
            ],

            'resources.item-types.index' => [
                'judul' => 'Jenis Barang',
                'ringkas' => 'Pembeda kasar barang jual: Produk Jadi, Sparepart.',
                'langkah' => [
                    'Lebih luas daripada kategori — dipakai memisahkan barang yang Anda buat sendiri dari yang dibeli jadi.',
                ],
            ],

            'resources.motorcycle-brands.index' => [
                'judul' => 'Brand Motor',
                'ringkas' => 'Merek motor: Honda, Yamaha, Suzuki.',
                'langkah' => [
                    'Induk dari Type Motor. Menghapus brand tidak bisa selama masih ada type motor di bawahnya.',
                ],
            ],

            'resources.motorcycle-models.index' => [
                'judul' => 'Type Motor',
                'ringkas' => 'Type motor berikut brand-nya: Honda Beat Street, Yamaha Aerox 155.',
                'langkah' => [
                    'Slug memuat nama brand supaya "Beat" Honda dan Yamaha tidak bentrok.',
                    'Dipakai formula untuk menyebut resep ini milik motor apa, dan dipakai barang untuk menyebut cocok di motor apa.',
                    'Type motor baru juga bisa dibuat langsung dari stok opname saat mendaftarkan barang.',
                ],
            ],

            'resources.expense-types.index' => [
                'judul' => 'Jenis Pengeluaran',
                'ringkas' => 'Pengelompokan besar biaya: Operasional, Produksi.',
                'langkah' => [
                    'Induk dari Kategori Pengeluaran, dan itu yang memisahkan biaya di laporan.',
                ],
            ],

            'resources.expense-categories.index' => [
                'judul' => 'Kategori Pengeluaran',
                'ringkas' => 'Rincian biaya: Listrik, Sewa Tempat, Gaji, Ongkos Kirim.',
                'langkah' => [
                    'Tiap kategori menempel pada satu jenis pengeluaran.',
                    'Inilah yang dipilih saat mencatat pengeluaran, jadi buat secukupnya — terlalu rinci membuat orang asal pilih.',
                ],
            ],

            'resources.production-item-categories.index' => [
                'judul' => 'Jenis Barang Produksi',
                'ringkas' => 'Pengelompokan bahan: Pipa, Plat, Baut & Mur, Bahan Penolong.',
                'langkah' => [
                    '"Perannya di produk" dan "Didapat dari" dijawab sekali di sini, lalu terisi otomatis tiap kali bahan jenis ini ditambahkan.',
                    '"Gudang bawaan" mengisi sendiri gudang tujuan saat bahan jenis ini dibeli.',
                    'Aksesoris Utama untuk yang bentuknya aksesoris tapi wajib ada — pegas dan karet mounting.',
                ],
            ],

            'resources.production-item-opnames.index' => [
                'judul' => 'Hitung Ulang Stok',
                'ringkas' => 'Menghitung fisik stok bahan di satu gudang, lalu mengoreksi selisihnya.',
                'langkah' => [
                    'Pilih gudang yang dihitung dulu — koreksi stok harus tahu masuk ke gudang mana.',
                    'Isi hasil hitungan dalam satuan pakai: pipa dalam meter atau milimeter, bukan batang.',
                    'Hanya baris yang selisih yang mengoreksi stok.',
                    '"Lembar Hitung" bisa dicetak untuk dibawa ke gudang.',
                ],
            ],

            'resources.content-blocks.index' => [
                'judul' => 'Page Content',
                'ringkas' => 'Teks dan gambar di halaman toko, bisa diubah tanpa menyentuh kode.',
                'langkah' => [
                    'Tiap baris mewakili satu potong teks atau gambar di halaman.',
                    'Yang dikosongkan kembali memakai teks bawaan, jadi halaman tidak pernah tampil kosong.',
                    'Nama toko, email, dan nomor WhatsApp diatur terpisah di menu Site settings.',
                ],
            ],

            'resources.items.index' => [
                'judul' => 'Stok Barang',
                'ringkas' => 'Barang jadi yang dijual, berikut stok dan harganya.',
                'langkah' => [
                    'Tambah dan ubah lewat jendela — halaman daftarnya tetap di belakang.',
                    '"Kartu stok" membuka riwayat keluar-masuk satu barang: dari nota mana, berapa, dan sisanya berapa.',
                    'Stok tidak diketik langsung. Ia bergerak lewat pembelian, penjualan, dan stok opname.',
                ],
            ],
        ];
    }

    /**
     * Panduan untuk route yang sedang dibuka, atau null bila belum ada.
     */
    public static function untukRoute(?string $namaRoute): ?array
    {
        if (blank($namaRoute)) {
            return null;
        }

        // "filament.admin.resources.formulas.index" -> "resources.formulas.index"
        $kunci = preg_replace('/^filament\.[^.]+\./', '', $namaRoute);

        return self::semua()[$kunci] ?? null;
    }

    /**
     * Tangkapan layar menu ini, bila ada berkasnya.
     *
     * Sengaja dicari di disk, bukan didaftarkan di kode: gambar yang hilang
     * tidak boleh membuat panduannya ikut hilang, dan mengganti gambar yang
     * usang cukup dengan menimpa berkasnya.
     */
    public static function gambar(string $kunci): ?string
    {
        $berkas = 'tutorial/'.str_replace('.', '-', $kunci).'.png';

        return file_exists(public_path($berkas)) ? asset($berkas) : null;
    }
}
