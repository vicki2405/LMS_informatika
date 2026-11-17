<?php

namespace Tests\Feature;

use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtSoal;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CbtInteractiveModeTest extends TestCase
{
    use RefreshDatabase;

    private User $studentUser;

    private Siswa $student;

    private CbtJadwal $jadwalGame;

    private CbtSoal $soalPg;

    private CbtSoal $soalMatch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $kelas = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);
        $this->student = Siswa::create([
            'nama' => 'Budi Santoso',
            'nis' => '1001',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);
        $this->studentUser = User::factory()->create(['name' => 'Budi Santoso', 'kelas_id' => $kelas->id]);
        $this->studentUser->assignRole('murid');
        $this->student->update(['user_id' => $this->studentUser->id]);

        $bank = CbtBankSoal::create(['judul' => 'Bank Soal Interaktif', 'kelas_id' => $kelas->id]);
        $this->soalPg = CbtSoal::create([
            'bank_soal_id' => $bank->id,
            'nomor_urut' => 1,
            'pertanyaan' => 'Ibu kota Indonesia saat ini adalah?',
            'opsi_a' => 'Jakarta',
            'opsi_b' => 'Bandung',
            'opsi_c' => 'Surabaya',
            'opsi_d' => 'Medan',
            'opsi_e' => 'Semarang',
            'kunci_jawaban' => 'A',
            'bobot' => 1,
            'tipe_soal' => 'pilihan_ganda',
        ]);

        $this->soalMatch = CbtSoal::create([
            'bank_soal_id' => $bank->id,
            'nomor_urut' => 2,
            'pertanyaan' => 'Jodohkan bahasa pemrograman dengan peruntukannya:',
            'tipe_soal' => 'match',
            'opsi_a' => '-',
            'opsi_b' => '-',
            'opsi_c' => '-',
            'opsi_d' => '-',
            'opsi_e' => '-',
            'kunci_jawaban' => 'A',
            'data_interaktif' => [
                ['id' => '1', 'kiri' => 'PHP', 'kanan' => 'Backend Web'],
                ['id' => '2', 'kiri' => 'Swift', 'kanan' => 'iOS App'],
            ],
            'bobot' => 2,
        ]);

        $this->jadwalGame = CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'kelas_id' => $kelas->id,
            'judul_ujian' => 'Kuis Mode Interaktif',
            'durasi_menit' => 30,
            'token' => 'GAME12',
            'status' => 'aktif',
            'mode_ujian' => 'game',
        ]);
    }

    public function test_check_jawaban_pg_returns_correct_flag_without_leaking_key(): void
    {
        // 1. Jawaban benar
        $resCorrect = $this->actingAs($this->studentUser)
            ->postJson(route('siswa.cbt.check-jawaban'), [
                'jadwal_id' => $this->jadwalGame->id,
                'soal_id' => $this->soalPg->id,
                'jawaban' => 'A',
                'siswa_id' => $this->student->id,
            ]);

        $resCorrect->assertOk()
            ->assertJson([
                'success' => true,
                'is_correct' => true,
                'xp' => 100,
            ])
            ->assertJsonMissing(['kunci_jawaban' => 'A'])
            ->assertJsonMissing(['kunciJawaban' => 'A']);

        // 2. Jawaban salah
        $resWrong = $this->actingAs($this->studentUser)
            ->postJson(route('siswa.cbt.check-jawaban'), [
                'jadwal_id' => $this->jadwalGame->id,
                'soal_id' => $this->soalPg->id,
                'jawaban' => 'B',
                'siswa_id' => $this->student->id,
            ]);

        $resWrong->assertOk()
            ->assertJson([
                'success' => true,
                'is_correct' => false,
                'xp' => 0,
            ])
            ->assertJsonMissing(['kunci_jawaban' => 'A'])
            ->assertJsonMissing(['kunciJawaban' => 'A']);
    }

    public function test_api_check_jawaban_evaluates_match_question(): void
    {
        // Jawaban menjodohkan benar
        $resCorrect = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/siswa/cbt/check-jawaban', [
                'jadwal_id' => $this->jadwalGame->id,
                'soal_id' => $this->soalMatch->id,
                'jawaban' => [
                    '1' => 'Backend Web',
                    '2' => 'iOS App',
                ],
                'siswa_id' => $this->student->id,
            ]);

        $resCorrect->assertOk()
            ->assertJson([
                'success' => true,
                'is_correct' => true,
                'xp' => 100,
            ]);

        // Jawaban menjodohkan salah
        $resWrong = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/v1/siswa/cbt/check-jawaban', [
                'jadwal_id' => $this->jadwalGame->id,
                'soal_id' => $this->soalMatch->id,
                'jawaban' => [
                    '1' => 'iOS App',
                    '2' => 'Backend Web',
                ],
                'siswa_id' => $this->student->id,
            ]);

        $resWrong->assertOk()
            ->assertJson([
                'success' => true,
                'is_correct' => false,
                'xp' => 0,
            ]);
    }

    public function test_student_cannot_toggle_exam_mode(): void
    {
        $response = $this->actingAs($this->studentUser)
            ->postJson('/siswa/cbt/toggle-mode', [
                'jadwal_id' => $this->jadwalGame->id,
                'mode_ujian' => 'formal',
            ]);

        $response->assertNotFound();
    }
}
