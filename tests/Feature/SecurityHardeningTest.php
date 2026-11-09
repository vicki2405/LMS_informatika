<?php

namespace Tests\Feature;

use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtSoal;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_api_login_rejects_user_without_assigned_role(): void
    {
        $user = User::factory()->create([
            'email' => 'norole@sekolah.sch.id',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'norole@sekolah.sch.id',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Akun Anda belum memiliki peran aktif dalam sistem. Hubungi administrator.');
    }

    public function test_student_cannot_submit_homework_for_another_student(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);

        $studentA = Siswa::create([
            'nama' => 'Siswa A',
            'nis' => '1001',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);
        $userA = User::factory()->create(['name' => 'Siswa A', 'kelas_id' => $kelas->id]);
        $userA->assignRole('murid');
        $studentA->update(['user_id' => $userA->id]);

        $studentB = Siswa::create([
            'nama' => 'Siswa B',
            'nis' => '1002',
            'jenis_kelamin' => 'Perempuan',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);

        $tugas = Tugas::create([
            'kelas_id' => $kelas->id,
            'judul' => 'Tugas Keamanan',
            'mata_pelajaran' => 'Keamanan Sistem',
            'tenggat' => now()->addDays(2),
        ]);

        // Student A attempts to submit for Student B using student B's ID
        $response = $this->actingAs($userA)->post(route('siswa.tugas.submit'), [
            'tugasId' => $tugas->id,
            'siswa_id' => $studentB->id,
            'linkHasil' => 'https://hacked.link/exploit',
        ]);

        // Must reject or fallback strictly to Student A's own record, NOT student B
        $this->assertDatabaseMissing('pengumpulan_tugas', [
            'tugas_id' => $tugas->id,
            'siswa_id' => $studentB->id,
        ]);
    }

    public function test_cbt_token_fails_are_rate_limited(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);
        $student = Siswa::create([
            'nama' => 'Siswa Ujian',
            'nis' => '1005',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);
        $user = User::factory()->create(['name' => 'Siswa Ujian', 'kelas_id' => $kelas->id]);
        $user->assignRole('murid');
        $student->update(['user_id' => $user->id]);

        $bank = CbtBankSoal::create(['judul' => 'Bank Rate Limit', 'kelas_id' => $kelas->id]);
        $jadwal = CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'kelas_id' => $kelas->id,
            'judul_ujian' => 'Ujian Rate Limit',
            'durasi_menit' => 60,
            'token' => 'SECRET',
            'status' => 'aktif',
        ]);

        // Attempt 5 incorrect tokens
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/siswa/cbt/start', [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $student->id,
                'token' => 'WRONG' . $i,
            ])->assertForbidden();
        }

        // 6th attempt must be blocked by rate limiter
        $res = $this->actingAs($user, 'sanctum')->postJson('/api/v1/siswa/cbt/start', [
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $student->id,
            'token' => 'SECRET', // Even with the right token, locked out!
        ]);

        $res->assertForbidden()
            ->assertJsonFragment(['success' => false]);
    }

    public function test_cbt_game_mode_does_not_leak_answer_keys(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);
        $student = Siswa::create([
            'nama' => 'Siswa Game',
            'nis' => '1006',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);
        $user = User::factory()->create(['name' => 'Siswa Game', 'kelas_id' => $kelas->id]);
        $user->assignRole('murid');
        $student->update(['user_id' => $user->id]);

        $bank = CbtBankSoal::create(['judul' => 'Bank Game Mode', 'kelas_id' => $kelas->id]);
        CbtSoal::create([
            'bank_soal_id' => $bank->id,
            'nomor_urut' => 1,
            'pertanyaan' => 'Rahasia Soal?',
            'opsi_a' => 'A',
            'opsi_b' => 'B',
            'opsi_c' => 'C',
            'opsi_d' => 'D',
            'opsi_e' => 'E',
            'kunci_jawaban' => 'B',
            'bobot' => 1,
        ]);

        $jadwal = CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'kelas_id' => $kelas->id,
            'judul_ujian' => 'Ujian Mode Game',
            'durasi_menit' => 60,
            'token' => 'GAME99',
            'status' => 'aktif',
            'mode_ujian' => 'game',
        ]);

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/v1/siswa/cbt/start', [
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $student->id,
            'token' => 'GAME99',
        ]);

        $res->assertOk();
        $res->assertJsonMissing(['kunciJawaban' => 'B']);
        $this->assertNull($res->json('soals.0.kunciJawaban'));
    }

    public function test_wali_kelas_api_rejects_when_no_homeroom_assigned(): void
    {
        $user = User::factory()->create(['email' => 'wali.kosong@sekolah.sch.id']);
        $user->assignRole('wali_kelas');

        $kelas = Kelas::create(['nama_kelas' => 'X Lain', 'tingkat' => 'X']);
        $siswa = Siswa::create([
            'nama' => 'Siswa Luar',
            'nis' => '9999',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/v1/wali-kelas/kasus', [
            'siswa_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'nama_kasus' => 'Kasus Ilegal',
            'kategori' => 'Ringan',
            'poin' => 5,
            'status' => 'Selesai',
        ]);

        $res->assertStatus(403);
    }

    public function test_guru_cannot_batch_submit_scores_without_assigned_classes(): void
    {
        $user = User::factory()->create(['email' => 'guru.tanpakelas@sekolah.sch.id']);
        $user->assignRole('guru');
        $guru = Guru::create(['nama' => 'Guru Baru', 'user_id' => $user->id, 'mapel_utama' => 'Fisika']);

        $tp = TujuanPembelajaran::create([
            'kode' => 'TP-FIS-1',
            'judul' => 'Hukum Newton',
            'mapel' => 'Fisika',
            'jenis' => 'Formatif',
            'kktp' => 75,
            'semester' => 'Ganjil',
        ]);

        $kelas = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);
        $siswa = Siswa::create([
            'nama' => 'Siswa Uji',
            'nis' => '1008',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);

        $this->actingAs($user)
            ->post(route('guru.nilai.store-batch'), [
                'tpId' => $tp->id,
                'scores' => [
                    ['siswaId' => $siswa->id, 'nilai' => 90],
                ],
            ])
            ->assertSessionHasErrors('scores');
    }

    public function test_cbt_schedule_only_appears_for_classes_with_scheduled_mapel(): void
    {
        $mapelInf = \App\Models\MataPelajaran::create(['nama' => 'Informatika', 'kode' => 'INF']);
        $mapelKka = \App\Models\MataPelajaran::create(['nama' => 'KKA', 'kode' => 'KKA']);

        $kelasA = Kelas::create(['nama_kelas' => 'X TKR 2', 'tingkat' => 'X']);
        $kelasB = Kelas::create(['nama_kelas' => 'X TSM 3', 'tingkat' => 'X']);

        // Schedule in tab=jadwal:
        \App\Models\Jadwal::create([
            'kelas_id' => $kelasA->id,
            'mata_pelajaran' => 'Informatika',
            'hari' => 'Senin',
            'jam_ke' => 1,
        ]);
        \App\Models\Jadwal::create([
            'kelas_id' => $kelasB->id,
            'mata_pelajaran' => 'KKA',
            'hari' => 'Selasa',
            'jam_ke' => 1,
        ]);

        // Teacher whose profile mapel_utama is Informatika, but teaches KKA in Kelas B (X TSM 3)
        $guru = Guru::create([
            'nama' => 'Guru Informatika & KKA',
            'mapel_utama' => 'Informatika',
        ]);
        \Illuminate\Support\Facades\DB::table('guru_kelas')->insert([
            'guru_id' => $guru->id,
            'kelas_id' => $kelasB->id,
        ]);

        // Student A in class A (Informatika)
        $studentA = Siswa::create([
            'nama' => 'Siswa TKR 2',
            'nis' => '2001',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelasA->id,
            'status' => 'Aktif',
        ]);
        $userA = User::factory()->create(['name' => 'Siswa TKR 2', 'kelas_id' => $kelasA->id]);
        $userA->assignRole('murid');
        $studentA->update(['user_id' => $userA->id]);

        // Student B in class B (KKA only)
        $studentB = Siswa::create([
            'nama' => 'Siswa TSM 3',
            'nis' => '2002',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelasB->id,
            'status' => 'Aktif',
        ]);
        $userB = User::factory()->create(['name' => 'Siswa TSM 3', 'kelas_id' => $kelasB->id]);
        $userB->assignRole('murid');
        $studentB->update(['user_id' => $userB->id]);

        // Bank Soal for Informatika
        $bank = CbtBankSoal::create([
            'judul' => 'Bank Soal Informatika',
            'mata_pelajaran_id' => $mapelInf->id,
        ]);
        CbtSoal::create([
            'bank_soal_id' => $bank->id,
            'nomor_urut' => 1,
            'pertanyaan' => 'Apa itu RAM?',
            'opsi_a' => 'Memory',
            'opsi_b' => 'CPU',
            'opsi_c' => 'Disk',
            'opsi_d' => 'GPU',
            'opsi_e' => 'Power',
            'kunci_jawaban' => 'A',
            'bobot' => 1,
        ]);

        // CBT Jadwal with kelas_id = null (intended for all classes with this subject)
        $jadwal = CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'kelas_id' => null, // Semua kelas ampuan mapel ini
            'judul_ujian' => 'Ujian Tengah Semester Informatika',
            'durasi_menit' => 60,
            'token' => 'INF123',
            'status' => 'aktif',
        ]);

        // Student A (class with Informatika) MUST see the exam
        $resA = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/siswa/cbt/jadwal');
        $resA->assertOk();
        $this->assertCount(1, $resA->json('data'));
        $this->assertEquals($jadwal->id, $resA->json('data.0.id'));

        // Student B (class with KKA only) MUST NOT see the exam via API
        $resB = $this->actingAs($userB, 'sanctum')->getJson('/api/v1/siswa/cbt/jadwal');
        $resB->assertOk();
        $this->assertCount(0, $resB->json('data'));

        // Student B MUST NOT see the exam via Web Inertia Dashboard
        $webResB = $this->actingAs($userB)->get('/siswa/dashboard?tab=cbt');
        $webResB->assertOk();
        $webCbtList = $webResB->inertiaProps('cbtJadwalListProp');
        $this->assertCount(0, $webCbtList);

        // Student B attempting to start exam MUST be rejected
        $this->actingAs($userB, 'sanctum')
            ->postJson('/api/v1/siswa/cbt/start', [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $studentB->id,
                'token' => 'INF123',
            ])
            ->assertForbidden();
    }
}
