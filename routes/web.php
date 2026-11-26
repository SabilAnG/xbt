<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\TrackingController;
use App\Models\Product;
use App\Models\Warehouse;
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

        // Inventori: formula → rencana → pembelian → gudang → produksi
        Route::post('formula/{formula}/salin', [Admin\FormulaController::class, 'duplicate'])->name('formulas.duplicate');
        Route::resource('formula', Admin\FormulaController::class)->names('formulas')->parameters(['formula' => 'formula']);
        Route::post('rencana-produksi/{plan}/produksi', [Admin\ProductionPlanController::class, 'startProductions'])->name('plans.productions');
        Route::resource('rencana-produksi', Admin\ProductionPlanController::class)->names('plans')->parameters(['rencana-produksi' => 'plan']);
        Route::post('produksi/{production}/bukukan', [Admin\ProductionController::class, 'post'])->name('productions.post');
        Route::post('produksi/{production}/terima', [Admin\ProductionController::class, 'receive'])->name('productions.receive');
        Route::delete('produksi/penerimaan/{receipt}', [Admin\ProductionController::class, 'destroyReceipt'])->name('productions.receipts.destroy');
        Route::post('produksi/{production}/batalkan', [Admin\ProductionController::class, 'cancel'])->name('productions.cancel');
        Route::resource('produksi', Admin\ProductionController::class)->names('productions')->parameters(['produksi' => 'production']);
        Route::post('pembelian-bahan/{purchase}/batalkan-sisa', [Admin\MaterialPurchaseController::class, 'cancelOutstanding'])->name('purchases.cancel-outstanding');
        Route::resource('penerimaan', Admin\PurchaseReceiptController::class)->only(['index', 'create', 'store', 'show', 'destroy'])
            ->names('receipts')->parameters(['penerimaan' => 'receipt']);
        Route::resource('supplier', Admin\SupplierController::class)->only(['index', 'store', 'show', 'update', 'destroy'])
            ->names('suppliers')->parameters(['supplier' => 'supplier']);
        Route::post('pembelian-bahan/{purchase}/bayar', [Admin\MaterialPurchaseController::class, 'storePayment'])->name('purchases.payments.store');
        Route::delete('pembelian-bahan/pembayaran/{payment}', [Admin\MaterialPurchaseController::class, 'destroyPayment'])->name('purchases.payments.destroy');
        Route::resource('pembelian-bahan', Admin\MaterialPurchaseController::class)->only(['index', 'create', 'store', 'show', 'destroy'])
            ->names('purchases')->parameters(['pembelian-bahan' => 'purchase']);
        Route::get('master-item', [Admin\MasterItemController::class, 'index'])->name('master-items.index');
        Route::get('satuan', [Admin\UnitController::class, 'index'])->name('units.index');
        Route::post('satuan', [Admin\UnitController::class, 'store'])->name('units.store');
        Route::put('satuan/{unit}', [Admin\UnitController::class, 'update'])->name('units.update');
        Route::delete('satuan/{unit}', [Admin\UnitController::class, 'destroy'])->name('units.destroy');
        Route::resource('bahan', Admin\MaterialController::class)->names('materials')->parameters(['bahan' => 'material']);
        Route::get('gudang', [Admin\WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('gudang/{type}', [Admin\WarehouseController::class, 'show'])->whereIn('type', Warehouse::slugs())->name('warehouses.show');
        Route::resource('penyesuaian-stok', Admin\AdjustmentController::class)->only(['index', 'create', 'store', 'destroy'])
            ->names('adjustments')->parameters(['penyesuaian-stok' => 'adjustment']);

        Route::resource('produk', Admin\ItemController::class)->names('items')->parameters(['produk' => 'item']);
        Route::resource('merek', Admin\BrandController::class)->except('show')->names('brands')->parameters(['merek' => 'brand']);
        Route::resource('penjualan', Admin\SaleController::class)->except('show')->names('sales')->parameters(['penjualan' => 'sale']);
        Route::get('laporan-keuangan', Admin\FinanceReportController::class)->name('finance.index');
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
        Route::post('website/produk/pratinjau', [Admin\WebProductController::class, 'preview'])->name('web-products.preview');
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

        // Pengaturan produksi: lebar potong gergaji.
        Route::get('pengaturan-produksi', [Admin\ProductionSettingController::class, 'edit'])->name('production-settings.edit');
        Route::put('pengaturan-produksi', [Admin\ProductionSettingController::class, 'update'])->name('production-settings.update');
    });
});
