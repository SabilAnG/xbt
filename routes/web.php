<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\TrackingController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
| Static mirror of the hypersonic.id front-end. Product detail pages are
| still flat Blade views; they move to the database once the admin
| Product resource is in place.
*/

Route::get('/', function () {
    return view('pages.home', [
        // Katalog untuk beranda versi HP (gaya aplikasi e-commerce).
        'catalog' => Product::with(['images', 'item'])->active()->ordered()->get(),
    ]);
})->name('home');
Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/workshop', 'pages.workshop')->name('workshop');
Route::view('/tracking', 'pages.tracking')->name('tracking');
Route::view('/privacy-policy', 'pages.privacy-policy')->name('privacy-policy');
Route::view('/terms-and-conditions', 'pages.terms-and-conditions')->name('terms-and-conditions');

Route::view('/keranjang', 'pages.cart')->name('cart');

Route::get('/products', function () {
    return view('pages.products', [
        'products' => Product::with(['images', 'item'])->active()->ordered()->get(),
    ]);
})->name('products.index');

Route::get('/products/{product:slug}', function (Product $product) {
    abort_unless($product->is_active, 404);

    return view('pages.products.show', [
        'product' => $product->load(['images', 'item']),
        'others' => Product::with(['images', 'item'])->active()->ordered()->whereKeyNot($product->id)->limit(6)->get(),
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

/*
|--------------------------------------------------------------------------
| Admin panel (Blade, tanpa Filament)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    });

    Route::middleware('auth')->name('admin.')->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('produk', Admin\ItemController::class)->names('items')->parameters(['produk' => 'item']);
        Route::resource('bahan', Admin\MaterialController::class)->names('materials')->parameters(['bahan' => 'material']);
        Route::resource('merek', Admin\BrandController::class)->except('show')->names('brands')->parameters(['merek' => 'brand']);
        Route::resource('resep', Admin\FormulaController::class)->except('show')->names('formulas')->parameters(['resep' => 'formula']);
        Route::resource('produksi', Admin\ProductionController::class)->only(['index', 'create', 'store', 'show', 'destroy'])
            ->names('productions')->parameters(['produksi' => 'production']);
        Route::resource('penjualan', Admin\SaleController::class)->except('show')->names('sales')->parameters(['penjualan' => 'sale']);
        Route::resource('pembelian-bahan', Admin\MaterialPurchaseController::class)->only(['index', 'create', 'store', 'destroy'])
            ->names('purchases')->parameters(['pembelian-bahan' => 'purchase']);
        Route::resource('penyesuaian-bahan', Admin\AdjustmentController::class)->only(['index', 'create', 'store', 'destroy'])
            ->names('adjustments')->parameters(['penyesuaian-bahan' => 'adjustment']);
        Route::get('dompet/transfer', [Admin\WalletController::class, 'createTransfer'])->name('wallets.transfer');
        Route::post('dompet/transfer', [Admin\WalletController::class, 'storeTransfer'])->name('wallets.transfer.store');
        Route::delete('dompet/transfer/{transfer}', [Admin\WalletController::class, 'destroyTransfer'])->name('wallets.transfers.destroy');
        Route::get('dompet/mutasi', [Admin\WalletController::class, 'createManual'])->name('wallets.manual');
        Route::post('dompet/mutasi', [Admin\WalletController::class, 'storeManual'])->name('wallets.manual.store');
        Route::delete('dompet/mutasi/{transaction}', [Admin\WalletController::class, 'destroyTransaction'])->name('wallets.transactions.destroy');
        Route::resource('dompet', Admin\WalletController::class)->names('wallets')->parameters(['dompet' => 'wallet']);
        Route::resource('pengeluaran', Admin\ExpenseController::class)->except('show')->names('expenses')->parameters(['pengeluaran' => 'expense']);

        // Website
        Route::get('website/pengaturan', [Admin\SiteSettingController::class, 'edit'])->name('site-settings.edit');
        Route::put('website/pengaturan', [Admin\SiteSettingController::class, 'update'])->name('site-settings.update');
        Route::resource('website/konten', Admin\ContentBlockController::class)->only(['index', 'edit', 'update'])
            ->names('content-blocks')->parameters(['konten' => 'contentBlock']);
        Route::get('website/produk/dari-stok', [Admin\WebProductController::class, 'fromStock'])->name('web-products.from-stock');
        Route::post('website/produk/dari-stok/{item}', [Admin\WebProductController::class, 'storeFromStock'])->name('web-products.from-stock.store');
        Route::resource('website/produk', Admin\WebProductController::class)->except('show')
            ->names('web-products')->parameters(['produk' => 'webProduct']);
        Route::resource('website/pesanan', Admin\OrderController::class)->except('show')
            ->names('orders')->parameters(['pesanan' => 'order']);
        Route::post('website/pesanan/{order}/pengiriman', [Admin\OrderController::class, 'storeShipment'])->name('orders.shipments.store');
        Route::put('website/pengiriman/{shipment}', [Admin\OrderController::class, 'updateShipment'])->name('shipments.update');
        Route::delete('website/pengiriman/{shipment}', [Admin\OrderController::class, 'destroyShipment'])->name('shipments.destroy');
        Route::post('website/pengiriman/{shipment}/riwayat', [Admin\OrderController::class, 'storeEvent'])->name('shipments.events.store');
        Route::delete('website/riwayat/{event}', [Admin\OrderController::class, 'destroyEvent'])->name('shipment-events.destroy');

        // Sheet Daftar, Host Live, Tukang, Riwayat Pengadaan
        Route::get('daftar-pilihan', [Admin\ListOptionController::class, 'index'])->name('list-options.index');
        Route::post('daftar-pilihan', [Admin\ListOptionController::class, 'store'])->name('list-options.store');
        Route::delete('daftar-pilihan/{listOption}', [Admin\ListOptionController::class, 'destroy'])->name('list-options.destroy');
        Route::post('daftar-pilihan/kategori', [Admin\ListOptionController::class, 'storeCategory'])->name('expense-categories.store');
        Route::delete('daftar-pilihan/kategori/{category}', [Admin\ListOptionController::class, 'destroyCategory'])->name('expense-categories.destroy');

        Route::get('jam-live', [Admin\HostController::class, 'sessions'])->name('live-sessions.index');
        Route::get('jam-live/create', [Admin\HostController::class, 'createSession'])->name('live-sessions.create');
        Route::post('jam-live', [Admin\HostController::class, 'storeSession'])->name('live-sessions.store');
        Route::get('jam-live/{session}/edit', [Admin\HostController::class, 'editSession'])->name('live-sessions.edit');
        Route::put('jam-live/{session}', [Admin\HostController::class, 'updateSession'])->name('live-sessions.update');
        Route::delete('jam-live/{session}', [Admin\HostController::class, 'destroySession'])->name('live-sessions.destroy');
        Route::get('data-host', [Admin\HostController::class, 'index'])->name('hosts.index');
        Route::post('data-host', [Admin\HostController::class, 'store'])->name('hosts.store');
        Route::put('data-host/{host}', [Admin\HostController::class, 'update'])->name('hosts.update');
        Route::delete('data-host/{host}', [Admin\HostController::class, 'destroy'])->name('hosts.destroy');

        Route::get('absen-tukang', [Admin\WorkerController::class, 'attendance'])->name('attendance.index');
        Route::get('absen-tukang/create', [Admin\WorkerController::class, 'createDay'])->name('attendance.create');
        Route::post('absen-tukang', [Admin\WorkerController::class, 'storeDay'])->name('attendance.store');
        Route::get('absen-tukang/{day}/edit', [Admin\WorkerController::class, 'editDay'])->name('attendance.edit');
        Route::put('absen-tukang/{day}', [Admin\WorkerController::class, 'updateDay'])->name('attendance.update');
        Route::delete('absen-tukang/{day}', [Admin\WorkerController::class, 'destroyDay'])->name('attendance.destroy');
        Route::get('data-tukang', [Admin\WorkerController::class, 'index'])->name('workers.index');
        Route::post('data-tukang', [Admin\WorkerController::class, 'store'])->name('workers.store');
        Route::put('data-tukang/{worker}', [Admin\WorkerController::class, 'update'])->name('workers.update');
        Route::delete('data-tukang/{worker}', [Admin\WorkerController::class, 'destroy'])->name('workers.destroy');

        Route::get('gaji/{kind}', [Admin\PayrollController::class, 'index'])->whereIn('kind', ['host', 'tukang'])->name('payrolls.index');
        Route::get('gaji/{kind}/create', [Admin\PayrollController::class, 'create'])->whereIn('kind', ['host', 'tukang'])->name('payrolls.create');
        Route::post('gaji/{kind}', [Admin\PayrollController::class, 'store'])->whereIn('kind', ['host', 'tukang'])->name('payrolls.store');
        Route::get('gaji/{kind}/{id}/edit', [Admin\PayrollController::class, 'edit'])->whereIn('kind', ['host', 'tukang'])->name('payrolls.edit');
        Route::put('gaji/{kind}/{id}', [Admin\PayrollController::class, 'update'])->whereIn('kind', ['host', 'tukang'])->name('payrolls.update');
        Route::delete('gaji/{kind}/{id}', [Admin\PayrollController::class, 'destroy'])->whereIn('kind', ['host', 'tukang'])->name('payrolls.destroy');

        Route::get('riwayat-pengadaan', Admin\ProcurementHistoryController::class)->name('procurement.index');

        // Master pendukung Formula (HPP)
        Route::get('pengaturan-produksi', [Admin\ProductionSettingController::class, 'edit'])->name('production-settings.edit');
        Route::put('pengaturan-produksi', [Admin\ProductionSettingController::class, 'update'])->name('production-settings.update');
        Route::get('master/{master}', [Admin\MasterController::class, 'index'])->name('masters.index');
        Route::get('master/{master}/create', [Admin\MasterController::class, 'create'])->name('masters.create');
        Route::post('master/{master}', [Admin\MasterController::class, 'store'])->name('masters.store');
        Route::get('master/{master}/{id}/edit', [Admin\MasterController::class, 'edit'])->whereNumber('id')->name('masters.edit');
        Route::put('master/{master}/{id}', [Admin\MasterController::class, 'update'])->whereNumber('id')->name('masters.update');
        Route::delete('master/{master}/{id}', [Admin\MasterController::class, 'destroy'])->whereNumber('id')->name('masters.destroy');
    });
});
