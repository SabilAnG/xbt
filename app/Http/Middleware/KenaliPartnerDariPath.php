<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengenali partner dari alamat `/toko/{slug}`.
 *
 * Ini yang membuat toko partner hidup tanpa satu pun langkah di server: tidak
 * ada DNS yang perlu ditambah, tidak ada vhost, tidak ada sertifikat. Partner
 * yang disetujui pukul sepuluh sudah bisa membuka tokonya pukul sepuluh lewat.
 *
 * Slug yang tidak dikenal dibalas 404, sama seperti halaman yang memang tidak
 * ada — dan memang begitulah kedudukannya.
 */
class KenaliPartnerDariPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('partner');

        $partner = Tenant::query()
            ->where('slug', $slug)
            ->where('status', Tenant::AKTIF)
            ->first();

        abort_if($partner === null, 404);

        tenancy()->initialize($partner);

        return $next($request);
    }
}
