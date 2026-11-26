<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Struktur menu admin: dipakai sidebar, breadcrumb di topbar, dan pencarian
 * menu cepat. Satu sumber supaya label & ikon tidak berbeda antar tempat.
 */
class AdminNavigation
{
    /**
     * Ikon garis 24×24 (stroke), cukup path-nya.
     */
    private const ICONS = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z',
        'box' => 'M21 8 12 3 3 8m18 0-9 5m9-5v8l-9 5m0-8L3 8m9 5v8M3 8v8l9 5',
        'layers' => 'm12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5',
        'beaker' => 'M9 3h6m-5 0v6L4.5 18.5A1.7 1.7 0 0 0 6 21h12a1.7 1.7 0 0 0 1.5-2.5L14 9V3M7 15h10',
        'factory' => 'M3 21V10l5 3V10l5 3V6l8 4v11H3Zm4-4h2m4 0h2',
        'cart' => 'M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2M9 20.5a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Zm8 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Z',
        'bag' => 'M6 7h12l1 14H5L6 7Zm3 0a3 3 0 0 1 6 0',
        'sliders' => 'M4 6h10m4 0h2M4 12h4m4 0h8M4 18h12m4 0h0M14 4v4M8 10v4m8 2v4',
        'wallet' => 'M3 7a2 2 0 0 1 2-2h13v4M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2Zm13 7h.01',
        'wrench' => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z',
        'cog' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.3l2-1.6-2-3.4-2.4 1a7.5 7.5 0 0 0-2.2-1.3L14.4 3h-4l-.4 2.4a7.5 7.5 0 0 0-2.2 1.3l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.6l-2 1.6 2 3.4 2.4-1a7.5 7.5 0 0 0 2.2 1.3l.4 2.4h4l.4-2.4a7.5 7.5 0 0 0 2.2-1.3l2.4 1 2-3.4-2-1.6c.1-.4.1-.9.1-1.3Z',
        'building' => 'M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16m0 0h4V11h-4M4 21h12M8 7h4M8 11h4M8 15h4',
        'tag' => 'M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9Zm5-4.5h.01',
        'bike' => 'M5.5 18a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm13 0a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM5.5 14.5 9 8h5l4.5 6.5M12 8l-2.5-3H7m7 3 1.5-3H18',
        'video' => 'm15 10 5-3v10l-5-3M4 6h11v12H4z',
        'coins' => 'M9 14c3.3 0 6-1.3 6-3s-2.7-3-6-3-6 1.3-6 3 2.7 3 6 3Zm-6-3v4c0 1.7 2.7 3 6 3s6-1.3 6-3v-4m-6-6c0-1.7 2.7-3 6-3s6 1.3 6 3-2.7 3-6 3m6-3v8c0 1.4-1.8 2.5-4.3 2.9',
        'calendar' => 'M8 3v3m8-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm4 9 2 2 4-4',
        'banknote' => 'M3 7h18v10H3zm9 7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM6 10v.01M18 14v.01',
        'users' => 'M16 21v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1m14-10a4 4 0 0 0 0-8m6 18v-1a4 4 0 0 0-3-3.9M9 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
        'list' => 'M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01',
        'history' => 'M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 2',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.5-2.5 3.5-5.5 3.5-9S14.5 5.5 12 3m0 18c-2.5-2.5-3.5-5.5-3.5-9S9.5 5.5 12 3M3.5 9h17m-17 6h17',
        'text' => 'M4 6h16M4 10h16M4 14h10M4 18h7',
        'image' => 'M4 5h16v14H4zm0 11 4.5-4.5a1.5 1.5 0 0 1 2 0L15 16m-1.5-1.5 1.5-1.5a1.5 1.5 0 0 1 2 0L20 16M15 9h.01',
        'puzzle' => 'M10 4a2 2 0 1 1 4 0v1h4a1 1 0 0 1 1 1v4h-1a2 2 0 1 0 0 4h1v4a1 1 0 0 1-1 1h-4v-1a2 2 0 1 0-4 0v1H6a1 1 0 0 1-1-1v-4h1a2 2 0 1 0 0-4H5V6a1 1 0 0 1 1-1h4V4Z',
        'warehouse' => 'M3 21V9l9-6 9 6v12M7 21v-8h10v8M7 17h10',
        'arrows' => 'M7 7h13m0 0-4-4m4 4-4 4M17 17H4m0 0 4-4m-4 4 4 4',
        'inbox' => 'M3 13h5l1.5 3h5L16 13h5M5 5h14l2 8v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6l2-8Z',
        'truck' => 'M3 6h11v10H3zm11 4h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z',
    ];

    /** Path ikon untuk dipakai komponen lain (kartu statistik, tombol). */
    public static function icon(string $name): string
    {
        return self::ICONS[$name] ?? self::ICONS['box'];
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, params: array<string, string>, active: string, icon: string}>}>
     */
    public static function groups(): array
    {
        $item = fn (string $label, string $route, string $active, string $icon, array $params = []) => compact('label', 'route', 'params', 'active') + ['icon' => self::ICONS[$icon]];

        return [
            ['label' => 'Ringkasan', 'items' => [
                $item('Dashboard', 'admin.dashboard', 'admin.dashboard', 'home'),
            ]],
            // Urutan grup mengikuti EMS: Pembelian → Penjualan → Katalog → Keuangan → Gudang → Produksi.
            ['label' => 'Pembelian', 'items' => [
                $item('Pembelian', 'admin.purchases.index', 'admin.purchases.*', 'bag'),
                $item('Penerimaan', 'admin.receipts.index', 'admin.receipts.*', 'inbox'),
                $item('Supplier', 'admin.suppliers.index', 'admin.suppliers.*', 'users'),
            ]],
            ['label' => 'Penjualan', 'items' => [
                $item('Stok Siap Jual', 'admin.warehouses.show', 'admin.warehouses.show:finish-good', 'box', ['type' => 'finish-good']),
                $item('Penjualan', 'admin.sales.index', 'admin.sales.*', 'cart'),
            ]],
            ['label' => 'Katalog', 'items' => [
                $item('Item', 'admin.master-items.index', 'admin.master-items.*|admin.materials.*|admin.items.*', 'layers'),
                $item('Satuan', 'admin.units.index', 'admin.units.*', 'list'),
                $item('Merek', 'admin.brands.index', 'admin.brands.*', 'tag'),
                $item('Data Motor', 'admin.motor-options.index', 'admin.motor-options.*', 'bike'),
            ]],
            ['label' => 'Keuangan', 'items' => [
                $item('Dompet', 'admin.wallets.index', 'admin.wallets.*', 'wallet'),
                $item('Pengeluaran', 'admin.expenses.index', 'admin.expenses.*', 'banknote'),
                $item('Laporan Keuangan', 'admin.finance.index', 'admin.finance.*', 'coins'),
            ]],
            ['label' => 'Gudang', 'items' => [
                $item('Gudang', 'admin.warehouses.index', 'admin.warehouses.index|admin.warehouses.show:bahan-baku|admin.warehouses.show:setengah-jadi|admin.warehouses.show:aksesoris', 'warehouse'),
                $item('Pindah Barang', 'admin.transfers.index', 'admin.transfers.*', 'arrows'),
                $item('Stok Opname', 'admin.stock-opname.index', 'admin.stock-opname.*', 'list'),
                $item('Penyesuaian Stok', 'admin.adjustments.index', 'admin.adjustments.*', 'sliders'),
            ]],
            ['label' => 'Produksi', 'items' => [
                $item('Formula', 'admin.formulas.index', 'admin.formulas.*', 'beaker'),
                $item('Data Komponen', 'admin.component-options.index', 'admin.component-options.*', 'list'),
                $item('Jasa Vendor', 'admin.vendor-services.index', 'admin.vendor-services.*', 'wrench'),
                $item('Data Ongkos', 'admin.cost-options.index', 'admin.cost-options.*', 'coins'),
                $item('Rencana Produksi', 'admin.plans.index', 'admin.plans.*', 'calendar'),
                $item('Produksi', 'admin.productions.index', 'admin.productions.*', 'factory'),
                $item('Laporan Produksi', 'admin.reports.production', 'admin.reports.production', 'coins'),
                $item('Vendor Produksi', 'admin.warehouses.show', 'admin.warehouses.show:di-vendor', 'truck', ['type' => 'di-vendor']),
                $item('Hasil Samping', 'admin.warehouses.show', 'admin.warehouses.show:stok-sisa', 'puzzle', ['type' => 'stok-sisa']),
                $item('Pengaturan Produksi', 'admin.production-settings.edit', 'admin.production-settings.*', 'cog'),
            ]],
            ['label' => 'Pekerja', 'items' => [
                $item('Pekerja', 'admin.workers.index', 'admin.workers.*', 'users'),
                $item('Absensi', 'admin.attendances.index', 'admin.attendances.*', 'calendar'),
                $item('Penggajian', 'admin.payrolls.index', 'admin.payrolls.*', 'banknote'),
            ]],
            ['label' => 'Website', 'items' => [
                $item('Pengaturan Situs', 'admin.site-settings.edit', 'admin.site-settings.*', 'globe'),
                $item('Konten Halaman', 'admin.content-blocks.index', 'admin.content-blocks.*', 'text'),
                $item('Katalog Produk', 'admin.web-products.index', 'admin.web-products.*', 'image'),
                $item('Pesanan & Tracking', 'admin.orders.index', 'admin.orders.*', 'truck'),
            ]],
            ['label' => 'Pengguna', 'items' => [
                $item('Pengguna & Akses', 'admin.users.index', 'admin.users.index', 'users'),
                $item('Riwayat Aktivitas', 'admin.users.activity', 'admin.users.activity', 'list'),
            ]],
        ];
    }

    /**
     * Awalan nama route → grup menu, untuk hak akses. Route yang tidak ada di
     * sini (dashboard, logout) boleh dibuka semua peran.
     */
    private const ROUTE_GROUPS = [
        'Pembelian' => ['purchases', 'receipts', 'suppliers'],
        'Penjualan' => ['sales'],
        'Katalog' => ['master-items', 'materials', 'items', 'units', 'brands', 'motor-options'],
        'Keuangan' => ['wallets', 'expenses', 'finance'],
        'Gudang' => ['warehouses', 'transfers', 'adjustments', 'stock-opname'],
        'Produksi' => ['formulas', 'component-options', 'vendor-services', 'cost-options', 'plans', 'productions', 'production-settings', 'reports'],
        'Pekerja' => ['workers', 'attendances', 'payrolls'],
        'Pengguna' => ['users'],
        'Website' => ['site-settings', 'content-blocks', 'web-products', 'orders', 'shipments', 'shipment-events'],
    ];

    /** Grup menu pemilik route ini (null = terbuka untuk semua yang login). */
    public static function groupOfRoute(string $routeName): ?string
    {
        $segment = explode('.', $routeName)[1] ?? '';

        foreach (self::ROUTE_GROUPS as $group => $prefixes) {
            if (in_array($segment, $prefixes, true)) {
                return $group;
            }
        }

        return null;
    }

    /** Boleh membuka route ini? Stok siap jual juga terbuka untuk bagian penjualan. */
    public static function allows(?User $user, string $routeName, array $parameters = []): bool
    {
        $group = self::groupOfRoute($routeName);

        if (! $user || $group === null || $user->canOpenGroup($group)) {
            return $user !== null || $group === null;
        }

        return $routeName === 'admin.warehouses.show' && ($parameters['type'] ?? null) === 'finish-good' && $user->canOpenGroup('Penjualan');
    }

    /**
     * Menu yang terlihat untuk pengguna ini.
     *
     * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    public static function groupsFor(?User $user): array
    {
        return collect(static::groups())
            ->map(fn (array $group) => ['items' => array_values(array_filter($group['items'], fn (array $item) => self::allows($user, $item['route'], $item['params'])))] + $group)
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()->all();
    }

    /**
     * Pola "nama.route:param" aktif hanya bila parameter kind/master/type
     * cocok, supaya menu yang memakai satu route (mis. halaman gudang) tidak
     * menyala bersamaan.
     *
     * @param  array{active: string}  $item
     */
    public static function isActive(array $item, Request $request): bool
    {
        foreach (explode('|', $item['active']) as $pattern) {
            [$name, $param] = array_pad(explode(':', $pattern, 2), 2, null);

            if ($request->routeIs($name) && ($param === null || in_array($param, [$request->route('kind'), $request->route('master'), $request->route('type')], true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grup & menu yang sedang dibuka, untuk breadcrumb.
     *
     * @return array{group: string, item: array<string, mixed>}|null
     */
    public static function current(Request $request): ?array
    {
        foreach (static::groups() as $group) {
            foreach ($group['items'] as $item) {
                if (static::isActive($item, $request)) {
                    return ['group' => $group['label'], 'item' => $item];
                }
            }
        }

        return null;
    }
}
