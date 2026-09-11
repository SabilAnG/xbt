<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use Illuminate\Http\RedirectResponse;

/**
 * Meneruskan klik banner ke situs pemasang, sambil menghitungnya.
 *
 * Dihitung di server, bukan lewat JavaScript: di halaman yang memuat iklan,
 * JavaScript penghitung justru yang paling sering diblokir, dan angka klik yang
 * separuh terhitung lebih menyesatkan daripada tidak menghitung sama sekali.
 */
class AdvertisementClickController extends Controller
{
    public function __invoke(Advertisement $advertisement): RedirectResponse
    {
        // increment() menaikkan lewat satu query, jadi dua klik yang datang
        // bersamaan tidak saling menimpa seperti kalau dibaca lalu ditulis.
        $advertisement->increment('clicks');

        // away(): tujuannya milik orang lain, dan Laravel menolak membuat
        // redirect ke luar domain lewat to() tanpa disengaja.
        return redirect()->away($advertisement->target_url);
    }
}
