<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuruApiSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruUser;
    protected Guru $guru;
    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->guruUser = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi.guru@sekolah.sch.id',
        ]);
        $this->guruUser->assignRole('guru');

        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nama' => 'Budi Santoso',
            'nip' => '198501012010011001',
            'mata_pelajaran' => 'Informatika',
        ]);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'X RPL 1',
            'tingkat' => 'X',
        ]);
    }

    public function test_guru_api_can_store_and_delete_jadwal(): void
    {
        Sanctum::actingAs($this->guruUser);

        $response = $this->postJson('/api/v1/guru/jadwal', [
            'hari' => 'Senin',
            'jam_ke' => 'Jam 1 - 2',
            'kelas' => 'X RPL 1',
            'mata_pelajaran' => 'Informatika',
            'ruangan' => 'Lab Komputer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('jadwals', [
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'hari' => 'Senin',
            'mata_pelajaran' => 'Informatika',
        ]);

        $jadwal = Jadwal::where('guru_id', $this->guru->id)->first();

        // Delete schedule
        $delResponse = $this->deleteJson('/api/v1/guru/jadwal/' . $jadwal->id);
        $delResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('jadwals', ['id' => $jadwal->id]);
    }

    public function test_guru_api_can_sync_and_get_kelas(): void
    {
        Sanctum::actingAs($this->guruUser);

        // Save kelas ampuan
        $saveResponse = $this->postJson('/api/v1/guru/kelas-ampuan', [
            'kelas_ids' => [$this->kelas->id],
        ]);
        $saveResponse->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('guru_kelas', [
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
        ]);

        // Get kelas
        $getResponse = $this->getJson('/api/v1/guru/kelas');
        $getResponse->assertOk()
            ->assertJsonPath('success', true);

        // Remove kelas ampuan
        $delResponse = $this->deleteJson('/api/v1/guru/kelas-ampuan/' . $this->kelas->id);
        $delResponse->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('guru_kelas', [
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
        ]);
    }

    public function test_guru_api_can_clear_and_swap_tp_scores(): void
    {
        Sanctum::actingAs($this->guruUser);
        $this->guru->kelasAmpuan()->attach($this->kelas->id);

        $siswa = Siswa::create([
            'nama' => 'Ahmad Rizky',
            'nis' => '1001',
            'kelas_id' => $this->kelas->id,
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);

        $tp1 = TujuanPembelajaran::create([
            'kode' => 'TP01',
            'judul' => 'Dasar Pemrograman',
            'mapel' => 'Informatika',
        ]);

        $tp2 = TujuanPembelajaran::create([
            'kode' => 'TP02',
            'judul' => 'Basis Data',
            'mapel' => 'Informatika',
        ]);

        NilaiTp::create([
            'tp_id' => $tp1->id,
            'siswa_id' => $siswa->id,
            'nilai' => 85,
            'guru_id' => $this->guru->id,
        ]);

        NilaiTp::create([
            'tp_id' => $tp2->id,
            'siswa_id' => $siswa->id,
            'nilai' => 95,
            'guru_id' => $this->guru->id,
        ]);

        // Swap TP
        $swapResponse = $this->postJson('/api/v1/guru/nilai/swap-tp', [
            'sourceTpId' => $tp1->id,
            'targetTpId' => $tp2->id,
            'kelas' => 'X RPL 1',
            'mode' => 'swap',
        ]);
        $swapResponse->assertOk()->assertJsonPath('success', true);

        $this->assertEquals(95, NilaiTp::where('tp_id', $tp1->id)->where('siswa_id', $siswa->id)->value('nilai'));
        $this->assertEquals(85, NilaiTp::where('tp_id', $tp2->id)->where('siswa_id', $siswa->id)->value('nilai'));

        // Clear Kolom
        $clearResponse = $this->postJson('/api/v1/guru/nilai/clear-kolom', [
            'tpId' => $tp1->id,
            'kelas' => 'X RPL 1',
        ]);
        $clearResponse->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('nilai_tps', [
            'tp_id' => $tp1->id,
            'siswa_id' => $siswa->id,
        ]);
    }
}

