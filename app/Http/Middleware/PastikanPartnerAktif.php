<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup toko partner yang masa pakainya habis.
 *
 * Yang dikunci hanya pintunya — datanya tetap utuh di databasenya sendiri, dan
 * begitu admin memperpanjang, semuanya kembali seperti semula tanpa ada yang
 * perlu dipulihkan.
 *
 * Halamannya sengaja berdiri sendiri, hitam, tanpa menu: toko ini memang sedang
 * tidak berjalan, dan menampilkannya seolah baik-baik saja hanya membuat
 * pengunjung mengira situsnya rusak.
 */
class PastikanPartnerAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $this->partnerSekarang();

        if (! $partner instanceof Tenant || $partner->isAktif()) {
            return $next($request);
        }

        // 402 Payment Required: jujur soal sebabnya, dan mesin pencari tidak
        // memperlakukannya sebagai halaman yang hilang selamanya.
        return response()->view('pages.partner-kedaluwarsa', [
            'nama' => $partner->name,
            'whatsapp' => $this->tautanWhatsapp($partner),
        ], 402);
    }

    /**
     * Partner yang sedang dilayani.
     *
     * Dipisah jadi method sendiri supaya bisa diuji tanpa menyalakan tenancy
     * sungguhan — dan menyalakannya berarti membuat database, yang bukan urusan
     * middleware ini.
     */
    protected function partnerSekarang(): ?Tenant
    {
        $partner = tenant();

        return $partner instanceof Tenant ? $partner : null;
    }

    /**
     * Pesannya sudah terisi supaya partner tidak perlu menjelaskan ulang siapa
     * dirinya — admin langsung tahu toko mana yang minta diperpanjang.
     */
    private function tautanWhatsapp(Tenant $partner): string
    {
        $nomor = preg_replace('/\D/', '', (string) config('app.admin_whatsapp'));

        $pesan = 'Halo, saya ingin melanjutkan langganan website saya ('.$partner->name.').';

        return 'https://wa.me/'.$nomor.'?text='.rawurlencode($pesan);
    }
}
