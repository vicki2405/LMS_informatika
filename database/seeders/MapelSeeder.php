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

        // 2. Seed Master Mata Pelajaran
        $mapels = [
            ['kode' => 'INF', 'nama' => 'Informatika', 'kelompok' => 'Kejuruan / Produktif', 'deskripsi' => 'Pemrograman, Pemikiran Komputasional & Algoritma'],
            ['kode' => 'MATH', 'nama' => 'Matematika', 'kelompok' => 'Mata Pelajaran Umum', 'deskripsi' => 'Matematika Terapan & Logika'],
            ['kode' => 'BINDO', 'nama' => 'Bahasa Indonesia', 'kelompok' => 'Mata Pelajaran Umum', 'deskripsi' => 'Literasi & Kebahasaan Indonesia'],
            ['kode' => 'BING', 'nama' => 'Bahasa Inggris', 'kelompok' => 'Mata Pelajaran Umum', 'deskripsi' => 'English Communication & Grammar'],
            ['kode' => 'SEJ', 'nama' => 'Sejarah Indonesia', 'kelompok' => 'Mata Pelajaran Umum', 'deskripsi' => 'Sejarah Kebangsaan & Peradaban'],
            ['kode' => 'PAI', 'nama' => 'Pendidikan Agama Islam', 'kelompok' => 'Muatan Lokal', 'deskripsi' => 'Aqidah, Akhlaq & Fiqih Syafii'],
        ];

        foreach ($mapels as $m) {
            MataPelajaran::firstOrCreate(
                ['kode' => $m['kode']],
                ['nama' => $m['nama'], 'kelompok' => $m['kelompok'], 'deskripsi' => $m['deskripsi']]
            );
        }
    }
}
