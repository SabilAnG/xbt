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
        User::firstOrCreate(
            ['email' => 'admin@hypersonic.id'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        $this->call([
            SiteContentSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
