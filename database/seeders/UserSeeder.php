<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. User Admin
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator Sekolah',
                'email' => 'admin@sekolah.sch.id',
                'password' => Hash::make('123456'),
            ]
        );
        $admin->syncRoles(['admin']);

        // 2. User Siswa
        $kelasId = \App\Models\Kelas::first()?->id;
        $siswa = User::updateOrCreate(
            ['username' => 'siswa'],
            [
                'name' => 'Ahmad Rizky',
                'email' => 'siswa@sekolah.sch.id',
                'password' => Hash::make('123456'),
                'kelas_id' => $kelasId,
            ]
        );
        $siswa->syncRoles(['murid']);
    }
}
