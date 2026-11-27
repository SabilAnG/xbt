<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: SecurityHeaders::class);

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Token formulir kedaluwarsa (halaman dibiarkan terbuka lama, sesi habis):
        // jangan tampilkan "419 Page Expired", kembalikan ke formulir dengan pesan.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException || $request->expectsJson()) {
                return null;
            }

            if ($request->routeIs('admin.logout')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }

            if ($request->routeIs('login.attempt')) {
                return redirect()->route('login')->withInput($request->only('email', 'remember'))
                    ->withErrors(['email' => 'Halaman login sudah terlalu lama terbuka. Silakan masuk lagi.']);
            }

            return back()->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->with('error', 'Sesi halaman sudah kedaluwarsa, data belum tersimpan. Silakan kirim ulang.');
        });
    })->create();
