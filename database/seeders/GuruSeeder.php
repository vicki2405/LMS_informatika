<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GuruSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Guru Biasa
        $user1 = User::firstOrCreate(
            ['username' => 'budi_guru'],
            [
                'name' => 'Budi Santoso, S.Kom.',
                'email' => 'budi.guru@sekolah.sch.id',
                'password' => Hash::make('password123'),
            ]
        );
        $user1->syncRoles(['guru']);

        Guru::firstOrCreate(
            ['nip' => '19850110201001'],
            [
                'nama' => 'Budi Santoso, S.Kom.',
                'telepon' => '081234567890',
                'mapel_utama' => 'Informatika',
                'user_id' => $user1->id,
            ]
        );

        // 2. Guru Wali Kelas
        $user2 = User::firstOrCreate(
            ['username' => 'siti_walikelas'],
            [
                'name' => 'Siti Aminah, M.Pd.',
                'email' => 'siti.walikelas@sekolah.sch.id',
                'password' => Hash::make('password123'),
            ]
        );
        $user2->syncRoles(['guru', 'wali_kelas']);

        Guru::firstOrCreate(
            ['nip' => '19850110201002'],
            [
                'nama' => 'Siti Aminah, M.Pd.',
                'telepon' => '082345678901',
                'mapel_utama' => 'Pemrograman Web',
                'user_id' => $user2->id,
            ]
        );

        // 3. Guru Wali Kelas
        $user3 = User::firstOrCreate(
            ['username' => 'hendra_walikelas'],
            [
                'name' => 'Hendra Wijaya, S.T.',
                'email' => 'hendra.walikelas@sekolah.sch.id',
                'password' => Hash::make('password123'),
            ]
        );
        $user3->syncRoles(['guru', 'wali_kelas']);

        Guru::firstOrCreate(
            ['nip' => '19850110201003'],
            [
                'nama' => 'Hendra Wijaya, S.T.',
                'telepon' => '083456789012',
                'mapel_utama' => 'Basis Data',
                'user_id' => $user3->id,
            ]
        );
    }
}
