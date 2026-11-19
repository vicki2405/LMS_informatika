<?php

namespace Tests\Feature;

use App\Http\Controllers\Guru\GuruCbtController;
use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtNilai;
use App\Models\CbtSesi;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use App\Services\Cbt\CbtExamService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_cbt_score_synchronizes_identically_to_nilai_tp_and_cleans_up_on_reset(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole('guru');
        $guru = Guru::create(['nama' => 'Guru Informatika', 'user_id' => $guruUser->id]);

        $kelas = Kelas::create(['nama_kelas' => 'X RPL 1', 'tingkat' => 'X']);
        $siswa = Siswa::create([
            'nama' => 'Budi Pratama',
            'nis' => '1001',
            'jenis_kelamin' => 'Laki-laki',
            'kelas_id' => $kelas->id,
            'status' => 'Aktif',
        ]);

        $mapel = MataPelajaran::firstOrCreate(['kode' => 'INF'], ['nama' => 'Informatika']);

        $tp = TujuanPembelajaran::create([
            'kode' => 'TP-01',
            'judul' => 'Algoritma Pemrograman',
            'mapel' => 'Informatika',
            'jenis' => 'Formatif',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'tahun_ajaran_id' => 1,
        ]);

        $bank = CbtBankSoal::create([
            'judul' => 'Bank Soal Algoritma',
            'mata_pelajaran_id' => $mapel->id,
        ]);

        $jadwal = CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'kelas_id' => $kelas->id,
            'guru_id' => $guru->id,
            'judul_ujian' => 'Ujian Algoritma Dasar',
            'status' => 'aktif',
            'durasi_menit' => 60,
        ]);

        // Buat Materi yang menautkan CBT Jadwal ini ke TP-01
        $materi = Materi::create([
            'guru_id' => $guru->id,
            'mapel_id' => $mapel->id,
            'kelas_id' => $kelas->id,
            'tp_id' => $tp->id,
            'cbt_jadwal_id' => $jadwal->id,
            'judul' => 'Materi Algoritma dan Pemrograman',
            'tipe' => 'Modul Teks',
            'is_published' => true,
        ]);

        // Simulasikan pengerjaan CBT dengan nilai 87.5 (7 Benar, 1 Salah dari 8 Soal)
        $cbtNilai = CbtNilai::create([
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $siswa->id,
            'bank_soal_id' => $bank->id,
            'jumlah_soal' => 8,
            'jumlah_benar' => 7,
            'jumlah_salah' => 1,
            'nilai' => 87.5,
            'status' => 'Tuntas',
        ]);

        // Jalankan sinkronisasi
        GuruCbtController::syncCbtNilaiToTp($cbtNilai, $jadwal);

        // 1. Verifikasi nilai di tabel nilai_tps 100% identik dengan hasil CBT
        $nilaiTp = NilaiTp::where('tp_id', $tp->id)->where('siswa_id', $siswa->id)->first();
        $this->assertNotNull($nilaiTp, 'Record NilaiTp harus berhasil dibuat.');
        $this->assertEquals(87.5, (float) $nilaiTp->nilai, 'Nilai TP harus identik dengan nilai CBT.');
        $this->assertStringContainsString('7 Benar, 1 Salah', $nilaiTp->catatan);

        // 2. Verifikasi batch sync juga bekerja untuk dashboard
        $syncedCount = GuruCbtController::syncAllPendingCbtScoresToTp($guru->id);
        $this->assertGreaterThanOrEqual(1, $syncedCount);

        // 3. Verifikasi reset sesi membersihkan nilai siswa
        $sesi = CbtSesi::create([
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $siswa->id,
            'status' => 'selesai',
        ]);
        $req = Request::create(route('guru.cbt.sesi.reset', $sesi->id), 'POST');
        $req->setUserResolver(fn () => $guruUser);
        (new GuruCbtController())->resetSesiSiswa($req, $sesi->id);

        $this->assertNull(NilaiTp::where('tp_id', $tp->id)->where('siswa_id', $siswa->id)->first(), 'Nilai TP harus dibersihkan saat sesi direset.');
    }
}
