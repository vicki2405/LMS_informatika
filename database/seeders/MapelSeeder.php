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

        // 2. Seed Master Mata Pelajaran (Only if not already created)
        $mapels = [
            ['kode' => 'MP-INF', 'nama' => 'Informatika', 'kelompok' => 'Kelompok Kejuruan', 'deskripsi' => 'Pemrograman, Pemikiran Komputasional & Algoritma'],
            ['kode' => 'MP-MTK', 'nama' => 'Matematika', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Matematika Terapan & Logika'],
            ['kode' => 'MP-IND', 'nama' => 'Bahasa Indonesia', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Literasi & Kebahasaan Indonesia'],
            ['kode' => 'MP-BIG', 'nama' => 'Bahasa Inggris', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'English Communication & Grammar'],
            ['kode' => 'MP-SEJ', 'nama' => 'Sejarah Indonesia', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Sejarah Kebangsaan & Peradaban'],
            ['kode' => 'MP-PAI', 'nama' => 'Pendidikan Agama & Budi Pekerti', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Aqidah, Akhlaq & Fiqih Syafii'],
        ];

        foreach ($mapels as $m) {
            MataPelajaran::firstOrCreate(
                ['nama' => $m['nama']],
                ['kode' => $m['kode'], 'kelompok' => $m['kelompok'], 'deskripsi' => $m['deskripsi']]
            );
        }
    }
}
