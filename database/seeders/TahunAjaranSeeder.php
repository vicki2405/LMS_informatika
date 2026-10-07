<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TahunAjaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tahunAjaran = [
            ['kode' => '2024/2025', 'is_aktif' => false, 'created_at' => now(), 'updated_at' => now()],
            ['kode' => '2025/2026', 'is_aktif' => true,  'created_at' => now(), 'updated_at' => now()],
            ['kode' => '2026/2027', 'is_aktif' => false, 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($tahunAjaran as $ta) {
            \App\Models\TahunAjaran::firstOrCreate(
                ['kode' => $ta['kode']],
                ['is_aktif' => $ta['is_aktif']]
            );
        }
    }
}
