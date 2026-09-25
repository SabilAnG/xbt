<?php

use App\Http\Controllers\AdvertisementClickController;
use App\Http\Controllers\Panel\BarangProduksiController as PanelBarangProduksiController;
use App\Http\Controllers\Panel\DasborController as PanelDasborController;
use App\Http\Controllers\Panel\DokumenController as PanelDokumenController;
use App\Http\Controllers\Panel\IklanController as PanelIklanController;
use App\Http\Controllers\Panel\KatalogController as PanelKatalogController;
use App\Http\Controllers\Panel\KomponenController as PanelKomponenController;
use App\Http\Controllers\Panel\KontenController as PanelKontenController;
use App\Http\Controllers\Panel\MasterDataController as PanelMasterDataController;
use App\Http\Controllers\Panel\MasukController as PanelMasukController;
use App\Http\Controllers\Panel\OrderController as PanelOrderController;
use App\Http\Controllers\Panel\PartnerController as PanelPartnerController;
use App\Http\Controllers\Panel\PembelianBahanController as PanelPembelianBahanController;
use App\Http\Controllers\Panel\PengaturanSitusController as PanelPengaturanSitusController;
use App\Http\Controllers\Panel\ResepController as PanelResepController;
use App\Http\Controllers\Panel\StokBarangController as PanelStokBarangController;
use App\Http\Controllers\Panel\StokOpnameController as PanelStokOpnameController;
use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\TrackingController;
use App\Http\Middleware\KenaliPartnerDariPath;
use App\Http\Middleware\PastikanMasukPanel;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
| Static mirror of the hypersonic.id front-end. Product detail pages are
| still flat Blade views; they move to the database once the Filament
| Product resource is in place.
*/

/*
| Halaman yang sama melayani dua sisi: situs Hypersonic di alamat induk, dan
| toko partner di /toko/{slug}. Didefinisikan sekali lalu didaftarkan dua kali —
| yang berbeda cuma awalan alamat dan database di belakangnya.
|
| Path, bukan subdomain: partner yang disetujui langsung hidup, tanpa DNS baru,
| tanpa vhost, tanpa sertifikat.
*/
$halamanToko = function () {
    Route::view('/', 'pages.home')->name('home');
    Route::view('/about', 'pages.about')->name('about');
    Route::view('/contact', 'pages.contact')->name('contact');
    Route::view('/workshop', 'pages.workshop')->name('workshop');
    Route::view('/tracking', 'pages.tracking')->name('tracking');
    Route::view('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');
    Route::view('/terms-and-conditions', 'pages.terms-and-conditions')->name('terms-and-conditions');

    Route::get('/products', function () {
        return view('pages.products', [
            'products' => Product::with('images')->active()->ordered()->get(),
        ]);
    })->name('products.index');

    Route::get('/products/{product:slug}', function (Product $product) {
        abort_unless($product->is_active, 404);

        return view('pages.products.show', [
            'product' => $product->load('images'),
        ]);
    })->name('products.show');
};

// Situs induk.
Route::group([], $halamanToko);

// Toko partner.
Route::prefix('toko/{partner}')
    ->name('toko.')
    ->middleware(KenaliPartnerDariPath::class)
    ->group($halamanToko);

