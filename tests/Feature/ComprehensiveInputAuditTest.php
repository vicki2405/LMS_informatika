<?php

namespace Tests\Feature;

use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtNilai;
use App\Models\CbtSoal;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\KasusSiswa;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComprehensiveInputAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruUser;
    protected Guru $guru;
    protected User $waliKelasUser;
    protected Guru $waliGuru;
    protected User $adminUser;
    protected User $siswaUser;
    protected Siswa $siswa;
    protected Kelas $kelas;
    protected TahunAjaran $tahunAjaran;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);

        $this->tahunAjaran = TahunAjaran::firstOrCreate(
            ['kode' => '2026/2027'],
            ['is_aktif' => true]
        );

        $this->kelas = Kelas::create([
            'nama_kelas' => 'X PPLG 1',
            'tingkat' => 'X',
        ]);

        MataPelajaran::create([
            'nama' => 'Informatika',
            'kode' => 'INF',
        ]);

        $this->guruUser = User::factory()->create([
            'name' => 'Guru Audit',
            'email' => 'audit_guru@sekolah.sch.id',
        ]);
        $this->guruUser->assignRole('guru');

        $this->guru = Guru::create([
            'user_id' => $this->guruUser->id,
            'nama' => 'Guru Audit',
            'nip' => '198501012010011001',
            'mata_pelajaran' => 'Informatika',
            'telepon' => '081234567890',
        ]);

        $this->waliKelasUser = User::factory()->create([
            'name' => 'Wali Kelas Audit',
            'email' => 'audit_walikelas@sekolah.sch.id',
            'kelas_id' => $this->kelas->id,
        ]);
        $this->waliKelasUser->assignRole('wali_kelas');

        $this->waliGuru = Guru::create([
            'user_id' => $this->waliKelasUser->id,
            'nama' => 'Wali Kelas Audit',
            'nip' => '198601012010011002',
            'mata_pelajaran' => 'Informatika',
            'kelas_binaan' => $this->kelas->nama_kelas,
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin Audit',
            'email' => 'audit_admin@sekolah.sch.id',
        ]);
        $this->adminUser->assignRole('admin');

        $this->siswa = Siswa::create([
            'nama' => 'Siswa Audit',
            'kelas_id' => $this->kelas->id,
            'nis' => '12345',
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);

        $this->siswaUser = User::factory()->create([
            'name' => 'Siswa Audit',
            'email' => 'audit_siswa@sekolah.sch.id',
            'kelas_id' => $this->kelas->id,
        ]);
        $this->siswaUser->assignRole('murid');
    }

    /** Test 1: TP Input with various edge case jenis and semester */
    public function test_guru_input_tp_edge_cases(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.tp.store'), [
            'kode' => 'TP 1.1',
            'mapel' => 'Informatika',
            'judul' => 'Memahami algoritma pemrograman dasar',
            'jenis' => 'Praktik',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'tahunAjaran' => '2026/2027',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tujuan_pembelajarans', [
            'kode' => 'TP 1.1',
            'jenis' => 'Praktik',
        ]);

        $response = $this->actingAs($this->guruUser)->post(route('guru.tp.store'), [
            'kode' => 'TP 1.2',
            'mapel' => 'Informatika',
            'judul' => 'Membuat proyek web sederhana',
            'jenis' => 'Proyek',
            'kktp' => 80,
            'semester' => 'Genap',
            'tahunAjaran' => '2026/2027',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tujuan_pembelajarans', [
            'kode' => 'TP 1.2',
            'jenis' => 'Proyek',
        ]);
    }

    /** Test 2: Jurnal Mengajar input */
    public function test_guru_input_jurnal(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.jurnal.store'), [
            'tanggal' => date('Y-m-d'),
            'kelas' => $this->kelas->nama_kelas,
            'jam_ke' => 'Jam 1 - 2',
            'mata_pelajaran' => 'Informatika',
            'materi' => 'Pengenalan Python',
            'uraian' => 'Siswa mempelajari sintaks variabel dan tipe data.',
            'catatan' => 'Semua siswa hadir tepat waktu.',
            'semester' => 'Ganjil',
            'tahun_pelajaran' => '2026/2027',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jurnals', [
            'materi' => 'Pengenalan Python',
            'kelas_id' => $this->kelas->id,
        ]);
    }

    /** Test 3: Absensi Batch Input */
    public function test_guru_input_absensi_batch(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.absensi.store-batch'), [
            'tanggal' => date('Y-m-d'),
            'kelas' => $this->kelas->nama_kelas,
            'rows' => [
                [
                    'siswaId' => $this->siswa->id,
                    'status' => 'Hadir',
                    'keterangan' => 'Hadir tepat waktu',
                ],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('absensis', [
            'siswa_id' => $this->siswa->id,
            'status' => 'Hadir',
        ]);
    }

    /** Test 4: Nilai Batch Input */
    public function test_guru_input_nilai_batch(): void
    {
        $tp = TujuanPembelajaran::create([
            'kode' => 'TP-AUDIT-1',
            'judul' => 'TP Audit Nilai',
            'kktp' => 75,
            'jenis' => 'Formatif',
            'semester' => 'Ganjil',
            'tahun_ajaran_id' => $this->tahunAjaran->id,
        ]);

        $response = $this->actingAs($this->guruUser)->post(route('guru.nilai.store-batch'), [
            'tpId' => $tp->id,
            'scores' => [
                [
                    'siswaId' => $this->siswa->id,
                    'nilai' => 88.5,
                    'catatan' => 'Sangat baik',
                ],
            ],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nilai_tps', [
            'tp_id' => $tp->id,
            'siswa_id' => $this->siswa->id,
            'nilai' => 88.5,
        ]);
    }

    /** Test 5: Materi Input (Slides & Upload) */
    public function test_guru_input_materi(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.materi.store'), [
            'judul' => 'Materi Bab 1 Audit',
            'tipe' => 'slides',
            'theme' => 'dark',
            'semester' => 'Ganjil',
            'deskripsi' => 'Pengantar materi',
            'kegiatan' => json_encode([
                [
                    'label' => 'Slide 1',
                    'judul' => 'Pendahuluan',
                    'konten' => '<p>Selamat datang di bab 1</p>',
                ],
            ]),
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('materis', [
            'judul' => 'Materi Bab 1 Audit',
        ]);
    }

    /** Test 6: Bank Soal & Soal CBT Input */
    public function test_guru_input_cbt_bank_soal_and_soal(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.cbt.bank-soal.store'), [
            'judul' => 'Bank Soal Audit Informatika',
            'mata_pelajaran_id' => 1,
            'tingkat' => 'X',
        ]);
        $response->assertSessionHasNoErrors();
        $bankSoal = CbtBankSoal::where('judul', 'Bank Soal Audit Informatika')->firstOrFail();

        $response = $this->actingAs($this->guruUser)->post(route('guru.cbt.soal.store'), [
            'bank_soal_id' => $bankSoal->id,
            'tipe_soal' => 'pilihan_ganda',
            'pertanyaan' => 'Berapakah 2 + 2?',
            'opsi_a' => '1',
            'opsi_b' => '2',
            'opsi_c' => '3',
            'opsi_d' => '4',
            'opsi_e' => '5',
            'kunci_jawaban' => 'D',
            'bobot' => 1.0,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cbt_soals', [
            'bank_soal_id' => $bankSoal->id,
            'pertanyaan' => 'Berapakah 2 + 2?',
            'kunci_jawaban' => 'D',
        ]);

        $response = $this->actingAs($this->guruUser)->post(route('guru.cbt.jadwal.store'), [
            'bank_soal_id' => $bankSoal->id,
            'kelas_id' => $this->kelas->id,
            'judul_ujian' => 'Penilaian Harian 1',
            'durasi_menit' => 60,
            'token' => 'AUDIT1',
            'status' => 'aktif',
            'mode_ujian' => 'formal',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cbt_jadwals', [
            'bank_soal_id' => $bankSoal->id,
            'token' => 'AUDIT1',
        ]);
    }

    /** Test 7: Wali Kelas Kasus Input */
    public function test_wali_kelas_input_kasus(): void
    {
        $response = $this->actingAs($this->waliKelasUser)->post(route('wali-kelas.kasus.store'), [
            'siswaId' => $this->siswa->id,
            'tanggal' => date('Y-m-d'),
            'namaKasus' => 'Terlambat masuk sekolah',
            'kategori' => 'Ringan',
            'poin' => 5,
            'tindakan' => 'Teguran lisan oleh wali kelas',
            'status' => 'Dalam Pemantauan',
            'catatan' => 'Siswa berjanji tidak mengulangi',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kasus_siswas', [
            'siswa_id' => $this->siswa->id,
            'nama_kasus' => 'Terlambat masuk sekolah',
            'kategori' => 'Ringan',
        ]);
    }

    /** Test 8: Admin Input Siswa with edge case gender */
    public function test_admin_input_siswa(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.siswa.store'), [
            'nis' => '998877',
            'nama' => 'Siswa Baru Admin Test',
            'jenisKelamin' => 'Perempuan',
            'kelas' => $this->kelas->nama_kelas,
            'telepon' => '08999999999',
            'status' => 'Aktif',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('siswas', [
            'nis' => '998877',
            'nama' => 'Siswa Baru Admin Test',
            'jenis_kelamin' => 'Perempuan',
        ]);
    }

    /** Test 9: CBT Transfer to TP (both new_tp and existing_tp mode) */
    public function test_cbt_transfer_nilai_to_tp(): void
    {
        $bankSoal = CbtBankSoal::create([
            'guru_id' => $this->guru->id,
            'mata_pelajaran_id' => 1,
            'judul' => 'Bank Soal Transfer Test',
            'tingkat' => 'X',
        ]);

        $jadwal = CbtJadwal::create([
            'bank_soal_id' => $bankSoal->id,
            'kelas_id' => $this->kelas->id,
            'guru_id' => $this->guru->id,
            'judul_ujian' => 'Ujian Akhir Semester',
            'status' => 'aktif',
            'token' => 'TRF01',
        ]);

        CbtNilai::create([
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $this->siswa->id,
            'nilai' => 90,
            'jumlah_benar' => 9,
            'jumlah_salah' => 1,
            'total_soal' => 10,
        ]);

        // Transfer to new TP
        $response = $this->actingAs($this->guruUser)->post(route('guru.cbt.transfer-nilai-tp'), [
            'jadwal_id' => $jadwal->id,
            'mode' => 'new_tp',
            'kode' => 'TP-CBT-TEST',
            'judul' => 'Ujian Akhir Semester (CBT)',
            'jenis' => 'Sumatif',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'overwrite' => true,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tujuan_pembelajarans', [
            'kode' => 'TP-CBT-TEST',
            'jenis' => 'Sumatif',
        ]);
        $tp = TujuanPembelajaran::where('kode', 'TP-CBT-TEST')->firstOrFail();
        $this->assertDatabaseHas('nilai_tps', [
            'tp_id' => $tp->id,
            'siswa_id' => $this->siswa->id,
            'nilai' => 90,
        ]);
    }

    /** Test 10: Guru Save Kelas Ampuan */
    public function test_guru_save_kelas_ampuan(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.kelas-ampuan.save'), [
            'kelas_ids' => [$this->kelas->id],
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('guru_kelas', [
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
        ]);
    }

    /** Test 11: Guru Schedule (Jadwal) Input */
    public function test_guru_store_jadwal(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('guru.jadwal.store'), [
            'hari' => 'Senin',
            'jam_ke' => 'Jam 1 - 2',
            'kelas' => $this->kelas->nama_kelas,
            'mata_pelajaran' => 'Informatika',
            'ruangan' => 'Lab Komputer 1',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jadwals', [
            'guru_id' => $this->guru->id,
            'hari' => 'Senin',
            'jam_ke' => 'Jam 1 - 2',
            'kelas_id' => $this->kelas->id,
        ]);
    }

    /** Test 12: Siswa Submit Tugas */
    public function test_siswa_submit_tugas(): void
    {
        $tugas = \App\Models\Tugas::create([
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'judul' => 'Tugas Portofolio HTML',
            'mata_pelajaran' => 'Informatika',
            'deskripsi' => 'Kumpulkan link repo github',
            'tenggat' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->siswaUser)->post(route('siswa.tugas.submit'), [
            'tugasId' => $tugas->id,
            'siswa_id' => $this->siswa->id,
            'linkHasil' => 'https://github.com/siswa/portofolio',
            'catatan' => 'Selesai tepat waktu',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pengumpulan_tugas', [
            'tugas_id' => $tugas->id,
            'siswa_id' => $this->siswa->id,
            'status' => 'Sudah Dikirim',
        ]);
    }

    /** Test 13: Guru dapat mengosongkan seluruh nilai satu kolom TP */
    public function test_guru_can_clear_nilai_tp_kolom(): void
    {
        $tp = \App\Models\TujuanPembelajaran::create([
            'kode' => 'TP-CLR-1',
            'judul' => 'Uji Coba Clear Kolom',
            'mapel' => 'Informatika',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2025/2026',
        ]);

        \App\Models\NilaiTp::create([
            'tp_id' => $tp->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'nilai' => 88.5,
            'catatan' => 'Bagus',
        ]);

        $this->assertDatabaseHas('nilai_tps', [
            'tp_id' => $tp->id,
            'siswa_id' => $this->siswa->id,
            'nilai' => 88.5,
        ]);

        $response = $this->actingAs($this->guruUser)->post(route('guru.nilai.clear-kolom'), [
            'tpId' => $tp->id,
            'kelas' => $this->kelas->nama_kelas,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('nilai_tps', [
            'tp_id' => $tp->id,
            'siswa_id' => $this->siswa->id,
        ]);
    }

    /** Test 14: Guru dapat menukar (swap) nilai antar-TP untuk kelas terpilih */
    public function test_guru_can_swap_nilai_antar_tp(): void
    {
        $tpA = \App\Models\TujuanPembelajaran::create([
            'kode' => 'TP-SWP-A',
            'judul' => 'Materi A',
            'mapel' => 'Informatika',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2025/2026',
        ]);

        $tpB = \App\Models\TujuanPembelajaran::create([
            'kode' => 'TP-SWP-B',
            'judul' => 'Materi B',
            'mapel' => 'Informatika',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2025/2026',
        ]);

        \App\Models\NilaiTp::create([
            'tp_id' => $tpA->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'nilai' => 70,
            'catatan' => 'Nilai Awal TP A',
        ]);

        \App\Models\NilaiTp::create([
            'tp_id' => $tpB->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'nilai' => 95,
            'catatan' => 'Nilai Awal TP B',
        ]);

        $response = $this->actingAs($this->guruUser)->post(route('guru.nilai.swap-tp'), [
            'sourceTpId' => $tpA->id,
            'targetTpId' => $tpB->id,
            'kelas' => $this->kelas->nama_kelas,
            'mode' => 'swap',
        ]);

        $response->assertSessionHasNoErrors();

        // Nilai TP A sekarang harus 95 (milik B sebelumnya)
        $this->assertDatabaseHas('nilai_tps', [
            'tp_id' => $tpA->id,
            'siswa_id' => $this->siswa->id,
            'nilai' => 95,
            'catatan' => 'Nilai Awal TP B',
        ]);

        // Nilai TP B sekarang harus 70 (milik A sebelumnya)
        $this->assertDatabaseHas('nilai_tps', [
            'tp_id' => $tpB->id,
            'siswa_id' => $this->siswa->id,
            'nilai' => 70,
            'catatan' => 'Nilai Awal TP A',
        ]);
    }

    /** Test 15: Guru dapat memindahkan (move) nilai dari TP Asal ke TP Tujuan */
    public function test_guru_can_move_nilai_antar_tp(): void
    {
        $tpSrc = \App\Models\TujuanPembelajaran::create([
            'kode' => 'TP-MOV-SRC',
            'judul' => 'Source TP',
            'mapel' => 'Informatika',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2025/2026',
        ]);

        $tpTgt = \App\Models\TujuanPembelajaran::create([
            'kode' => 'TP-MOV-TGT',
            'judul' => 'Target TP',
            'mapel' => 'Informatika',
            'kktp' => 75,
            'semester' => 'Ganjil',
            'tahun_ajaran' => '2025/2026',
        ]);

        \App\Models\NilaiTp::create([
            'tp_id' => $tpSrc->id,
            'siswa_id' => $this->siswa->id,
            'guru_id' => $this->guru->id,
            'nilai' => 85,
            'catatan' => 'Pindahan dari TP Asal',
        ]);

        $response = $this->actingAs($this->guruUser)->post(route('guru.nilai.swap-tp'), [
            'sourceTpId' => $tpSrc->id,
            'targetTpId' => $tpTgt->id,
            'kelas' => $this->kelas->nama_kelas,
            'mode' => 'move',
        ]);

        $response->assertSessionHasNoErrors();

        // Nilai TP Asal harus terhapus/kosong
        $this->assertDatabaseMissing('nilai_tps', [
            'tp_id' => $tpSrc->id,
            'siswa_id' => $this->siswa->id,
        ]);

        // Nilai TP Tujuan harus berisi nilai 85
        $this->assertDatabaseHas('nilai_tps', [
            'tp_id' => $tpTgt->id,
            'siswa_id' => $this->siswa->id,
            'nilai' => 85,
            'catatan' => 'Pindahan dari TP Asal',
        ]);
    }

    /** Test 16: Guru dapat membuka Dedicated Page Tambah Jadwal CBT */
    public function test_guru_can_render_cbt_jadwal_create_page(): void
    {
        $response = $this->actingAs($this->guruUser)->get(route('guru.cbt.jadwal.create'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Guru/CbtJadwalForm')
            ->has('bankSoalList')
            ->has('masterKelasList')
            ->has('jurusanList')
            ->where('jadwalProp', null)
        );
    }

    /** Test 17: Guru dapat membuka Dedicated Page Edit Jadwal CBT */
    public function test_guru_can_render_cbt_jadwal_edit_page(): void
    {
        $bank = \App\Models\CbtBankSoal::create([
            'kode' => 'BANK-JAD-1',
            'judul' => 'Bank Soal Uji Jadwal',
            'mata_pelajaran_id' => \App\Models\MataPelajaran::first()->id,
            'guru_id' => $this->guru->id,
            'tingkat' => 'X',
        ]);

        $jadwal = \App\Models\CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'guru_id' => $this->guru->id,
            'judul_ujian' => 'Ujian Tengah Semester Informatika',
            'durasi_menit' => 90,
            'token' => 'UTS90X',
            'status' => 'aktif',
            'mode_ujian' => 'game',
        ]);

        $response = $this->actingAs($this->guruUser)->get(route('guru.cbt.jadwal.edit', $jadwal->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Guru/CbtJadwalForm')
            ->where('jadwalProp.id', $jadwal->id)
            ->where('jadwalProp.token', 'UTS90X')
            ->where('jadwalProp.mode_ujian', 'game')
        );
    }

    /** Test 18: Guru dapat menyimpan Jadwal CBT dan redirect kembali ke tab cbt-jadwal */
    public function test_guru_can_store_cbt_jadwal_and_redirects_to_cbt_jadwal_tab(): void
    {
        $bank = \App\Models\CbtBankSoal::create([
            'kode' => 'BANK-JAD-2',
            'judul' => 'Bank Soal Baru',
            'mata_pelajaran_id' => \App\Models\MataPelajaran::first()->id,
            'guru_id' => $this->guru->id,
            'tingkat' => 'X',
        ]);

        $response = $this->actingAs($this->guruUser)->post(route('guru.cbt.jadwal.store'), [
            'bank_soal_id' => $bank->id,
            'judul_ujian' => 'Penilaian Harian Jaringan Komputer',
            'durasi_menit' => 45,
            'token' => 'PHJK01',
            'acak_soal' => true,
            'acak_opsi' => true,
            'status' => 'aktif',
            'mode_ujian' => 'formal',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('guru.dashboard') . '?tab=cbt-jadwal');

        $this->assertDatabaseHas('cbt_jadwals', [
            'judul_ujian' => 'Penilaian Harian Jaringan Komputer',
            'token' => 'PHJK01',
            'durasi_menit' => 45,
            'mode_ujian' => 'formal',
        ]);
    }

    /** Test 19: Menghapus Jadwal CBT tidak menghapus nilai siswa (Soft Delete) */
    public function test_deleting_cbt_jadwal_preserves_student_scores(): void
    {
        $bank = \App\Models\CbtBankSoal::create([
            'kode' => 'BANK-PRESERVE-1',
            'judul' => 'Bank Soal Aman Nilai',
            'mata_pelajaran_id' => \App\Models\MataPelajaran::first()->id,
            'guru_id' => $this->guru->id,
            'tingkat' => 'X',
        ]);

        $jadwal = \App\Models\CbtJadwal::create([
            'bank_soal_id' => $bank->id,
            'guru_id' => $this->guru->id,
            'judul_ujian' => 'Ujian Akhir Yang Dihapus',
            'durasi_menit' => 60,
            'token' => 'KEEP99',
            'status' => 'aktif',
            'mode_ujian' => 'formal',
        ]);

        $nilai = \App\Models\CbtNilai::create([
            'jadwal_id' => $jadwal->id,
            'siswa_id' => $this->siswa->id,
            'bank_soal_id' => $bank->id,
            'jumlah_soal' => 20,
            'jumlah_benar' => 18,
            'jumlah_salah' => 2,
            'nilai' => 90.00,
            'status' => 'Tuntas',
        ]);

        // Guru menghapus jadwal
        $response = $this->actingAs($this->guruUser)->delete(route('guru.cbt.jadwal.delete', $jadwal->id));
        $response->assertSessionHas('message');

        // Pastikan jadwal di soft-delete (tidak tampil di query normal)
        $this->assertSoftDeleted('cbt_jadwals', ['id' => $jadwal->id]);
        $this->assertNull(\App\Models\CbtJadwal::find($jadwal->id));
        $this->assertNotNull(\App\Models\CbtJadwal::withTrashed()->find($jadwal->id));

        // Pastikan nilai siswa TIDAK terhapus dari database!
        $this->assertDatabaseHas('cbt_nilais', [
            'id' => $nilai->id,
            'jadwal_id' => $jadwal->id,
            'nilai' => 90.00,
        ]);

        // Pastikan daftar nilai (Hasil Nilai CBT) tetap dapat membaca nilai ini
        $nilaiList = \App\Http\Controllers\Guru\GuruCbtController::getNilaisList($this->guru->id);
        $found = collect($nilaiList)->firstWhere('id', $nilai->id);
        $this->assertNotNull($found, 'Nilai siswa harus tetap muncul di daftar hasil nilai');
        $this->assertEquals(90.00, $found['nilai']);
        $this->assertEquals('Ujian Akhir Yang Dihapus', $found['judulUjian']);
    }
}
