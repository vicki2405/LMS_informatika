<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Daftar Seluruh Permissions Menu Sistem Sekolah
        $permissions = [
            // Manajemen Admin & Sekolah
            'kelola-user',
            'kelola-sekolah',

            // 1. Jadwal Mengajar Guru
            'kelola-jadwal',
            'lihat-jadwal',

            // 2. Kelola Kelas & Jurusan
            'kelola-kelas',
            'lihat-kelas',

            // 3. Data Siswa
            'kelola-siswa',
            'lihat-siswa',

            // 4. Absensi Siswa (Matriks & Massal)
            'kelola-absensi',
            'lihat-absensi',

            // 5. Jurnal Mengajar Guru
            'kelola-jurnal',
            'lihat-jurnal',

            // 6. Penilaian TP & Master Tahun Ajaran
            'kelola-nilai',
            'lihat-nilai',
            'kelola-tp',
            'kelola-tahun-ajaran',

            // 7. Kelola Materi per TP
            'kelola-materi',
            'lihat-materi',

            // 8. Wewenang Khusus Wali Kelas
            'kelola-wali-kelas',
            'rekap-rapor-wali-kelas',
            'cetak-rapor-kelas',

            // Tugas & Asesmen Siswa
            'kelola-tugas',
            'lihat-tugas',
            'kerjakan-tugas',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Buat Roles & Sync Permissions
        // Role Admin
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleAdmin->syncPermissions(Permission::all());

        // Role Guru Mata Pelajaran
        $roleGuru = Role::firstOrCreate(['name' => 'guru']);
        $roleGuru->syncPermissions([
            'kelola-jadwal',
            'lihat-jadwal',
            'kelola-kelas',
            'lihat-kelas',
            'kelola-siswa',
            'lihat-siswa',
            'kelola-absensi',
            'lihat-absensi',
            'kelola-jurnal',
            'lihat-jurnal',
            'kelola-nilai',
            'lihat-nilai',
            'kelola-tp',
            'kelola-tahun-ajaran',
            'kelola-materi',
            'lihat-materi',
            'kelola-tugas',
            'lihat-tugas',
        ]);

        // Role Wali Kelas (Memiliki Seluruh Hak Guru + Akses Khusus Wali Kelas)
        $roleWaliKelas = Role::firstOrCreate(['name' => 'wali_kelas']);
        $roleWaliKelas->syncPermissions([
            'kelola-jadwal',
            'lihat-jadwal',
            'kelola-kelas',
            'lihat-kelas',
            'kelola-siswa',
            'lihat-siswa',
            'kelola-absensi',
            'lihat-absensi',
            'kelola-jurnal',
            'lihat-jurnal',
            'kelola-nilai',
            'lihat-nilai',
            'kelola-tp',
            'kelola-tahun-ajaran',
            'kelola-materi',
            'lihat-materi',
            'kelola-tugas',
            'lihat-tugas',
            'kelola-wali-kelas',
            'rekap-rapor-wali-kelas',
            'cetak-rapor-kelas',
        ]);

        // Role Murid / Siswa
        $roleMurid = Role::firstOrCreate(['name' => 'murid']);
        $roleMurid->syncPermissions([
            'lihat-jadwal',
            'lihat-materi',
            'lihat-tugas',
            'kerjakan-tugas',
            'lihat-nilai',
            'lihat-absensi',
        ]);
    }
}
