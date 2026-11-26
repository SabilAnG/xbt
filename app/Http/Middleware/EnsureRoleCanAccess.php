<?php

namespace App\Http\Middleware;

use App\Support\AdminNavigation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hak akses per peran: route admin hanya terbuka bila grup menunya boleh
 * dibuka peran pengguna. Akun yang dinonaktifkan langsung dikeluarkan.
 */
class EnsureRoleCanAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Akun ini dinonaktifkan. Hubungi pemilik.']);
        }

        $route = $request->route();

        if ($user && $route && ! AdminNavigation::allows($user, (string) $route->getName(), $route->parameters())) {
            abort(403, 'Peran '.$user->roleLabel().' tidak punya akses ke menu ini.');
        }

        return $next($request);
    }
}
