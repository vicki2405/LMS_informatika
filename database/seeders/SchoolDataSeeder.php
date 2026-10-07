<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SchoolDataSeeder extends Seeder
{
    /**
     * Run the database seeds for operational school data.
     */
    public function run(): void
    {
        $guru = \App\Models\Guru::where('nama', 'LIKE', '%Budi%')->first() ?? \App\Models\Guru::first();
        $kelas1 = Kelas::where('nama_kelas', 'X TKR 1')->first() ?? Kelas::first();
        $kelas2 = Kelas::where('nama_kelas', 'X TSM 1')->first() ?? Kelas::skip(1)->first();
        $siswas = Siswa::all();
        $tps = TujuanPembelajaran::all();

        // 1. Seed Jadwal Mengajar
        if ($kelas1 && $guru) {
            Jadwal::firstOrCreate(
                ['hari' => 'Senin', 'jam_ke' => 1, 'kelas_id' => $kelas1->id],
                [
                    'guru_id' => $guru->id,
                    'waktu' => '07.00 - 07.45',
                    'mata_pelajaran' => 'Informatika (Hardware)',
                    'ruangan' => 'Lab Komputer 1',
                    'keterangan' => 'Pengenalan Hardware & CPU',
                ]
            );

            Jadwal::firstOrCreate(
                ['hari' => 'Senin', 'jam_ke' => 2, 'kelas_id' => $kelas1->id],
                [
                    'guru_id' => $guru->id,
                    'waktu' => '07.45 - 08.30',
                    'mata_pelajaran' => 'Informatika (Hardware)',
                    'ruangan' => 'Lab Komputer 1',
                    'keterangan' => 'Praktikum Komponen System Unit',
                ]
            );
        }

        if ($kelas2 && $guru) {
            Jadwal::firstOrCreate(
                ['hari' => 'Selasa', 'jam_ke' => 3, 'kelas_id' => $kelas2->id],
                [
                    'guru_id' => $guru->id,
                    'waktu' => '08.30 - 09.15',
                    'mata_pelajaran' => 'Informatika (Jaringan)',
                    'ruangan' => 'Lab Komputer 2',
                    'keterangan' => 'Kabel UTP & Crimping RJ45',
                ]
            );
        }

        // 2. Seed Jurnal Mengajar
        if ($kelas1 && $guru) {
            Jurnal::firstOrCreate(
                ['tanggal' => date('Y-m-d'), 'kelas_id' => $kelas1->id, 'jam_ke' => 'Jam 1-2'],
                [
                    'guru_id' => $guru->id,
                    'mata_pelajaran' => 'Informatika',
                    'materi' => 'Pengenalan Algoritma & Pemrograman Vue 3',
                    'uraian' => 'Diskusi struktur komponen Vue 3, pengenalan props & custom event.',
                    'catatan' => 'Siswa sangat antusias mengikuti praktikum.',
                ]
            );
        }

        // 3. Seed Absensi
        if ($kelas1 && count($siswas) > 0) {
            foreach ($siswas as $index => $siswa) {
                Absensi::firstOrCreate(
                    ['tanggal' => date('Y-m-d'), 'siswa_id' => $siswa->id],
                    [
                        'kelas_id' => $siswa->kelas_id ?? $kelas1->id,
                        'status' => $index === 2 ? 'Sakit' : 'Hadir',
                        'keterangan' => $index === 2 ? 'Demam' : 'Hadir Tepat Waktu',
                    ]
                );
            }
        }

        // 4. Seed Nilai TP
        if (count($tps) > 0 && count($siswas) > 0) {
            foreach ($siswas as $sIdx => $siswa) {
                foreach ($tps as $tIdx => $tp) {
                    $score = 80 + (($sIdx + $tIdx) % 15);
                    NilaiTp::firstOrCreate(
                        ['tp_id' => $tp->id, 'siswa_id' => $siswa->id],
                        ['nilai' => $score]
                    );
                }
            }
        }
    }
}
