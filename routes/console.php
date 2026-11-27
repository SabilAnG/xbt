<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:user {email} {--name=Admin}', function (string $email) {
    $password = $this->secret('Password');

    if (strlen((string) $password) < 8 || in_array(strtolower((string) $password), ['password', 'password123', '12345678', 'admin123', 'hypersonic'], true)) {
        $this->error('Password minimal 8 karakter dan bukan password umum.');

        return 1;
    }

    User::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'password' => $password]);
    $this->info("Akun admin {$email} siap dipakai di /admin.");
})->purpose('Buat atau reset akun admin panel');

/*
 * Pemeriksaan keamanan cepat untuk dijalankan di server:
 *   docker compose -f compose.prod.yaml exec app php artisan security:check
 * Keluar dengan kode 1 bila ada temuan, supaya bisa dipakai di skrip deploy.
 */
Artisan::command('security:check', function () {
    $weak = ['password', 'password123', '12345678', '123456789', 'admin123', 'secret', 'qwerty123', 'hypersonic'];
    $problems = [];

    if (config('app.debug') && app()->isProduction()) {
        $problems[] = 'APP_DEBUG=true di produksi: stack trace & konfigurasi bisa terlihat publik.';
    }
    if (app()->isProduction() && ! config('session.secure')) {
        $problems[] = 'SESSION_SECURE_COOKIE belum true: cookie sesi bisa terkirim lewat http.';
    }
    if (blank(config('app.key'))) {
        $problems[] = 'APP_KEY kosong.';
    }
    if (app()->isProduction() && blank(env('BACKUP_PASSWORD'))) {
        $problems[] = 'BACKUP_PASSWORD kosong: deploy/backup-db.sh tidak akan jalan.';
    }

    foreach (User::query()->where('is_active', true)->get() as $user) {
        foreach ($weak as $guess) {
            if (Hash::check($guess, $user->password)) {
                $problems[] = "Akun {$user->email} memakai password lemah/bawaan (\"{$guess}\"). Ganti segera.";
                break;
            }
        }
        if ($user->isOwner() && ! $user->hasTwoFactor()) {
            $problems[] = "Pemilik {$user->email} belum mengaktifkan verifikasi 2 langkah.";
        }
    }

    if ($problems === []) {
        $this->info('Tidak ada temuan.');

        return 0;
    }

    foreach ($problems as $problem) {
        $this->warn('• '.$problem);
    }

    return 1;
})->purpose('Periksa password bawaan, 2 langkah pemilik, dan konfigurasi produksi');
