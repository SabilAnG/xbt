<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga panel baru.
 *
 * Dipakai menggantikan middleware `auth` bawaan, yang mengarahkan tamu ke rute
 * bernama `login` — dan rute itu tidak ada di sini, karena panel lama memakai
 * halaman masuk milik Filament sendiri. Akibatnya `auth` melempar
 * RouteNotFoundException, dan tamu yang seharusnya diminta masuk justru
 * mendapat layar 500.
 *
 * Dibuat khusus panel, bukan disetel global, supaya perilaku panel Filament di
 * /admin tidak ikut berubah selama masa perpindahan.
 */
class PastikanMasukPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        // Alamat yang dituju disimpan supaya sesudah masuk orang kembali ke
        // halaman yang tadi dimintanya, bukan ke beranda.
        return redirect()->guest(route('panel.masuk'));
    }
}
