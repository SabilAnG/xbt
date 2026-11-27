<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Keamanan akun sendiri: ganti password dan verifikasi 2 langkah. Terbuka untuk semua peran. */
class AccountSecurityController extends Controller
{
    /** Rahasia authenticator yang sedang dipasang (belum dikonfirmasi). */
    private const SETUP = 'two_factor.setup_secret';

    /** Nama yang muncul di aplikasi authenticator. */
    public const ISSUER = 'IHC Racing';

    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $secret = $user->hasTwoFactor() ? null : $request->session()->get(self::SETUP);

        return view('admin.account.security', [
            'user' => $user,
            'setupSecret' => $secret,
            'setupUri' => $secret ? Totp::uri($secret, $user->email, self::ISSUER) : null,
            'recoveryCodes' => session('recovery_codes'),
        ]);
    }

    /** Mulai pasang: buat rahasia baru, tampilkan untuk dipindai/diketik di HP. */
    public function start(Request $request): RedirectResponse
    {
        if ($request->user()->hasTwoFactor()) {
            return back()->with('error', 'Verifikasi 2 langkah sudah aktif.');
        }

        $request->session()->put(self::SETUP, Totp::generateSecret());

        return redirect()->route('admin.account.security')->withFragment('dua-langkah');
    }

    /** Aktif setelah kode pertama dari HP cocok — memastikan HP benar-benar sudah tersambung. */
    public function confirm(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $secret = $request->session()->get(self::SETUP);
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);

        if (! $secret || Totp::match($secret, $data['code']) === null) {
            throw ValidationException::withMessages(['code' => 'Kode tidak cocok. Pastikan jam HP sesuai lalu masukkan kode terbaru.']);
        }

        $codes = User::newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget(self::SETUP);

        return redirect()->route('admin.account.security')
            ->with('status', 'Verifikasi 2 langkah aktif. Simpan kode cadangan di bawah.')
            ->with('recovery_codes', $codes);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SETUP);

        return redirect()->route('admin.account.security');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('admin.account.security')->with('status', 'Verifikasi 2 langkah dimatikan.');
    }

    public function regenerateCodes(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);

        if (! $request->user()->hasTwoFactor()) {
            return back()->with('error', 'Aktifkan verifikasi 2 langkah dulu.');
        }

        $codes = User::newRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('admin.account.security')
            ->with('status', 'Kode cadangan baru dibuat; kode lama tidak berlaku lagi.')
            ->with('recovery_codes', $codes);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $this->confirmPassword($request);
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ], ['password.different' => 'Password baru harus berbeda dari yang lama.']);

        $request->user()->forceFill(['password' => $data['password']])->save();

        // Sesi di perangkat lain masih memegang sidik password lama → dikeluarkan (AuthenticateSession).
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        return redirect()->route('admin.account.security')->with('status', 'Password diganti. Sesi di perangkat lain sudah dikeluarkan.');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['current_password' => ['required', 'current_password']], [
            'current_password.current_password' => 'Password saat ini salah.',
        ]);
    }
}
