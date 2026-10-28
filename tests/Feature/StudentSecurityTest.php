<?php

namespace Tests\Feature;

use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtSesi;
use App\Models\CbtSoal;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected Siswa $student;

    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->kelas = Kelas::create([
            'nama_kelas' => 'X PPLG 1',
            'tingkat' => 'X',
        ]);

        $otherKelas = Kelas::create([
            'nama_kelas' => 'X PPLG 2',
            'tingkat' => 'X',
        ]);

        $this->student = Siswa::create([
            'nama' => 'Siswa Aman',
            'nis' => '1001',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $this->kelas->id,
            'status' => 'Aktif',
        ]);

        Siswa::create([
            'nama' => 'Siswa Lain',
            'nis' => '1002',
            'jenis_kelamin' => 'Perempuan',
            'kelas_id' => $otherKelas->id,
            'status' => 'Aktif',
        ]);

        $this->studentUser = User::factory()->create([
            'name' => 'Nama Bukan Siswa',
            'kelas_id' => $this->kelas->id,
        ]);
        $this->studentUser->assignRole('murid');
        $this->student->update(['user_id' => $this->studentUser->id]);
    }

    public function test_student_dashboard_uses_account_bound_student(): void
    {
        $this->actingAs($this->studentUser)
            ->get(route('siswa.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siswaObjProp.id', $this->student->id)
                ->where('siswaObjProp.nama', 'Nama Bukan Siswa')
            );
    }

    public function test_cbt_schedule_list_hides_token_and_questions(): void
    {
        [$jadwal] = $this->makeCbtExam();

        $this->actingAs($this->studentUser, 'sanctum')
            ->getJson('/api/v1/siswa/cbt/jadwal')
            ->assertOk()
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('data.0.soals')
            ->assertJsonPath('data.0.id', $jadwal->id)
            ->assertJsonPath('data.0.jumlahSoal', 1);
    }

    public function test_cbt_start_requires_the_correct_token(): void
    {
        [$jadwal] = $this->makeCbtExam();

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/siswa/cbt/start', [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $this->student->id,
                'token' => 'SALAH',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('cbt_sesis', [
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $this->student->id,
            'token_entered' => 'SALAH',
        ]);
    }

    public function test_cbt_start_returns_questions_and_submit_requires_started_session(): void
    {
        [$jadwal] = $this->makeCbtExam();

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/siswa/cbt/start', [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $this->student->id,
                'token' => 'ABC123',
            ])
            ->assertOk()
            ->assertJsonPath('soals.0.pertanyaan', 'Berapa 2 + 2?')
            ->assertJsonPath('soals.0.kunciJawaban', null);

        $this->assertDatabaseHas('cbt_sesis', [
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $this->student->id,
            'status' => 'mengerjakan',
        ]);

        CbtSesi::where('jadwal_id', $jadwal->id)
            ->where('siswa_id', $this->student->id)
            ->delete();

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/siswa/cbt/submit', [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $this->student->id,
                'answers' => [],
            ])
            ->assertForbidden();
    }

    public function test_student_cannot_change_their_identity_name(): void
    {
        $this->actingAs($this->studentUser)
            ->patch(route('profile.update'), [
                'name' => 'Nama Palsu',
                'email' => $this->studentUser->email,
            ])
            ->assertSessionHasErrors('name');
    }

    private function makeCbtExam(): array
    {
        $bank = CbtBankSoal::create([
            'judul' => 'Bank Soal Keamanan',
            'kelas_id' => $this->kelas->id,
            'jumlah_pilihan' => 5,
        ]);

        CbtSoal::create([
            'bank_soal_id' => $bank->id,
            'nomor_urut' => 1,
            'pertanyaan' => 'Berapa 2 + 2?',
            'opsi_a' => '1',
            'opsi_b' => '2',
            'opsi_c' => '3',
            'opsi_d' => '4',
            'opsi_e' => '5',
            'kunci_jawaban' => 'D',
            'bobot' => 1,
        ]);

        $jadwal = CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'kelas_id' => $this->kelas->id,
            'judul_ujian' => 'Ujian Keamanan',
            'durasi_menit' => 60,
            'token' => 'ABC123',
            'status' => 'aktif',
            'mode_ujian' => 'formal',
        ]);

        return [$jadwal, $bank];
    }
}
