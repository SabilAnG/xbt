<?php

namespace App\Http\Middleware;

use App\Support\HakPartner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup halaman yang memang hanya milik Hypersonic.
 *
 * Pendaftaran partner dan pemasangan iklan adalah urusan situs induk. Kalau
 * dibiarkan hidup di subdomain partner, pengunjung toko orang bisa mendaftar
 * jadi partner dari sana — dan pendaftarannya masuk ke database partner itu,
 * bukan ke tempat yang membacanya.
 */
class HanyaPusat
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(HakPartner::partner() !== null, 404);

        return $next($request);
    }
}
