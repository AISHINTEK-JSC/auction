<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Listings are not seeded: each one needs a real auction in the engine.
     */
    public function run(): void
    {
        $this->call([CitySeeder::class, CategorySeeder::class]);

        // Local accounts, password "password". Change it before exposing the app.
        if (! User::query()->where('email', 'admin@example.com')->exists()) {
            User::factory()->create(['name' => 'Quản trị', 'email' => 'admin@example.com'])
                ->forceFill(['is_admin' => true])
                ->save();
        }

        foreach (['seller@example.com' => 'Người bán', 'buyer@example.com' => 'Người mua'] as $email => $name) {
            if (! User::query()->where('email', $email)->exists()) {
                User::factory()->create(['name' => $name, 'email' => $email]);
            }
        }
    }
}
