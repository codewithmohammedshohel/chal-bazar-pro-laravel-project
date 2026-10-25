<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed demo catalog data only. No user accounts or credentials are seeded.
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);
    }
}
