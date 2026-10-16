<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use Illuminate\Database\Seeder;

class MataPelajaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mapels = [
            ['kode' => 'MP-IND', 'nama' => 'Bahasa Indonesia', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Penguasaan tata bahasa, literasi, dan apresiasi sastra Indonesia'],
            ['kode' => 'MP-INF', 'nama' => 'Informatika', 'kelompok' => 'Kelompok Kejuruan', 'deskripsi' => 'Dasar algoritma, berpikir komputasional, & arsitektur komputer'],
            ['kode' => 'MP-WEB', 'nama' => 'Pemrograman Web', 'kelompok' => 'Kelompok Kejuruan', 'deskripsi' => 'Pengembangan antarmuka web responsif HTML, CSS, JavaScript, Vue & Laravel'],
            ['kode' => 'MP-MTK', 'nama' => 'Matematika', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Aljabar, geometri, trigonometri, dan kalkulasi statistik'],
            ['kode' => 'MP-BIG', 'nama' => 'Bahasa Inggris', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Komunikasi lisan, tulisan, dan istilah teknis kejuruan bahasa Inggris'],
            ['kode' => 'MP-PAI', 'nama' => 'Pendidikan Agama & Budi Pekerti', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Pembentukan karakter, akhlakul karimah, dan ilmu keagamaan'],
            ['kode' => 'MP-BSD', 'nama' => 'Basis Data', 'kelompok' => 'Kelompok Kejuruan', 'deskripsi' => 'Perancangan ERD, SQL relational database, dan manipulasi data'],
            ['kode' => 'MP-OTM', 'nama' => 'Teknik Kendaraan Ringan (Otomotif)', 'kelompok' => 'Kelompok Kejuruan TKR', 'deskripsi' => 'Pemeliharaan mesin kendaraan ringan, sistem chassis, dan kelistrikan otomotif'],
            ['kode' => 'MP-TSM', 'nama' => 'Teknik Sepeda Motor', 'kelompok' => 'Kelompok Kejuruan TSM', 'deskripsi' => 'Perbaikan dan perawatan sistem injeksi sepeda motor'],
            ['kode' => 'MP-TEI', 'nama' => 'Teknik Elektronika Industri', 'kelompok' => 'Kelompok Kejuruan TEI', 'deskripsi' => 'Rangkaian mikroprosesor, sensor industri, dan kendali otomatis'],
            ['kode' => 'MP-ANM', 'nama' => 'Dasar-Dasar Animasi', 'kelompok' => 'Kelompok Kejuruan ANIMASI', 'deskripsi' => 'Prinsip animasi 2D/3D, sketsa karakter, dan produksi storyboard'],
            ['kode' => 'MP-KUL', 'nama' => 'Tata Boga & Seni Kuliner', 'kelompok' => 'Kelompok Kejuruan KULINER', 'deskripsi' => 'Teknik pengolahan makanan, hygiene sanitasi, dan penyajian masakan'],
            ['kode' => 'MP-SEJ', 'nama' => 'Sejarah Indonesia', 'kelompok' => 'Kelompok A (Umum)', 'deskripsi' => 'Sejarah Kebangsaan & Peradaban Nasional'],
        ];

        foreach ($mapels as $m) {
            MataPelajaran::firstOrCreate(
                ['kode' => $m['kode']],
                $m
            );
        }
    }
}
