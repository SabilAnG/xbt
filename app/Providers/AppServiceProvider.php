<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tabel tenancy hanya milik pusat. Skema aplikasi di database/migrations
        // dipakai bersama — pusat menjalankannya sebagai toko sendiri, tiap
        // partner menjalankannya lagi di database miliknya. Yang di sini tidak
        // ikut ke sana: partner tidak punya daftar partner.
        $this->loadMigrationsFrom(database_path('migrations/central'));
    }
}
