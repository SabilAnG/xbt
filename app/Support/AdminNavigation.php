<?php

namespace App\Support;

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
            ['label' => 'Stok', 'items' => [
                $item('Stok Produk', 'admin.items.index', 'admin.items.*', 'box'),
                $item('Stok Bahan', 'admin.materials.index', 'admin.materials.*', 'layers'),
                $item('Formula (HPP)', 'admin.formulas.index', 'admin.formulas.*', 'beaker'),
            ]],
            ['label' => 'Transaksi', 'items' => [
                $item('Produksi', 'admin.productions.index', 'admin.productions.*', 'factory'),
                $item('Penjualan', 'admin.sales.index', 'admin.sales.*', 'cart'),
                $item('Pembelian Bahan', 'admin.purchases.index', 'admin.purchases.*', 'bag'),
                $item('Penyesuaian Bahan', 'admin.adjustments.index', 'admin.adjustments.*', 'sliders'),
                $item('Pengeluaran', 'admin.expenses.index', 'admin.expenses.*', 'wallet'),
            ]],
            ['label' => 'Host Live & Tukang', 'items' => [
                $item('Jam Live', 'admin.live-sessions.index', 'admin.live-sessions.*', 'video'),
                $item('Gaji Host', 'admin.payrolls.index', 'admin.payrolls.*:host', 'coins', ['kind' => 'host']),
                $item('Absen Tukang', 'admin.attendance.index', 'admin.attendance.*', 'calendar'),
                $item('Gaji Tukang', 'admin.payrolls.index', 'admin.payrolls.*:tukang', 'banknote', ['kind' => 'tukang']),
                $item('Data Host & Tukang', 'admin.hosts.index', 'admin.hosts.*|admin.workers.*', 'users'),
            ]],
            ['label' => 'Master HPP', 'items' => [
                $item('Komponen Biaya', 'admin.masters.index', 'admin.masters.*:komponen-biaya', 'wrench', ['master' => 'komponen-biaya']),
                $item('Mesin', 'admin.masters.index', 'admin.masters.*:mesin', 'cog', ['master' => 'mesin']),
                $item('Overhead Bulanan', 'admin.masters.index', 'admin.masters.*:overhead', 'building', ['master' => 'overhead']),
                $item('Tingkatan Harga', 'admin.masters.index', 'admin.masters.*:tingkatan-harga', 'tag', ['master' => 'tingkatan-harga']),
                $item('Type & Merek Motor', 'admin.masters.index', 'admin.masters.*:type-motor|admin.masters.*:merek-motor', 'bike', ['master' => 'type-motor']),
                $item('Pengaturan Produksi', 'admin.production-settings.edit', 'admin.production-settings.*', 'sliders'),
            ]],
            ['label' => 'Referensi', 'items' => [
                $item('Daftar Pilihan', 'admin.list-options.index', 'admin.list-options.*', 'list'),
                $item('Riwayat Pengadaan', 'admin.procurement.index', 'admin.procurement.*', 'history'),
            ]],
            ['label' => 'Website', 'items' => [
                $item('Pengaturan Situs', 'admin.site-settings.edit', 'admin.site-settings.*', 'globe'),
                $item('Konten Halaman', 'admin.content-blocks.index', 'admin.content-blocks.*', 'text'),
                $item('Katalog Produk', 'admin.web-products.index', 'admin.web-products.*', 'image'),
                $item('Pesanan & Tracking', 'admin.orders.index', 'admin.orders.*', 'truck'),
            ]],
        ];
    }

    /**
     * Pola "nama.route:param" aktif hanya bila parameter kind/master cocok,
     * supaya Gaji Host dan Gaji Tukang (satu route) tidak menyala bersamaan.
     *
     * @param  array{active: string}  $item
     */
    public static function isActive(array $item, Request $request): bool
    {
        foreach (explode('|', $item['active']) as $pattern) {
            [$name, $param] = array_pad(explode(':', $pattern, 2), 2, null);

            if ($request->routeIs($name) && ($param === null || in_array($param, [$request->route('kind'), $request->route('master')], true))) {
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
