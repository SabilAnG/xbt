<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Masuk dan keluar panel.
 *
 * Memakai guard `web` bawaan dan tabel `users` yang sama dengan panel lama,
 * jadi satu akun berlaku di keduanya selama masa perpindahan.
 */
class MasukController extends Controller
{
    public function tampil(): View
    {
        return view('panel.masuk');
    }

    public function kirim(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, $request->boolean('ingat'))) {
            // Pesannya sengaja tidak menyebut mana yang salah. Menyebut "email
            // tidak terdaftar" memberi tahu penebak bahwa alamat lain ada.
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        // Sesi lama dibuang supaya id sesi sebelum login tidak bisa dipakai
        // lagi sesudahnya.
        $request->session()->regenerate();

        return redirect()->intended(route('panel.beranda'));
    }

    public function keluar(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('panel.masuk');
    }
}
