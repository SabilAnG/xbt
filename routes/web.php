<?php

use App\Http\Controllers\PartnerRegistrationController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\TrackingController;
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

Route::view('/', 'pages.home')->name('home');
Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/workshop', 'pages.workshop')->name('workshop');
Route::view('/tracking', 'pages.tracking')->name('tracking');
Route::view('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');

// Jadi Partner: buka toko sendiri di atas aplikasi ini.
Route::get('/jadi-partner', [PartnerRegistrationController::class, 'create'])->name('partner.register');
Route::post('/jadi-partner', [PartnerRegistrationController::class, 'store'])->name('partner.register.store');
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

/*
|--------------------------------------------------------------------------
| Endpoints the front-end markup calls
|--------------------------------------------------------------------------
*/

Route::post('/tracking/track', [TrackingController::class, 'track'])->name('tracking.track');

Route::get('/redirect/away', [RedirectController::class, 'away'])->name('redirect.away');
Route::get('/redirect/product-direct-checkout', [RedirectController::class, 'productDirectCheckout'])
    ->name('redirect.product-checkout');
