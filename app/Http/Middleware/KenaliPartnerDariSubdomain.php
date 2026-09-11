<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengenali partner dari alamat yang dibuka, lalu memindahkan koneksi database
 * ke miliknya.
 *
 * Berbeda dari `InitializeTenancyByDomain` bawaan tenancy, yang menggagalkan
 * permintaan begitu tenant tidak ketemu: di sini alamat pusat memang bukan milik
 * siapa-siapa dan harus lewat apa adanya. Satu panel melayani keduanya — yang
 * membedakan cuma database di belakangnya dan menu yang boleh dibuka.
 *
 * Subdomain yang tidak terdaftar dibalas 404. Menampilkan panel pusat untuk
 * alamat yang tidak dikenal akan membuat siapa pun bisa menebak-nebak alamat
 * dan mendarat di panel milik Hypersonic.
 */
class KenaliPartnerDariSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if ($this->alamatPusat($host)) {
            return $next($request);
        }

        $domain = Domain::where('domain', $host)->first();

        abort_if($domain === null, 404);

        $partner = Tenant::find($domain->tenant_id);

        abort_if($partner === null, 404);

        tenancy()->initialize($partner);

        return $next($request);
    }

    private function alamatPusat(string $host): bool
    {
        return in_array($host, (array) config('tenancy.central_domains', []), true);
    }
}
