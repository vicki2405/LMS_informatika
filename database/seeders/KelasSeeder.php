<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\Seeder;

class KelasSeeder extends Seeder
{
    /**
     * Run the database seeds for SMKS Muhammadiyah 2 Genteng.
     */
    public function run(): void
    {
        // 1. 5 Jurusan Resmi SMKS Muhammadiyah 2 Genteng
        $tkr = Jurusan::firstOrCreate(['kode' => 'TKR'], ['nama' => 'Teknik Kendaraan Ringan', 'deskripsi' => 'Otomotif & Pemeliharaan Kendaraan Ringan']);
        $tsm = Jurusan::firstOrCreate(['kode' => 'TSM'], ['nama' => 'Teknik Sepeda Motor', 'deskripsi' => 'Otomotif & Teknik Sepeda Motor']);
        $tei = Jurusan::firstOrCreate(['kode' => 'TEI'], ['nama' => 'Teknik Elektronika Industri', 'deskripsi' => 'Otomasi Industri & Sistem Elektronika']);
        $animasi = Jurusan::firstOrCreate(['kode' => 'ANIMASI'], ['nama' => 'Animasi', 'deskripsi' => 'Seni Komunikasi Visual, Animasi 2D/3D & Media']);
        $kuliner = Jurusan::firstOrCreate(['kode' => 'KULINER'], ['nama' => 'Kuliner', 'deskripsi' => 'Tata Boga & Seni Kuliner Produk']);

        $gBudi = \App\Models\Guru::query()->where('nama', 'LIKE', '%Budi%')->first();
        $gSiti = \App\Models\Guru::query()->where('nama', 'LIKE', '%Siti%')->first();
        $gHendra = \App\Models\Guru::query()->where('nama', 'LIKE', '%Hendra%')->first();

        // 2. Kelas Sample
        $k1 = Kelas::firstOrCreate(['nama_kelas' => 'X TKR 1'], ['tingkat' => 'X', 'jurusan_id' => $tkr->id, 'guru_id' => $gBudi?->id, 'jumlah_siswa' => 5]);
        $k2 = Kelas::firstOrCreate(['nama_kelas' => 'X TSM 1'], ['tingkat' => 'X', 'jurusan_id' => $tsm->id, 'guru_id' => $gSiti?->id, 'jumlah_siswa' => 5]);
        $k3 = Kelas::firstOrCreate(['nama_kelas' => 'X TEI 1'], ['tingkat' => 'X', 'jurusan_id' => $tei->id, 'guru_id' => $gHendra?->id, 'jumlah_siswa' => 5]);
        $k4 = Kelas::firstOrCreate(['nama_kelas' => 'X ANIMASI 1'], ['tingkat' => 'X', 'jurusan_id' => $animasi->id, 'guru_id' => $gBudi?->id, 'jumlah_siswa' => 5]);
        $k5 = Kelas::firstOrCreate(['nama_kelas' => 'X KULINER 1'], ['tingkat' => 'X', 'jurusan_id' => $kuliner->id, 'guru_id' => $gSiti?->id, 'jumlah_siswa' => 5]);

        // 3. Sample Siswa
        $siswas = [
            ['nama' => 'Ahmad Rizky', 'jenis_kelamin' => 'Laki-laki', 'kelas_id' => $k1->id, 'telepon' => '081234567890'],
            ['nama' => 'Budi Santoso', 'jenis_kelamin' => 'Laki-laki', 'kelas_id' => $k1->id, 'telepon' => '082345678901'],
            ['nama' => 'Dewi Lestari', 'jenis_kelamin' => 'Perempuan', 'kelas_id' => $k4->id, 'telepon' => '083456789012'],
            ['nama' => 'Fajar Pratama', 'jenis_kelamin' => 'Laki-laki', 'kelas_id' => $k2->id, 'telepon' => '084567890123'],
            ['nama' => 'Siti Nurhaliza', 'jenis_kelamin' => 'Perempuan', 'kelas_id' => $k5->id, 'telepon' => '085678901234'],
        ];

        foreach ($siswas as $s) {
            Siswa::firstOrCreate(['nama' => $s['nama']], $s);
        }
    }
}
