<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSiswaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_students_with_varying_gender_strings(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this
            ->actingAs($admin)
            ->post('/admin/siswa/import-batch', [
                'rows' => [
                    [
                        'nama' => 'ACHMAD MIFTAHUDDIN YUSUF',
                        'jenisKelamin' => 'Laki - laki',
                        'kelas' => 'X TKR 1',
                        'telepon' => '-',
                    ],
                    [
                        'nama' => 'SITI AMINAH',
                        'jenisKelamin' => 'perempuan',
                        'kelas' => 'X TKR 1',
                        'telepon' => '-',
                    ],
                    [
                        'nama' => 'BUDI SANTO',
                        'jenisKelamin' => 'L',
                        'kelas' => 'X TKR 1',
                        'telepon' => '-',
                    ],
                    [
                        'nama' => 'WATI SUSILO',
                        'jenisKelamin' => 'P',
                        'kelas' => 'X TKR 1',
                        'telepon' => '-',
                    ]
                ]
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('siswas', [
            'nama' => 'ACHMAD MIFTAHUDDIN YUSUF',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $this->assertDatabaseHas('siswas', [
            'nama' => 'SITI AMINAH',
            'jenis_kelamin' => 'Perempuan',
        ]);

        $this->assertDatabaseHas('siswas', [
            'nama' => 'BUDI SANTO',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $this->assertDatabaseHas('siswas', [
            'nama' => 'WATI SUSILO',
            'jenis_kelamin' => 'Perempuan',
        ]);
    }

    public function test_admin_can_save_student_status_and_date(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\KelasSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Test Saving student as Pindah with date
        $response = $this
            ->actingAs($admin)
            ->post('/admin/siswa/store', [
                'nama' => 'RUDI HERMAWAN',
                'jenisKelamin' => 'Laki-laki',
                'kelas' => 'X TKR 1',
                'teleponSiswa' => '081234567890',
                'status' => 'Pindah',
                'tanggal_status' => '2026-08-03'
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('siswas', [
            'nama' => 'RUDI HERMAWAN',
            'status' => 'Pindah',
            'tanggal_status' => '2026-08-03'
        ]);

        $siswa = Siswa::where('nama', 'RUDI HERMAWAN')->first();

        // Update student back to Aktif (should clear the date)
        $response2 = $this
            ->actingAs($admin)
            ->post('/admin/siswa/store', [
                'id' => $siswa->id,
                'nama' => 'RUDI HERMAWAN',
                'jenisKelamin' => 'Laki-laki',
                'kelas' => 'X TKR 1',
                'teleponSiswa' => '081234567890',
                'status' => 'Aktif',
                'tanggal_status' => '2026-08-03'
            ]);

        $response2->assertSessionHasNoErrors();

        $this->assertDatabaseHas('siswas', [
            'id' => $siswa->id,
            'status' => 'Aktif',
            'tanggal_status' => null
        ]);
    }

    public function test_admin_can_import_student_with_nis(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this
            ->actingAs($admin)
            ->post('/admin/siswa/import-batch', [
                'rows' => [
                    [
                        'nis' => '12345',
                        'nama' => 'AHMAD FAUZI',
                        'jenisKelamin' => 'Laki-laki',
                        'kelas' => 'X TKR 1',
                        'telepon' => '08123456789',
                    ],
                ]
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('siswas', [
            'nama' => 'AHMAD FAUZI',
            'nis' => '12345',
        ]);
    }

    public function test_admin_can_add_students_with_same_name_in_different_classes(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\KelasSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $kelas1 = \App\Models\Kelas::create(['nama_kelas' => 'X TKR 11', 'tingkat' => 'X']);
        $kelas2 = \App\Models\Kelas::create(['nama_kelas' => 'XI Animasi', 'tingkat' => 'XI']);

        // 1. Simpan Alvin pertama di kelas X TKR 11
        $res1 = $this->actingAs($admin)->post('/admin/siswa/store', [
            'nis' => '1001',
            'nama' => 'ALVIN ZIDNA FAQIH',
            'jenisKelamin' => 'Laki-laki',
            'kelas' => 'X TKR 11',
            'telepon' => '081234567890',
            'status' => 'Aktif',
        ]);
        $res1->assertSessionHasNoErrors();

        // 2. Simpan Alvin kedua di kelas XI Animasi
        $res2 = $this->actingAs($admin)->post('/admin/siswa/store', [
            'nis' => '2001',
            'nama' => 'ALVIN ZIDNA FAQIH',
            'jenisKelamin' => 'Laki-laki',
            'kelas' => 'XI Animasi',
            'telepon' => '089876543210',
            'status' => 'Aktif',
        ]);
        $res2->assertSessionHasNoErrors();

        // Keduanya HARUS ada di database dan tidak saling menimpa
        $this->assertEquals(2, Siswa::where('nama', 'ALVIN ZIDNA FAQIH')->count());
        $this->assertDatabaseHas('siswas', [
            'nama' => 'ALVIN ZIDNA FAQIH',
            'kelas_id' => $kelas1->id,
            'nis' => '1001',
        ]);
        $this->assertDatabaseHas('siswas', [
            'nama' => 'ALVIN ZIDNA FAQIH',
            'kelas_id' => $kelas2->id,
            'nis' => '2001',
        ]);
    }

    public function test_admin_can_save_student_with_nis_and_telepon(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\KelasSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post('/admin/siswa/store', [
            'nis' => '998877',
            'nama' => 'SISWA TES NIS',
            'jenisKelamin' => 'Perempuan',
            'kelas' => 'X TKR 1',
            'telepon' => '081122334455',
            'status' => 'Aktif',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('siswas', [
            'nama' => 'SISWA TES NIS',
            'nis' => '998877',
            'telepon' => '081122334455',
            'jenis_kelamin' => 'Perempuan',
        ]);
    }
}

