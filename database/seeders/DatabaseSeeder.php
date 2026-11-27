<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Akun bawaan berpassword "password" hanya untuk lokal; di server pakai `php artisan admin:user`.
        if (app()->isProduction()) {
            $this->command?->warn('Lewati akun admin bawaan di produksi — buat dengan `php artisan admin:user`.');
        } else {
            User::firstOrCreate(
                ['email' => 'admin@hypersonic.id'],
                ['name' => 'Admin', 'password' => 'password'],
            );
        }

        $this->call([
            SiteContentSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
