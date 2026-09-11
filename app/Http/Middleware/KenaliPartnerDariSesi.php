<?php

namespace App\Http\Middleware;

use App\Services\SesiPartner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memindahkan koneksi ke database partner yang sedang masuk.
 *
 * Pasangannya `KenaliPartnerDariSubdomain`, dan bedanya soal urutan: yang itu
 * berjalan sebelum sesi dibaca karena alamatnya sudah menyebut siapa yang
 * dituju. Yang ini harus menunggu sesi, karena sesi yang menyimpan jawabannya.
 *
 * Dipakai panel admin, yang alamatnya sama untuk semua orang. Partner tidak
 * perlu mengingat alamat lain, dan tidak ada langkah di server yang perlu
 * dikerjakan tiap ada partner baru.
 */
class KenaliPartnerDariSesi
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sudah dikenali dari alamatnya — itu lebih tegas daripada sesi, dan
        // tidak boleh ditimpa.
        if (tenancy()->initialized) {
            return $next($request);
        }

        $partner = SesiPartner::partner();

        if ($partner !== null) {
            tenancy()->initialize($partner);
        }

        return $next($request);
    }
}
