<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Producție: `php artisan db:seed --force` → date de sistem + locații inițiale.
     * Demo (doar local / staging): `php artisan db:seed --class=DemoSeeder`.
     */
    public function run(): void
    {
        $this->call([SystemDataSeeder::class, VenueSeeder::class]);
    }
}
