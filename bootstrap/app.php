<?php

use App\Http\Middleware\HanyaPusat;
use App\Http\Middleware\KenaliPartnerDariSesi;
use App\Http\Middleware\KenaliPartnerDariSubdomain;
use App\Http\Middleware\PastikanPartnerAktif;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Di produksi aplikasi berada di belakang nginx host yang memegang TLS.
        // Tanpa ini Laravel menganggap permintaan datang lewat http biasa, lalu
        // membangkitkan URL aset berawalan http di halaman https — browser
        // memblokirnya sebagai mixed content, dan panel admin kehilangan
        // JavaScript-nya sehingga tombol login tidak berfungsi.
        //
        // Port container hanya di-bind ke 127.0.0.1, jadi satu-satunya sumber
        // permintaan memang proxy tersebut.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Dipasang di route milik partner — halaman tokonya maupun panelnya.
        // Yang dikunci hanya pintunya; datanya tetap utuh.
        $middleware->alias([
            'partner.aktif' => PastikanPartnerAktif::class,
            'pusat' => HanyaPusat::class,
        ]);

        // Halaman publik melayani dua sisi: situs Hypersonic di alamat pusat,
        // dan toko partner di subdomainnya. Yang membedakan hanya database di
        // belakangnya — jadi pengenalannya harus terjadi sebelum apa pun
        // dibaca, termasuk sesi.
        $middleware->prependToGroup('web', [
            KenaliPartnerDariSubdomain::class,
        ]);

        // Di belakang, karena keduanya perlu sesi yang sudah dibaca. Ini juga
        // yang menjangkau route Livewire: ia tidak memakai middleware panel,
        // dan tanpa pengenalan di sini setiap permintaan Livewire dari panel
        // partner membaca user bernomor sama di database PUSAT — yakni admin
        // Hypersonic. Sesinya lalu dibuang karena sandinya tidak cocok, dan
        // partner terlempar ke halaman login sedetik setelah masuk.
        $middleware->appendToGroup('web', [
            KenaliPartnerDariSesi::class,
            PastikanPartnerAktif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
