<?php

namespace Database\Seeders;

use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class TahunAjaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tahunAjaran = [
            ['kode' => '2024/2025', 'is_aktif' => false],
            ['kode' => '2025/2026', 'is_aktif' => false],
            ['kode' => '2026/2027', 'is_aktif' => true],
        ];

        foreach ($tahunAjaran as $ta) {
            TahunAjaran::updateOrCreate(
                ['kode' => $ta['kode']],
                ['is_aktif' => $ta['is_aktif']]
            );
        }
    }
}
