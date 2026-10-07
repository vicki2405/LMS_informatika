<?php

namespace Database\Seeders;

use App\Models\KelompokMapel;
use App\Models\MataPelajaran;
use Illuminate\Database\Seeder;

class MapelSeeder extends Seeder
{
    /**
     * Run the database seeds for Kelompok Mapel & Mata Pelajaran.
     */
    public function run(): void
    {
        // 1. Seed Kelompok Mapel
        $kelompoks = [
            ['kode' => 'MPU', 'nama' => 'Mata Pelajaran Umum', 'deskripsi' => 'Kelompok mata pelajaran muatan umum dan nasional'],
            ['kode' => 'KJK', 'nama' => 'Kejuruan / Produktif', 'deskripsi' => 'Kelompok mata pelajaran keahlian produktif dan kejuruan'],
            ['kode' => 'MULOK', 'nama' => 'Muatan Lokal', 'deskripsi' => 'Kelompok mata pelajaran khas muatan daerah dan pesantren/sekolah'],
        ];

        foreach ($kelompoks as $km) {
            KelompokMapel::firstOrCreate(
                ['kode' => $km['kode']],
                ['nama' => $km['nama'], 'deskripsi' => $km['deskripsi']]
            );
        }
    }
}
