<?php

namespace App\Providers\Filament;

use App\Filament\Auth\MasukPanel;
use App\Filament\Widgets\CashflowChart;
use App\Filament\Widgets\DraftDocuments;
use App\Filament\Widgets\InventoryOverview;
use App\Filament\Widgets\LowStockItems;
use App\Http\Middleware\KenaliPartnerDariSesi;
use App\Http\Middleware\KenaliPartnerDariSubdomain;
use App\Http\Middleware\PastikanPartnerAktif;
use App\Models\Setting;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(MasukPanel::class)
            ->profile(isSimple: false)

            // Oranye mengikuti --primary-color situs publik.
            ->colors([
                'primary' => Color::hex('#ff6b00'),
                'gray' => Color::Zinc,
                'danger' => Color::Red,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])
            // Mode terang jadi bawaan — layar bengkel biasanya dipakai siang
            // hari dan angka hitam di atas putih lebih enak dibaca. Tombol
            // ganti tema tetap ada di menu profil bagi yang suka gelap.
            ->defaultThemeMode(ThemeMode::Light)

            ->brandName(fn () => Setting::get('site.name', config('app.name')))
            // Logo situs bertulisan putih: pas di header gelap, hilang di
            // topbar terang. Karena itu tiap mode punya berkasnya sendiri.
            ->brandLogo(fn () => asset(Setting::get('site.logo_light', 'assets/images/logo-dark.png')))
            ->darkModeBrandLogo(fn () => asset(Setting::get('site.logo', 'assets/images/logo-white.png')))
            ->brandLogoHeight('2rem')
            ->favicon(fn () => asset(Setting::get('site.favicon', 'favicon.ico')))

            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)

            // Urutan grup dibuat mengikuti alur kerja harian: transaksi dulu,
            // lalu aset & laporan, baru data acuan yang jarang disentuh.
            ->navigationGroups([
                NavigationGroup::make('Partner')->icon('heroicon-o-building-storefront'),
                NavigationGroup::make('Operasional')->icon('heroicon-o-arrows-right-left'),
                NavigationGroup::make('Produksi')->icon('heroicon-o-wrench-screwdriver'),
                NavigationGroup::make('Aset')->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Master Data')->icon('heroicon-o-rectangle-stack')->collapsed(),
                NavigationGroup::make('Toko Online')->icon('heroicon-o-globe-alt')->collapsed(),
                NavigationGroup::make('Website')->icon('heroicon-o-cog-6-tooth')->collapsed(),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                InventoryOverview::class,
                CashflowChart::class,
                LowStockItems::class,
                DraftDocuments::class,
            ])

            // Lapisan kaca di atas gaya bawaan Filament, bukan tema yang
            // dibangun ulang — supaya pembaruan Filament tetap terpakai.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): View => view('filament.tema-kaca'),
            )

            // Terang/gelap satu klik di topbar. Pilihan yang sama ada di menu
            // profil, tapi terkubur dua klik ke dalam.
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): View => view('filament.tombol-tema'),
            )

            // Pengingat masa pakai, di atas isi halaman. Tanpa ini partner baru
            // tahu langganannya habis ketika tokonya sudah mati.
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): View => view('filament.peringatan-masa-pakai'),
            )

            // Tombol buku di pojok kanan bawah: panduan menu yang sedang dibuka.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): View => view('filament.tutorial'),
            )

            ->middleware([
                // Paling depan: koneksi database harus sudah pindah ke milik
                // partner sebelum sesi dibaca, sebelum login diperiksa, dan
                // sebelum menu apa pun dibangun.
                // Alamat lebih tegas daripada sesi, jadi diperiksa lebih dulu.
                KenaliPartnerDariSubdomain::class,

                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,

                // Baru di sini: partner yang masuk lewat alamat panel yang sama
                // dikenali dari sesinya, dan sesi baru ada sesudah baris di atas.
                KenaliPartnerDariSesi::class,
                PastikanPartnerAktif::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
