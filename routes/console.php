<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:user {email} {--name=Admin}', function (string $email) {
    $password = $this->secret('Password');

    if (strlen((string) $password) < 8) {
        $this->error('Password minimal 8 karakter.');

        return 1;
    }

    User::updateOrCreate(['email' => $email], ['name' => $this->option('name'), 'password' => $password]);
    $this->info("Akun admin {$email} siap dipakai di /admin.");
})->purpose('Buat atau reset akun admin panel');