/*
| Hanya di situs induk. Mendaftar jadi partner dan memasang iklan adalah
| urusan Hypersonic; dibiarkan hidup di toko partner, pendaftarannya justru
| mendarat di database partner itu dan tidak pernah terbaca.
*/
Route::middleware('pusat')->group(function () {
    Route::get('/jadi-partner', [PartnerRegistrationController::class, 'create'])->name('partner.register');
    // Formulir terbuka untuk siapa saja. Tanpa batas, satu skrip bisa memenuhi
    // antrean persetujuan dengan ribuan baris dalam semenit.
    Route::post('/jadi-partner', [PartnerRegistrationController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('partner.register.store');

    Route::view('/pasang-iklan', 'pages.pasang-iklan')->name('pasang-iklan');

    // Klik banner iklan: dihitung di server lalu diteruskan ke situs pemasang.
    Route::get('/iklan/{advertisement}', AdvertisementClickController::class)->name('iklan.klik');
});

/*
|--------------------------------------------------------------------------
| Endpoints the front-end markup calls
|--------------------------------------------------------------------------
*/

Route::post('/tracking/track', [TrackingController::class, 'track'])->name('tracking.track');

Route::get('/redirect/away', [RedirectController::class, 'away'])->name('redirect.away');
Route::get('/redirect/product-direct-checkout', [RedirectController::class, 'productDirectCheckout'])
    ->name('redirect.product-checkout');

/*
|--------------------------------------------------------------------------
| Panel admin baru
|--------------------------------------------------------------------------
|
| Dibangun berdampingan dengan panel Filament di /admin, bukan menggantikannya
| sekaligus. Layar dipindahkan satu per satu; selama itu keduanya hidup dan
| memakai tabel serta akun yang sama.
|
*/
Route::prefix('panel')->name('panel.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/masuk', [PanelMasukController::class, 'tampil'])->name('masuk');
        Route::post('/masuk', [PanelMasukController::class, 'kirim'])->name('masuk.kirim');
    });

    Route::middleware(PastikanMasukPanel::class)->group(function () {
        Route::post('/keluar', [PanelMasukController::class, 'keluar'])->name('keluar');

        Route::controller(PanelBarangProduksiController::class)
            ->prefix('barang-produksi')->name('barang-produksi.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{barangProduksi}/ubah', 'edit')->name('edit');
                Route::put('/{barangProduksi}', 'update')->name('update');
                Route::delete('/{barangProduksi}', 'destroy')->name('destroy');
            });

        Route::controller(PanelPembelianBahanController::class)
            ->prefix('pembelian-bahan')->name('pembelian-bahan.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{pembelianBahan}', 'show')->name('show');
                Route::post('/{pembelianBahan}/baris', 'tambahBaris')->name('tambah-baris');
                Route::delete('/{pembelianBahan}/baris/{baris}', 'hapusBaris')->name('hapus-baris');
                Route::post('/{pembelianBahan}/bukukan', 'bukukan')->name('bukukan');
                Route::post('/{pembelianBahan}/batalkan', 'batalkan')->name('batalkan');
            });

        // Empat nota operasional ditangani satu controller; bentuknya
        // dideklarasikan di App\Support\DaftarDokumen.
        Route::controller(PanelOrderController::class)
            ->prefix('order')->name('order.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{order}/ubah', 'edit')->name('edit');
                Route::put('/{order}', 'update')->name('update');
                Route::delete('/{order}', 'destroy')->name('destroy');
                Route::post('/{order}/kiriman', 'tambahKiriman')->name('tambah-kiriman');
                Route::delete('/{order}/kiriman/{kiriman}', 'hapusKiriman')->name('hapus-kiriman');
                Route::post('/{order}/dhl', 'segarkanDhl')->name('segarkan-dhl');
            });

        Route::controller(PanelPengaturanSitusController::class)
            ->prefix('pengaturan')->name('pengaturan.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/', 'update')->name('update');
            });

        Route::controller(PanelResepController::class)
            ->prefix('resep')->name('resep.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{resep}', 'show')->name('show');
                Route::put('/{resep}', 'update')->name('update');
                Route::post('/{resep}/bahan', 'tambahBaris')->name('tambah-baris');
                Route::delete('/{resep}/bahan/{baris}', 'hapusBaris')->name('hapus-baris');
                Route::post('/{resep}/jasa', 'tambahJasa')->name('tambah-jasa');
                Route::delete('/{resep}/jasa/{jasa}', 'hapusJasa')->name('hapus-jasa');
            });

        Route::controller(PanelKomponenController::class)
            ->prefix('komponen')->name('komponen.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{komponen}', 'update')->name('update');
                Route::delete('/{komponen}', 'destroy')->name('destroy');
            });

        Route::controller(PanelPartnerController::class)
            ->prefix('partner')->name('partner.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{partner}', 'show')->name('show');
                Route::post('/{partner}/setujui', 'setujui')->name('setujui');
                Route::post('/{partner}/perpanjang', 'perpanjang')->name('perpanjang');
                Route::post('/{partner}/hak', 'hak')->name('hak');
                Route::post('/{partner}/tolak', 'tolak')->name('tolak');
                Route::delete('/{partner}', 'destroy')->name('destroy');
            });

        Route::controller(PanelIklanController::class)
            ->prefix('iklan')->name('iklan.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{iklan}/ubah', 'edit')->name('edit');
                Route::put('/{iklan}', 'update')->name('update');
                Route::delete('/{iklan}', 'destroy')->name('destroy');
            });

        Route::controller(PanelKontenController::class)
            ->prefix('konten')->name('konten.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/', 'update')->name('update');
            });

        Route::controller(PanelKatalogController::class)
            ->prefix('katalog')->name('katalog.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{katalog}/ubah', 'edit')->name('edit');
                Route::put('/{katalog}', 'update')->name('update');
                Route::delete('/{katalog}', 'destroy')->name('destroy');
                Route::post('/{katalog}/gambar', 'unggah')->name('unggah');
                Route::delete('/{katalog}/gambar/{gambar}', 'hapusGambar')->name('hapus-gambar');
            });

        Route::controller(PanelDokumenController::class)
            ->prefix('nota/{jenis}')->name('dokumen.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}', 'show')->name('show');
                Route::post('/{id}/baris', 'tambahBaris')->name('tambah-baris');
                Route::delete('/{id}/baris/{baris}', 'hapusBaris')->name('hapus-baris');
                Route::post('/{id}/bukukan', 'bukukan')->name('bukukan');
                Route::post('/{id}/batalkan', 'batalkan')->name('batalkan');
            });

        Route::controller(PanelStokBarangController::class)
            ->prefix('stok-barang')->name('stok-barang.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{stokBarang}/ubah', 'edit')->name('edit');
                Route::put('/{stokBarang}', 'update')->name('update');
                Route::delete('/{stokBarang}', 'destroy')->name('destroy');
            });

        Route::controller(PanelStokOpnameController::class)
            ->prefix('stok-opname')->name('stok-opname.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{stokOpname}', 'show')->name('show');
                Route::post('/{stokOpname}/bukukan', 'bukukan')->name('bukukan');
                Route::post('/{stokOpname}/batalkan', 'batalkan')->name('batalkan');
            });

        // Delapan layar master data ditangani satu controller; bentuknya
        // dideklarasikan di App\Support\DaftarMasterData.
        Route::controller(PanelMasterDataController::class)
            ->prefix('master/{jenis}')->name('master.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/tambah', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{id}/ubah', 'edit')->name('edit');
                Route::put('/{id}', 'update')->name('update');
                Route::delete('/{id}', 'destroy')->name('destroy');
            });

        Route::get('/', PanelDasborController::class)->name('beranda');
    });
});
