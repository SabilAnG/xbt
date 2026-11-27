<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk setiap halaman yang dibuat Laravel.
 *
 * X-Frame-Options, nosniff dan Referrer-Policy dipasang nginx (berlaku juga
 * untuk file statis/unggahan), jadi di sini hanya yang tidak dipasang nginx —
 * agar header tidak terkirim dobel.
 */
class SecurityHeaders
{
    /** Asal luar yang memang dipakai halaman (font, peta, video). */
    private const SOURCES = [
        'style-src' => ['https://fonts.bunny.net', 'https://fonts.googleapis.com'],
        'font-src' => ['https://fonts.bunny.net', 'https://fonts.gstatic.com'],
        'frame-src' => ['https://www.google.com', 'https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://player.vimeo.com'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy(), false);
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(), usb=()', false);

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }

    private function policy(): string
    {
        // Saat `npm run dev`, aset datang dari server Vite.
        $vite = [];
        if (Vite::isRunningHot()) {
            $origin = rtrim((string) file_get_contents(public_path('hot')));
            $vite = [$origin, preg_replace('#^http#', 'ws', $origin)];
        }

        $directives = [
            'default-src' => ["'self'"],
            // Halaman masih memakai banyak script inline; asal script tetap hanya domain ini.
            'script-src' => ["'self'", "'unsafe-inline'", ...$vite],
            'style-src' => ["'self'", "'unsafe-inline'", ...self::SOURCES['style-src'], ...$vite],
            'font-src' => ["'self'", 'data:', ...self::SOURCES['font-src']],
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'media-src' => ["'self'", 'blob:'],
            'frame-src' => self::SOURCES['frame-src'],
            'connect-src' => ["'self'", ...$vite],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)->map(fn (array $sources, string $name) => $name.' '.implode(' ', array_unique($sources)))->implode('; ');
    }
}
