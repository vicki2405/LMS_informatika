<?php

namespace Tests\Feature;

use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Cbt\CbtExamService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncEdgeCaseAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_materi_monitoring_strictly_filters_classes_by_teacher_subject_schedule(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole('guru');
        $guru = Guru::create(['nama' => 'Guru KKA', 'user_id' => $guruUser->id]);

        $kelasKka = Kelas::create(['nama_kelas' => 'XII AK 1', 'tingkat' => 'XII']);
        $kelasRpl = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);

        $mapelKka = MataPelajaran::firstOrCreate(['kode' => 'KKA'], ['nama' => 'Konsentrasi Keahlian Akuntansi']);

        // Guru mengajar KKA di XII AK 1, dan mapel lain di X RPL 1
        Jadwal::create([
            'guru_id' => $guru->id,
            'kelas_id' => $kelasKka->id,
            'mata_pelajaran' => 'KKA',
            'hari' => 'Senin',
            'jam_ke' => '1',
            'waktu' => '07:00 - 08:00',
        ]);
        Jadwal::create([
            'guru_id' => $guru->id,
            'kelas_id' => $kelasRpl->id,
            'mata_pelajaran' => 'Informatika',
            'hari' => 'Selasa',
            'jam_ke' => '1',
            'waktu' => '07:00 - 08:00',
        ]);

        $materi = Materi::create([
            'guru_id' => $guru->id,
            'mapel_id' => $mapelKka->id,
            'kelas_id' => null, // Semua Kelas
            'judul' => 'Materi Akuntansi Keuangan',
            'tipe' => 'Modul Teks',
            'is_published' => true,
        ]);

        $res = $this->actingAs($guruUser)
            ->get(route('guru.materi.monitoring', $materi->id));

        $res->assertOk();
        $props = $res->original->getData()['page']['props'];

        // masterKelasList HARUS hanya berisi XII AK 1, X RPL 1 TIDAK BOLEH ikut
        $masterKelasIds = collect($props['masterKelasList'])->pluck('id')->all();
        $this->assertContains($kelasKka->id, $masterKelasIds);
        $this->assertNotContains($kelasRpl->id, $masterKelasIds);
    }

    public function test_cbt_exam_service_matches_subject_with_parentheses_and_codes(): void
    {
        $mapel = MataPelajaran::firstOrCreate(['kode' => 'KKA'], ['nama' => 'Konsentrasi Keahlian Akuntansi']);
        $kelas = Kelas::create(['nama_kelas' => 'XII AK 1', 'tingkat' => 'XII']);
        $siswa = Siswa::create([
            'nama' => 'Siti Aminah',
            'nis' => '1002',
            'jenis_kelamin' => 'Perempuan',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);

        // Jadwal kelas tertulis 'KKA'
        Jadwal::create([
            'kelas_id' => $kelas->id,
            'mata_pelajaran' => 'KKA',
            'hari' => 'Senin',
            'jam_ke' => '1',
            'waktu' => '07:00 - 08:00',
        ]);

        $bank = CbtBankSoal::create([
            'judul' => 'Ujian KKA Akhir',
            'mata_pelajaran_id' => $mapel->id,
        ]);

        $jadwal = CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'kelas_id' => null, // Semua Kelas
            'judul_ujian' => 'Ujian Akhir KKA',
            'status' => 'aktif',
            'durasi_menit' => 60,
        ]);

        $service = app(CbtExamService::class);

        // Tidak boleh throw ValidationException karena mapel KKA cocok dengan Konsentrasi Keahlian Akuntansi
        $service->assertJadwalEligible($jadwal, $siswa);
        $this->assertTrue(true);
    }
}
