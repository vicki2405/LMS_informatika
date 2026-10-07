<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Kelas;
use App\Models\Jurusan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminKelasCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_kelas_with_tingkat(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $jurusan = Jurusan::create([
            'kode' => 'RPL',
            'nama' => 'Rekayasa Perangkat Lunak',
            'deskripsi' => 'Pengembangan Software'
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/admin/kelas/store', [
                'namaKelas' => 'XI RPL 1',
                'tingkat' => 'XI',
                'jurusan' => 'Rekayasa Perangkat Lunak',
                'waliKelas' => ''
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('kelases', [
            'nama_kelas' => 'XI RPL 1',
            'tingkat' => 'XI',
            'jurusan_id' => $jurusan->id
        ]);
    }

    public function test_admin_can_update_kelas_tingkat(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $kelas = Kelas::create([
            'nama_kelas' => 'X TKR 1',
            'tingkat' => 'X'
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/admin/kelas/store', [
                'id' => $kelas->id,
                'namaKelas' => 'XI TKR 1',
                'tingkat' => 'XI',
                'jurusan' => '',
                'waliKelas' => ''
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kelases', [
            'id' => $kelas->id,
            'nama_kelas' => 'XI TKR 1',
            'tingkat' => 'XI'
        ]);
    }

    public function test_create_kelas_requires_valid_tingkat(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this
            ->actingAs($admin)
            ->post('/admin/kelas/store', [
                'namaKelas' => 'IX RPL 1',
                'tingkat' => 'IX', // invalid tingkat
                'jurusan' => '',
                'waliKelas' => ''
            ]);

        $response->assertSessionHasErrors(['tingkat']);
    }
}
