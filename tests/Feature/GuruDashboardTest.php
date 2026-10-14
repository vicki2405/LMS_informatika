<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuruDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_can_render_dashboard_with_natural_sorted_classes(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['name' => 'Pak Budi Guru']);
        $user->assignRole('guru');

        $guru = Guru::create([
            'nama' => 'Pak Budi Guru',
            'nip' => '198501012010011001',
            'email' => $user->email,
            'mapel_utama' => 'Informatika',
            'status' => 'Aktif',
            'user_id' => $user->id,
        ]);

        $jurusan = Jurusan::create([
            'kode' => 'TKR',
            'nama' => 'Teknik Kendaraan Ringan',
        ]);

        $k10 = Kelas::create(['nama_kelas' => 'X TKR 10', 'tingkat' => 'X', 'jurusan_id' => $jurusan->id]);
        $k1 = Kelas::create(['nama_kelas' => 'X TKR 1', 'tingkat' => 'X', 'jurusan_id' => $jurusan->id]);
        $k2 = Kelas::create(['nama_kelas' => 'X TKR 2', 'tingkat' => 'X', 'jurusan_id' => $jurusan->id]);

        DB::table('guru_kelas')->insert([
            ['guru_id' => $guru->id, 'kelas_id' => $k10->id],
            ['guru_id' => $guru->id, 'kelas_id' => $k1->id],
            ['guru_id' => $guru->id, 'kelas_id' => $k2->id],
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/guru/dashboard');

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Guru/Dashboard')
            ->has('kelasListProp', 3)
            ->where('kelasListProp.0.namaKelas', 'X TKR 1')
            ->where('kelasListProp.1.namaKelas', 'X TKR 2')
            ->where('kelasListProp.2.namaKelas', 'X TKR 10')
            ->has('masterKelasListProp', 3)
            ->where('masterKelasListProp.0.namaKelas', 'X TKR 1')
            ->where('masterKelasListProp.1.namaKelas', 'X TKR 2')
            ->where('masterKelasListProp.2.namaKelas', 'X TKR 10')
        );
    }

    public function test_guru_can_create_update_and_delete_jurnal(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        /** @var User $user */
        $user = User::factory()->create(['name' => 'Bu Rina Guru']);
        $user->assignRole('guru');

        $guru = Guru::create([
            'nama' => 'Bu Rina Guru',
            'nip' => '198701012010012002',
            'email' => $user->email,
            'mapel_utama' => 'Informatika',
            'status' => 'Aktif',
            'user_id' => $user->id,
        ]);

        $jurusan = Jurusan::create([
            'kode' => 'RPL',
            'nama' => 'Rekayasa Perangkat Lunak',
        ]);

        $kelas = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_id' => $jurusan->id]);

        DB::table('guru_kelas')->insert([
            'guru_id' => $guru->id,
            'kelas_id' => $kelas->id,
        ]);

        // 1. Store Jurnal
        $storeResponse = $this->actingAs($user)->post('/guru/jurnal/store', [
            'tanggal' => '2026-10-14',
            'jam_ke' => 'Jam 1 - 2',
            'kelas' => 'X RPL 1',
            'mata_pelajaran' => 'Informatika',
            'materi' => 'Pengenalan Algoritma',
            'uraian' => 'Membahas logika percabangan dan perulangan.',
            'catatan' => 'Siswa antusias.',
            'tahun_pelajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('jurnals', [
            'guru_id' => $guru->id,
            'kelas_id' => $kelas->id,
            'materi' => 'Pengenalan Algoritma',
        ]);

        $jurnal = \App\Models\Jurnal::where('materi', 'Pengenalan Algoritma')->first();
        $this->assertNotNull($jurnal);

        // 2. Update Jurnal
        $updateResponse = $this->actingAs($user)->post('/guru/jurnal/store', [
            'id' => $jurnal->id,
            'tanggal' => '2026-10-14',
            'jam_ke' => 'Jam 3 - 4',
            'kelas' => 'X RPL 1',
            'mata_pelajaran' => 'Informatika',
            'materi' => 'Algoritma Lanjut',
            'uraian' => 'Implementasi flowchart ke kode.',
            'catatan' => 'Semua tuntas.',
            'tahun_pelajaran' => '2026/2027',
            'semester' => 'Ganjil',
        ]);

        $updateResponse->assertRedirect();
        $this->assertDatabaseHas('jurnals', [
            'id' => $jurnal->id,
            'materi' => 'Algoritma Lanjut',
            'jam_ke' => 'Jam 3 - 4',
        ]);

        // 3. Delete Jurnal
        $deleteResponse = $this->actingAs($user)->delete("/guru/jurnal/{$jurnal->id}");
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('jurnals', ['id' => $jurnal->id]);
    }
}
