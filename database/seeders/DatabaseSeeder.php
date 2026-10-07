<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            TahunAjaranSeeder::class,
            GuruSeeder::class,
            KelasSeeder::class,
            UserSeeder::class,
            MapelSeeder::class,
            MataPelajaranSeeder::class,
            SchoolDataSeeder::class,
        ]);
    }
}
