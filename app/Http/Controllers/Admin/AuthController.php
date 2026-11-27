<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Sesi menunggu kode 2 langkah: id pengguna yang password-nya sudah benar. */
    private const PENDING = 'two_factor.pending';

    /** Batas waktu mengisi kode setelah password benar. */
    private const PENDING_MINUTES = 5;

    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Tebak password satu akun dari banyak IP tetap tertahan: batas per email.
        $accountKey = 'login:'.Str::lower($credentials['email']);
        $deviceKey = $accountKey.'|'.$request->ip();

        foreach ([$deviceKey => 5, $accountKey => 20] as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                throw ValidationException::withMessages([
                    'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.',
                ]);
            }
        }

        if (! Auth::validate($credentials + ['is_active' => true])) {
            RateLimiter::hit($deviceKey, 15 * 60);
            RateLimiter::hit($accountKey, 15 * 60);

            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        RateLimiter::clear($deviceKey);

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if ($user->hasTwoFactor()) {
            // Password benar, tapi belum masuk sampai kode authenticator diisi.
            $request->session()->regenerate();
            $request->session()->put(self::PENDING, [
                'id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires' => now()->addMinutes(self::PENDING_MINUTES)->timestamp,
            ]);

            return redirect()->route('login.two-factor');
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    public function showTwoFactor(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('admin.auth.two-factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Waktu verifikasi habis. Silakan masuk lagi.']);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:10', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $key = '2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $request->session()->forget(self::PENDING);

            return redirect()->route('login')->withErrors([
                'email' => 'Terlalu banyak kode salah. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.',
            ]);
        }

        $valid = filled($data['recovery_code'] ?? null)
            ? $user->useRecoveryCode($data['recovery_code'])
            : $user->verifyTwoFactorCode($data['code'] ?? '');

        if (! $valid) {
            RateLimiter::hit($key, 15 * 60);

            throw ValidationException::withMessages([
                filled($data['recovery_code'] ?? null) ? 'recovery_code' : 'code' => 'Kode salah atau sudah dipakai.',
            ]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull(self::PENDING)['remember'];

        return $this->completeLogin($request, $user, $remember);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING);

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING);

            return null;
        }

        $user = User::query()->whereKey($pending['id'])->where('is_active', true)->first();

        return $user?->hasTwoFactor() ? $user : null;
    }
}
