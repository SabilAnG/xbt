<?php

use App\Http\Controllers\AdvertisementClickController;
use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\TrackingController;
use App\Http\Middleware\KenaliPartnerDariPath;
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
