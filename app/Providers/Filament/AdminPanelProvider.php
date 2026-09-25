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

            // Ungu-lavender dengan aksen mint, mengikuti contoh tampilan yang
            // diminta. Catatan: ini MEMUTUS kaitan warna dengan situs publik
            // Hypersonic, yang oranye (#ff6b00) — dan logo panel masih
            // beraksen oranye. Kembalikan di sini kalau kaitan itu lebih
            // penting daripada tampilannya.
            ->colors([
                'primary' => Color::hex('#7c5cff'),
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'success' => Color::hex('#22c7a9'),
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
            //
            // Produksi dipecah dua. Yang dibuka tiap hari tetap di "Produksi",
            // urut seperti pekerjaannya berjalan. Yang diisi sekali lalu nyaris
            // tidak disentuh lagi turun ke "Pengaturan Produksi" yang tertutup.
            // Sembilan menu dalam satu daftar datar membuat orang baru tidak
            // tahu harus mulai dari mana, dan yang paling sering dipakai justru
            // tenggelam di antara yang paling jarang.
            ->navigationGroups([
                NavigationGroup::make('Partner')->icon('heroicon-o-building-storefront'),
                NavigationGroup::make('Operasional')->icon('heroicon-o-arrows-right-left'),
                NavigationGroup::make('Produksi')->icon('heroicon-o-wrench-screwdriver'),
                NavigationGroup::make('Pengaturan Produksi')
                    ->icon('heroicon-o-adjustments-horizontal')->collapsed(),
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

            // Tema dikompilasi Vite, bukan disuntik lewat STYLES_AFTER. Gaya
            // Filament tetap dipakai apa adanya; berkas tema hanya menumpang
            // di atasnya, jadi pembaruan Filament tidak perlu disalin ulang.
            //
            // Perubahan berkas tema baru terlihat setelah `npm run build`.
            ->viteTheme('resources/css/filament/admin/theme.css')

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
