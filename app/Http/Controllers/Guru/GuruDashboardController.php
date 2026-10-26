<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\CbtJadwal;
use App\Models\MateriRead;
use App\Services\Security\FileSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GuruDashboardController extends Controller
{
    /**
     * Resolve Guru model from authenticated user by user_id or email fallback.
     */
    private function resolveGuru(Request $request): ?Guru
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        if ($user->guru) {
            return $user->guru;
        }

        return Guru::query()
            ->where('user_id', '=', $user->id)
            ->orWhere('nama', '=', $user->name)
            ->first();
    }

    /**
     * Get array of class IDs associated with this teacher (via guru_kelas pivot and jadwals).
     */
    private function getTeacherKelasIds(?int $guruId): array
    {
        if (! $guruId) {
            return [];
        }

        $pivotIds = DB::table('guru_kelas')
            ->where('guru_id', '=', $guruId)
            ->pluck('kelas_id')
            ->toArray();

        $jadwalIds = Jadwal::query()
            ->where('guru_id', '=', $guruId)
            ->pluck('kelas_id')
            ->toArray();

        return array_unique(array_merge($pivotIds, $jadwalIds));
    }

    /**
     * Render main Subject Teacher Dashboard view with live data.
     */
    public function index(Request $request): Response
    {
        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;

        $kelasIds = $this->getTeacherKelasIds($guruId);
        $teacherMapels = $this->getTeacherMapelList($guru);
        $teacherMapelIds = collect($teacherMapels)->pluck('id')->filter()->toArray();

        return Inertia::render('Guru/Dashboard', [
            'teacherSubjectProp' => $guru?->mapel_utama ?? 'Informatika',
            'tahunAjaranListProp' => fn () => TahunAjaran::query()->orderBy('is_aktif', 'desc')->orderBy('id', 'desc')->get(),
            'jurusanListProp' => fn () => Jurusan::query()->orderBy('id', 'asc')->get(),
            'kelasListProp' => fn () => $this->getKelasData($guruId),
            'masterKelasListProp' => fn () => $this->getMasterKelasData(),
            'siswaListProp' => fn () => $this->getSiswaData($guruId, $kelasIds),
            'jadwalListProp' => fn () => $this->getJadwalData($guruId),
            'absensiListProp' => fn () => $this->getAbsensiData($guruId, $kelasIds),
            'jurnalListProp' => fn () => $this->getJurnalData($guruId),
            'tpListProp' => fn () => $this->getTpData($guru?->mapel_utama),
            'nilaiListProp' => fn () => $this->getNilaiData($guruId, $kelasIds),
            'materiListProp' => fn () => $this->getMateriData($guruId, $kelasIds, $teacherMapelIds),
            'mapelListProp' => fn () => $teacherMapels,
            'cbtBankSoalListProp' => fn () => GuruCbtController::getBankSoalsList($guruId),
            'cbtJadwalListProp' => fn () => GuruCbtController::getJadwalsList($guruId),
            'cbtNilaiListProp' => fn () => GuruCbtController::getNilaisList($guruId),
        ]);
    }

    /**
     * Get kelas list filtered to classes this guru teaches.
     */
    /**
     * Get list of MataPelajaran that this teacher actually teaches according to schedule & mapel_utama.
     */
    private function getTeacherMapelList(?Guru $guru): array
    {
        if (! $guru) {
            return [];
        }

        $scheduleMapelNames = Jadwal::query()
            ->where('guru_id', $guru->id)
            ->pluck('mata_pelajaran')
            ->filter()
            ->unique()
            ->toArray();

        $utamaNames = [];
        if (! empty($guru->mapel_utama)) {
            $parts = explode(',', $guru->mapel_utama);
            foreach ($parts as $p) {
                $trimmed = trim($p);
                if ($trimmed !== '') {
                    $utamaNames[] = $trimmed;
                }
            }
        }

        $allNames = array_unique(array_merge($scheduleMapelNames, $utamaNames));
        if (empty($allNames)) {
            return [];
        }

        $matched = MataPelajaran::query()->where(function ($q) use ($allNames) {
            foreach ($allNames as $name) {
                $q->orWhere('nama', 'LIKE', "%{$name}%")
                  ->orWhere('kode', 'LIKE', "%{$name}%");
            }
        })->orderBy('nama', 'asc')->get();

        $result = $matched->map(fn ($mp) => [
            'id' => $mp->id,
            'kode' => $mp->kode,
            'nama' => $mp->nama,
            'kelompok' => $mp->kelompok,
        ])->toArray();

        if (empty($result) && ! empty($allNames)) {
            foreach ($allNames as $idx => $name) {
                $result[] = [
                    'id' => 9000 + $idx,
                    'kode' => strtoupper(substr($name, 0, 4)),
                    'nama' => $name,
                    'kelompok' => 'Kejuruan / Umum',
                ];
            }
        }

        return $result;
    }

    private function getKelasData(?int $guruId = null): array
    {
        $query = Kelas::query()->with(['jurusan', 'waliKelas'])->withCount('siswas');

        if ($guruId) {
            $kelasIds = $this->getTeacherKelasIds($guruId);
            if (! empty($kelasIds)) {
                $query->whereIn('id', $kelasIds);
            } else {
                return [];
            }
        }

        return $query->get()
            ->sort(function ($a, $b) {
                $t = strcmp($a->tingkat ?? 'X', $b->tingkat ?? 'X');
                if ($t !== 0) return $t;
                return strnatcasecmp($a->nama_kelas, $b->nama_kelas);
            })
            ->values()
            ->map(function ($k) {
            return [
                'id' => $k->id,
                'nama' => $k->nama_kelas,
                'namaKelas' => $k->nama_kelas,
                'tingkat' => $k->tingkat ?? 'X',
                'jurusan' => $k->jurusan?->nama ?? $k->nama_kelas,
                'waliKelas' => $k->waliKelas?->nama ?? 'Belum Ditugaskan',
                'jumlahSiswa' => $k->siswas_count ?? $k->jumlah_siswa,
            ];
        })->toArray();
    }

    /**
     * Get ALL school classes for the class selection modal (not filtered by guru).
     */
    private function getMasterKelasData(): array
    {
        return Kelas::query()->with(['jurusan'])->get()
            ->sort(function ($a, $b) {
                $t = strcmp($a->tingkat ?? 'X', $b->tingkat ?? 'X');
                if ($t !== 0) return $t;
                return strnatcasecmp($a->nama_kelas, $b->nama_kelas);
            })
            ->values()
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'namaKelas' => $k->nama_kelas,
                    'tingkat' => $k->tingkat ?? 'X',
                    'jurusan' => $k->jurusan?->nama ?? '',
                    'jurusanId' => $k->jurusan_id,
                    'jurusanKode' => $k->jurusan?->kode ?? '',
                ];
            })->toArray();
    }

    private function getSiswaData(?int $guruId = null, array $kelasIds = []): array
    {
        if ($guruId && empty($kelasIds)) {
            return [];
        }

        $query = Siswa::query()
            ->select(['id', 'nis', 'nama', 'jenis_kelamin', 'kelas_id', 'telepon', 'status', 'tanggal_status'])
            ->with(['kelas:id,nama_kelas,tingkat,guru_id', 'kelas.waliKelas:id,nama'])
            ->orderBy('nama', 'asc');

        if (! empty($kelasIds)) {
            $query->whereIn('kelas_id', $kelasIds);
        }

        return $query->get()
            ->map(function ($s) {
            return [
                'id' => $s->id,
                'nis' => $s->nis ?: ('100'.$s->id),
                'nama' => $s->nama,
                'jenisKelamin' => $s->jenis_kelamin,
                'kelas' => $s->kelas?->nama_kelas ?? 'X RPL 1',
                'tingkat' => $s->kelas?->tingkat ?? 'X',
                'waliKelas' => $s->kelas?->waliKelas?->nama ?? '-',
                'telepon' => $s->telepon ?? '-',
                'status' => $s->status ?? 'Aktif',
                'tanggal_status' => $s->tanggal_status,
            ];
        })->toArray();
    }

    private function getJadwalData(?int $guruId = null): array
    {
        if (! $guruId) {
            return [];
        }

        $query = Jadwal::query()
            ->with('kelas')
            ->where('guru_id', '=', $guruId)
            ->orderByRaw("CASE 
                WHEN hari = 'Senin' THEN 1 
                WHEN hari = 'Selasa' THEN 2 
                WHEN hari = 'Rabu' THEN 3 
                WHEN hari = 'Kamis' THEN 4 
                WHEN hari = 'Jumat' THEN 5 
                WHEN hari = 'Sabtu' THEN 6 
                ELSE 7 END")
            ->orderBy('jam_ke', 'asc');

        return $query->get()->map(function ($j) {
            return [
                'id' => $j->id,
                'hari' => $j->hari,
                'jamKe' => $j->jam_ke,
                'waktu' => $j->waktu,
                'kelasId' => $j->kelas_id,
                'kelas' => $j->kelas?->nama_kelas ?? 'X RPL 1',
                'namaKelas' => $j->kelas?->nama_kelas ?? 'X RPL 1',
                'tingkat' => $j->kelas?->tingkat ?? 'X',
                'jurusan' => $j->kelas?->jurusan?->nama ?? '',
                'mataPelajaran' => $j->mata_pelajaran,
                'ruangan' => $j->ruangan ?? 'Lab Komputer 1',
                'keterangan' => $j->keterangan ?? '-',
            ];
        })->toArray();
    }

    private function getAbsensiData(?int $guruId = null, array $kelasIds = []): array
    {
        $query = Absensi::query()
            ->select(['id', 'tanggal', 'kelas_id', 'siswa_id', 'status', 'keterangan'])
            ->with(['kelas:id,nama_kelas', 'siswa:id,nama'])
            ->orderBy('tanggal', 'desc');

        if (! empty($kelasIds)) {
            $query->whereIn('kelas_id', $kelasIds, 'and', false);
        }

        // Ambil data absensi tahun pelajaran berjalan (12 bulan) agar matriks bulanan guru lengkap & akurat
        $query->where('tanggal', '>=', now()->subMonths(12)->format('Y-m-d'));

        return $query->limit(3000)->get()->map(function ($a) {
            $tglStr = is_string($a->tanggal)
                ? substr($a->tanggal, 0, 10)
                : ($a->tanggal ? $a->tanggal->format('Y-m-d') : '');

            return [
                'id' => $a->id,
                'tanggal' => $tglStr,
                'kelas' => $a->kelas?->nama_kelas ?? 'X RPL 1',
                'siswaId' => $a->siswa_id,
                'namaSiswa' => $a->siswa?->nama ?? '-',
                'status' => $a->status,
                'keterangan' => $a->keterangan ?? $a->status,
            ];
        })->toArray();
    }

    private function getJurnalData(?int $guruId = null): array
    {
        $query = Jurnal::query()->with('kelas')->orderBy('tanggal', 'desc')->orderBy('id', 'desc')->limit(500);
        if ($guruId) {
            $query->where('guru_id', '=', $guruId);
        }

        return $query->get()->map(function ($ju) {
            return [
                'id' => $ju->id,
                'tanggal' => $ju->tanggal,
                'jamKe' => $ju->jam_ke,
                'kelas' => $ju->kelas?->nama_kelas ?? 'X RPL 1',
                'mataPelajaran' => $ju->mata_pelajaran,
                'materi' => $ju->materi,
                'uraian' => $ju->uraian,
                'catatan' => $ju->catatan,
                'tahunPelajaran' => $ju->tahun_pelajaran ?? '2026/2027',
                'semester' => $ju->semester ?? 'Ganjil',
            ];
        })->toArray();
    }

    private function getTpData(?string $mapelUtama = null): array
    {
        $query = TujuanPembelajaran::query()->with('tahunAjaran')->orderBy('kode', 'asc');

        // Hanya tampilkan TP yang sesuai mapel guru yang login (mendukung multi-mapel comma-separated)
        if (!empty($mapelUtama)) {
            $mapelList = array_filter(array_map('trim', explode(',', $mapelUtama)));
            $query->where(function ($q) use ($mapelList) {
                $q->whereIn('mapel', $mapelList)
                  ->orWhere('mapel', '=', 'Umum')
                  ->orWhereNull('mapel');
                foreach ($mapelList as $m) {
                    $q->orWhere('mapel', 'LIKE', "%{$m}%");
                }
            });
        }

        return $query->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'kode' => $t->kode,
                'mapel' => $t->mapel ?? 'Umum',
                'judul' => $t->judul,
                'jenis' => $t->jenis,
                'kktp' => $t->kktp,
                'semester' => $t->semester,
                'tahunAjaran' => $t->tahunAjaran?->kode ?? '2026/2027',
            ];
        })->toArray();
    }

    private function getNilaiData(?int $guruId = null, array $kelasIds = []): array
    {
        $query = NilaiTp::query()->with(['tp', 'siswa.kelas']);
        if (! empty($kelasIds)) {
            $query->whereHas('siswa', function ($q) use ($kelasIds) {
                $q->whereIn('kelas_id', $kelasIds);
            });
        }

        return $query->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'tpId' => $n->tp_id,
                'siswaId' => $n->siswa_id,
                'kelas' => $n->siswa?->kelas?->nama_kelas ?? 'X RPL 1',
                'nilai' => $n->nilai,
                'catatan' => $n->catatan,
            ];
        })->toArray();
    }

    private function getMateriData(?int $guruId = null, array $kelasIds = [], array $mapelIds = []): array
    {
        $query = Materi::query()->with(['tp', 'kelas', 'mapel', 'kegiatans'])->orderBy('id', 'desc');
        if (! empty($kelasIds)) {
            $query->where(function ($q) use ($kelasIds) {
                $q->whereIn('kelas_id', $kelasIds)->orWhereNull('kelas_id');
            });
        }
        if (! empty($mapelIds)) {
            $query->where(function ($q) use ($mapelIds) {
                $q->whereIn('mapel_id', $mapelIds)->orWhereNull('mapel_id');
            });
        }

        return $query->limit(500)->get()->map(fn (Materi $m) => $this->serializeMateri($m))->toArray();
    }

    private function serializeMateri(Materi $m): array
    {
        $mapelName = $m->mapel?->nama ?? $m->tp?->mapel ?? '';
        $targetKelas = $m->kelas?->nama_kelas ?? ($mapelName ? "Semua Kelas ({$mapelName})" : 'Semua Kelas');

        return [
            'id' => $m->id,
            'tpId' => $m->tp_id,
            'tpKode' => $m->tp?->kode ?? 'TP-01',
            'kelasId' => $m->kelas_id,
            'targetKelas' => $targetKelas,
            'mapelId' => $m->mapel_id,
            'mapelNama' => $m->mapel?->nama ?? $m->tp?->mapel ?? '',
            'judul' => $m->judul,
            'tipe' => $m->tipe,
            'fileUrl' => $m->file_path ? asset('storage/'.$m->file_path) : ($m->file_url ?? ''),
            'filePath' => $m->file_path,
            'fileName' => $m->file_path ? basename($m->file_path) : '',
            'deskripsiSingkat' => $m->deskripsi ?? '',
            'kegiatan' => $m->kegiatans->map(fn ($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'layout' => $s->layout ?? 'standard',
                'judul' => $s->judul,
                'konten' => $s->konten,
                'filePath' => $s->file_path,
                'fileName' => $s->file_path ? basename($s->file_path) : '',
                'fileUrl' => $s->file_path ? asset('storage/'.$s->file_path) : ($s->file_url ?? ''),
            ])->values(),
            'theme' => $m->theme ?? 'dark',
            'cbtJadwalId' => $m->cbt_jadwal_id,
            'cbtJadwal' => $m->cbtJadwal ? [
                'id' => $m->cbtJadwal->id,
                'judul' => $m->cbtJadwal->judul_ujian,
                'durasi' => $m->cbtJadwal->durasi_menit,
            ] : null,
            'isPublished' => $m->is_published,
            'semester' => $m->semester,
            'readsCount' => $m->reads()->count(),
            'readsCompletedCount' => $m->reads()->where('status', 'selesai')->count(),
        ];
    }

    /**
     * Render the dedicated Materi creation form page.
     */
    public function createMateri(Request $request): Response
    {
        return $this->materiFormData($request, null);
    }

    /**
     * Render the dedicated Materi edit form page.
     */
    public function editMateri(int $id, Request $request): Response
    {
        $materi = Materi::query()->with(['tp', 'mapel', 'kelas', 'kegiatans'])->findOrFail($id);

        return $this->materiFormData($request, $materi);
    }

    private function materiFormData(Request $request, ?Materi $materi): Response
    {
        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;
        $teacherMapels = $this->getTeacherMapelList($guru);
        $teacherKelas = $this->getKelasData($guruId);
        $teacherCbtJadwals = GuruCbtController::getJadwalsList($guruId);

        $teacherMapelNames = collect($teacherMapels)->pluck('nama')->merge(
            !empty($guru?->mapel_utama) ? array_map('trim', explode(',', $guru->mapel_utama)) : []
        )->unique()->filter()->implode(',');

        return Inertia::render('Guru/MateriForm', [
            'materiProp' => $materi ? $this->serializeMateri($materi) : null,
            'mapelListProp' => $teacherMapels,
            'kelasListProp' => $teacherKelas,
            'masterKelasListProp' => $this->getMasterKelasData(),
            'jadwalListProp' => $this->getJadwalData($guruId),
            'tpListProp' => $this->getTpData($teacherMapelNames ?: $guru?->mapel_utama),
            'cbtJadwalListProp' => collect($teacherCbtJadwals)->map(fn ($j) => [
                'id' => $j['id'],
                'judul' => $j['judulUjian'] ?? ($j['bankSoalJudul'] ?? 'Ujian CBT'),
                'kelasId' => $j['kelasId'] ?? null,
                'durasi' => $j['durasiMenit'] ?? 60,
            ])->values()->toArray(),
        ]);
    }

    /**
     * Store or update batch attendance atomically via PostgreSQL.
     */
    public function storeAbsensiBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'kelas' => 'required|string',
            'rows' => 'required|array',
        ]);

        $kelasObj = Kelas::query()->where('nama_kelas', '=', $validated['kelas'])->first();

        if (! $kelasObj) {
            throw ValidationException::withMessages(['kelas' => 'Kelas "'.$validated['kelas'].'" tidak ditemukan di database! Presensi batal disimpan.']);
        }

        $kelasId = $kelasObj->id;
        $tglStr = date('Y-m-d', strtotime($validated['tanggal']));

        $inactiveSiswaIds = Siswa::query()
            ->whereIn('status', ['Keluar', 'Pindah'], 'and', false)
            ->pluck('id')
            ->toArray();

        DB::transaction(function () use ($validated, $kelasId, $tglStr, $inactiveSiswaIds) {
            foreach ($validated['rows'] as $row) {
                if (empty($row['status']) || in_array($row['siswaId'], $inactiveSiswaIds)) {
                    continue;
                }

                Absensi::query()->updateOrCreate(
                    [
                        'tanggal' => $tglStr,
                        'siswa_id' => $row['siswaId'],
                    ],
                    [
                        'kelas_id' => $kelasId,
                        'status' => $row['status'],
                        'keterangan' => $row['keterangan'] ?? $row['status'],
                    ]
                );
            }
        });

        return redirect()->back()->with('message', 'Presensi kelas berhasil disimpan ke database!');
    }

    /**
     * Delete a single attendance record.
     */
    public function deleteAbsensi(int $id): RedirectResponse
    {
        Absensi::query()->where('id', '=', $id)->delete();

        return redirect()->back()->with('message', 'Catatan absensi berhasil dihapus!');
    }

    /**
     * Update the status of a single attendance record (inline correction).
     */
    public function updateAbsensi(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Hadir,Sakit,Izin,Alpa',
        ]);

        $record = Absensi::query()->with('siswa')->findOrFail($id);
        if ($record->siswa && in_array($record->siswa->status, ['Keluar', 'Pindah'])) {
            return redirect()->back()->with('error', 'Presensi tidak dapat diubah karena siswa telah berstatus '.$record->siswa->status.'!');
        }

        $record->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('message', 'Status absensi berhasil diperbarui!');
    }

    /**
     * Delete all attendance records for a specific date + kelas (bulk cancel).
     */
    public function deleteAbsensiBulkDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal'  => 'required|date_format:Y-m-d',
            'kelas_id' => 'required|integer|exists:kelases,id',
        ]);

        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;
        $teacherKelasIds = $this->getTeacherKelasIds($guruId);

        if ($guruId && ! in_array((int) $validated['kelas_id'], $teacherKelasIds) && ! $request->user()->hasRole('admin')) {
            return redirect()->back()->withErrors(['kelas_id' => 'Anda tidak memiliki wewenang untuk membatalkan absensi kelas ini.']);
        }

        $deleted = Absensi::query()
            ->where('tanggal', '=', $validated['tanggal'])
            ->where('kelas_id', '=', $validated['kelas_id'])
            ->delete();

        return redirect()->back()->with('message', "Absensi tanggal {$validated['tanggal']} berhasil dibatalkan ({$deleted} record dihapus).");
    }

    /**
     * Store or update batch TP scores atomically via PostgreSQL.
     */
    public function storeNilaiBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tpId' => 'required|integer',
            'scores' => 'required|array',
        ]);

        $user = $request->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', '=', $user?->id)->first();
        $guruId = $guru?->id;

        DB::transaction(function () use ($validated, $guruId) {
            foreach ($validated['scores'] as $s) {
                if ($s['nilai'] !== null && $s['nilai'] !== '') {
                    NilaiTp::query()->updateOrCreate(
                        [
                            'tp_id' => $validated['tpId'],
                            'siswa_id' => $s['siswaId'],
                        ],
                        [
                            'nilai' => floatval($s['nilai']),
                            'catatan' => $s['catatan'] ?? null,
                            'guru_id' => $guruId,
                        ]
                    );
                } else {
                    NilaiTp::query()->where('tp_id', '=', $validated['tpId'])->where('siswa_id', '=', $s['siswaId'])->delete();
                }
            }
        });

        return redirect()->back()->with('message', 'Nilai TP kelas berhasil disimpan ke database!');
    }

    /**
     * Store or update Jurnal Mengajar.
     */
    public function storeJurnal(Request $request): RedirectResponse
    {
        $user = $request->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', '=', $user?->id, 'and')->first();
        $guruId = $guru?->id ?? $user->id;

        $jamKe = $request->input('jam_ke') ?? $request->input('jamKe') ?? 'Jam 1 - 2';
        $mataPelajaran = $request->input('mata_pelajaran') ?? $request->input('mataPelajaran') ?? 'Informatika';
        $defaultTaKode = TahunAjaran::getActive()?->kode ?? '2026/2027';
        $tahunPelajaran = $request->input('tahun_pelajaran') ?? $request->input('tahunPelajaran') ?? $defaultTaKode;
        $semester = $request->input('semester') ?? 'Ganjil';

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'tanggal' => 'required|date',
            'kelas' => 'required|string',
            'materi' => 'required|string',
            'uraian' => 'nullable|string',
            'catatan' => 'nullable|string',
            'tahun_pelajaran' => 'nullable|string',
            'tahunPelajaran' => 'nullable|string',
            'semester' => 'nullable|string',
        ]);

        $kelasObj = Kelas::query()->where('nama_kelas', '=', $validated['kelas'])->first();

        if (! $kelasObj) {
            throw ValidationException::withMessages(['kelas' => 'Kelas "'.$validated['kelas'].'" tidak ditemukan di database! Jurnal batal disimpan.']);
        }

        Jurnal::query()->updateOrCreate(
            ['id' => $validated['id'] ?? null],
            [
                'guru_id' => $guruId,
                'tanggal' => $validated['tanggal'],
                'jam_ke' => $jamKe,
                'kelas_id' => $kelasObj->id,
                'mata_pelajaran' => $mataPelajaran,
                'materi' => $validated['materi'],
                'uraian' => $validated['uraian'] ?? null,
                'catatan' => $validated['catatan'] ?? null,
                'tahun_pelajaran' => $tahunPelajaran,
                'semester' => $semester,
            ]
        );

        return redirect()->back()->with('message', 'Jurnal mengajar berhasil disimpan!');
    }

    /**
     * Delete Jurnal Mengajar.
     */
    public function deleteJurnal(int $id): RedirectResponse
    {
        $user = request()->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', '=', $user?->id)->first();
        $guruId = $guru?->id;

        $query = Jurnal::query()->where('id', '=', $id);
        if ($guruId && ! $user->hasRole('admin')) {
            $query->where('guru_id', '=', $guruId);
        }
        $query->delete();

        return redirect()->back()->with('message', 'Jurnal mengajar berhasil dihapus!');
    }

    /**
     * Export Jurnal Mengajar as Printable PDF HTML.
     */
    public function exportJurnalPdf(Request $request)
    {
        $user = $request->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', '=', $user?->id, 'and')->first();
        $guruId = $guru?->id ?? $user?->id;

        $kelasFilter = $request->query('kelas', 'Semua');
        $mapelFilter = $request->query('mapel', 'Semua');
        $month = $request->query('month');
        $year = $request->query('year');

        $query = Jurnal::query()->with('kelas')->orderBy('tanggal', 'asc')->orderBy('id', 'asc');
        if ($guruId) {
            $query->where('guru_id', '=', $guruId);
        }

        if ($kelasFilter && $kelasFilter !== 'Semua') {
            $query->whereHas('kelas', function ($q) use ($kelasFilter) {
                $q->where('nama_kelas', '=', $kelasFilter);
            });
        }

        if ($mapelFilter && $mapelFilter !== 'Semua') {
            $query->where('mata_pelajaran', '=', $mapelFilter);
        }

        if ($month && $year) {
            $query->whereYear('tanggal', $year)->whereMonth('tanggal', $month);
        }

        $jurnals = $query->get();

        $teacherName = $guru?->nama ?? $user?->name ?? 'Misbachul Munafiki, S.Kom';
        $teacherSubject = ($mapelFilter && $mapelFilter !== 'Semua')
            ? strtoupper($mapelFilter)
            : strtoupper($guru?->mapel_utama ?? 'SEMUA MATA PELAJARAN');

        $firstJurnal = $jurnals->first();
        $targetKelas = ($kelasFilter && $kelasFilter !== 'Semua') ? $kelasFilter : ($firstJurnal?->kelas?->nama_kelas ?? 'Semua Kelas');
        $rawTp = $firstJurnal?->tahun_pelajaran ?? '2026 - 2027';
        $tahunPelajaranStr = str_starts_with($rawTp, 'Tahun') ? $rawTp : "Tahun Pelajaran {$rawTp}";
        $targetSemester = $firstJurnal?->semester ?? 'Ganjil';

        $rowsHtml = '';
        foreach ($jurnals as $index => $item) {
            $num = $index + 1;
            $tgl = date('d-m-Y', strtotime($item->tanggal));
            $jam = $item->jam_ke;

            if (preg_match_all('/\d+/', $jam, $m) && count($m[0]) >= 2) {
                $min = min(intval($m[0][0]), intval($m[0][1]));
                $max = max(intval($m[0][0]), intval($m[0][1]));
                $jamShort = ($min === $max) ? "Jam {$min}" : "Jam {$min} - {$max}";
            } else {
                $jamShort = $jam;
            }

            $kelasName = e($item->kelas?->nama_kelas ?? '-');
            $mapelName = e($item->mata_pelajaran ?? '-');
            $materi = e($item->materi);
            $uraian = e($item->uraian ?? '-');
            $catatan = e($item->catatan ?? '-');

            $rowsHtml .= "
                <tr>
                    <td style=\"padding: 6px; border: 1px solid #000; text-align: center;\">{$num}</td>
                    <td style=\"padding: 6px; border: 1px solid #000; font-weight: bold;\">{$tgl}</td>
                    <td style=\"padding: 6px; border: 1px solid #000; text-align: center;\">{$jamShort}</td>
                    <td style=\"padding: 6px; border: 1px solid #000; text-align: center; font-weight: bold;\">{$kelasName}</td>
                    <td style=\"padding: 6px; border: 1px solid #000;\">{$mapelName}</td>
                    <td style=\"padding: 6px; border: 1px solid #000; font-weight: bold;\">{$materi}</td>
                    <td style=\"padding: 6px; border: 1px solid #000;\">{$uraian}</td>
                    <td style=\"padding: 6px; border: 1px solid #000;\">{$catatan}</td>
                </tr>
            ";
        }

        if (empty($rowsHtml)) {
            $rowsHtml = '<tr><td colspan="8" style="text-align: center; padding: 15px;">Belum ada catatan jurnal mengajar.</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Jurnal Mengajar - {$teacherName}</title>
    <style>
        @page { size: 215mm 330mm portrait; margin: 10mm; }
        body { font-family: Arial, sans-serif; padding: 15px; color: #000000; line-height: 1.4; }
        .title-box { text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .subtitle-box { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 16px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 12px; font-weight: bold; }
        .meta-table td { border: none; padding: 3px 0; vertical-align: top; }
        .meta-table td.label { width: 150px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 5px; font-size: 11px; }
        table.data-table th { background-color: #f1f5f9; color: #000000; border: 1px solid #000000; padding: 6px; text-align: center; font-weight: bold; }
        table.data-table td { border: 1px solid #000000; padding: 6px; vertical-align: top; }
    </style>
</head>
<body>
    <div class="title-box">JURNAL MENGAJAR GURU</div>
    <div class="subtitle-box">{$tahunPelajaranStr}</div>

    <table class="meta-table">
        <tr>
            <td class="label">Nama Sekolah</td>
            <td>: SMKS MUHAMMADIYAH 2 GENTENG  BANYUWANGI</td>
        </tr>
        <tr>
            <td class="label">Nama Pendidik</td>
            <td>: {$teacherName}</td>
        </tr>
        <tr>
            <td class="label">Mata Pelajaran</td>
            <td>: {$teacherSubject}</td>
        </tr>
        <tr>
            <td class="label">Kelas / Semester</td>
            <td>: {$targetKelas} / {$targetSemester}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 28px; text-align: center;">No.</th>
                <th style="width: 80px; text-align: center;">Hari & Tanggal</th>
                <th style="width: 65px; text-align: center;">Jam</th>
                <th style="width: 65px; text-align: center;">Kelas</th>
                <th style="width: 110px; text-align: left;">Mata Pelajaran</th>
                <th style="width: 22%; text-align: left;">Pokok Bahasan / Materi</th>
                <th style="width: 28%; text-align: left;">Uraian Kegiatan Pembelajaran</th>
                <th style="width: 18%; text-align: left;">Catatan & Kendala</th>
            </tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
    </table>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Store or update Materi (with optional file upload & flexible learning phases).
     */
    public function storeMateri(Request $request): RedirectResponse
    {
        $tpId = $request->input('tp_id') ?? $request->input('tpId');
        $kelasId = $request->input('kelas_id') ?? $request->input('kelasId');
        $mapelId = $request->input('mapel_id') ?? $request->input('mapelId');
        $cbtJadwalId = $request->input('cbt_jadwal_id') ?? $request->input('cbtJadwalId');
        $theme = $request->input('theme', 'dark');
        $fileUrl = $request->input('file_url') ?? $request->input('fileUrl');
        $deskripsi = $request->input('deskripsi') ?? $request->input('deskripsiSingkat');
        $isPublished = $request->has('is_published') ? $request->boolean('is_published') : ($request->has('isPublished') ? $request->boolean('isPublished') : true);

        $tpId = $tpId === '' ? null : $tpId;
        $kelasId = ($kelasId === '' || $kelasId === 'all' || $kelasId === 'null') ? null : $kelasId;
        $mapelId = $mapelId === '' ? null : $mapelId;
        $cbtJadwalId = $cbtJadwalId === '' ? null : $cbtJadwalId;

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'judul' => 'required|string|max:255',
            'tipe' => 'required|string',
            'theme' => 'nullable|string',
            'semester' => 'required|string',
            'file' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,mp4,mov,jpg,jpeg,png,webp|max:30720',
            'kegiatan_file.*' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,mp4,mov,jpg,jpeg,png,webp|max:30720',
            'kegiatan_files.*' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,mp4,mov,jpg,jpeg,png,webp|max:30720',
        ]);

        $file = $request->file('file');
        $kegiatanRaw = $request->input('kegiatan');
        $kegiatan = is_string($kegiatanRaw) ? (json_decode($kegiatanRaw, true) ?: []) : (is_array($kegiatanRaw) ? $kegiatanRaw : []);
        $keyedFiles = $request->file('kegiatan_files') ?? [];
        $indexedFiles = array_values($request->file('kegiatan_file') ?? []);

        $materi = Materi::query()->findOrNew($validated['id'] ?? null);

        if ($file) {
            FileSecurityService::validateUploadedFile($file, ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'mp4', 'mov', 'jpg', 'jpeg', 'png', 'webp']);
            if ($materi->file_path) {
                Storage::disk('public')->delete($materi->file_path);
            }
            $materi->file_path = $file->store('materi', 'public');
            $materi->file_url = null;
        }

        $materi->tp_id = $tpId;
        $materi->kelas_id = $kelasId;
        $materi->mapel_id = $mapelId;
        $materi->cbt_jadwal_id = $cbtJadwalId;
        $materi->theme = $theme ?: 'dark';
        $materi->judul = $validated['judul'];
        $materi->tipe = $validated['tipe'];
        if (! empty($fileUrl)) {
            $materi->file_url = $fileUrl;
        }
        $materi->deskripsi = $deskripsi;
        $semRaw = strtolower(trim((string)$validated['semester']));
        $materi->semester = (str_contains($semRaw, 'genap') || $semRaw === '2') ? 'Genap' : 'Ganjil';
        $materi->is_published = $isPublished;
        $materi->save();

        $oldKegiatanFiles = $materi->kegiatans()->pluck('file_path')->filter()->values()->all();

        $materi->kegiatans()->delete();
        $urutan = 1;
        $keptPaths = [];
        foreach (array_values($kegiatan) as $index => $step) {
            $label = $step['label'] ?? null;
            $judul = $step['judul'] ?? null;
            $konten = $step['konten'] ?? null;
            $fileKey = $step['_fileKey'] ?? null;

            // Look up uploaded file: priority explicit slide index, then _fileKey from keyed array, then indexed fallback
            $newFile = $keyedFiles[$index]
                ?? ($fileKey !== null && isset($keyedFiles[$fileKey]) ? $keyedFiles[$fileKey] : null)
                ?? ($fileKey !== null && isset($indexedFiles[$fileKey]) ? $indexedFiles[$fileKey] : null)
                ?? ($indexedFiles[$index] ?? null);

            $filePath = $step['filePath'] ?? $step['file_path'] ?? null;
            $fileUrl = $step['fileUrl'] ?? $step['file_url'] ?? null;

            if (empty($label) && empty($judul) && empty($konten) && ! $newFile && empty($filePath) && empty($fileUrl)) {
                continue;
            }

            if ($newFile) {
                FileSecurityService::validateUploadedFile($newFile, ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'mp4', 'mov', 'jpg', 'jpeg', 'png', 'webp']);
                $filePath = $newFile->store('materi', 'public');
            }
            if ($filePath) {
                $keptPaths[] = $filePath;
            }

            $materi->kegiatans()->create([
                'urutan' => $urutan++,
                'label' => $label,
                'layout' => $step['layout'] ?? 'standard',
                'judul' => $judul,
                'konten' => $konten,
                'file_path' => $filePath,
                'file_url' => $newFile ? null : $fileUrl,
            ]);
        }

        foreach ($oldKegiatanFiles as $oldPath) {
            if (! in_array($oldPath, $keptPaths, true)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        return redirect()->route('guru.dashboard', ['tab' => 'materi'])->with('message', 'Materi pembelajaran berhasil disimpan!');
    }

    /**
     * Delete Materi.
     */
    public function deleteMateri(int $id): RedirectResponse
    {
        $materi = Materi::query()->find($id);
        if ($materi) {
            if ($materi->file_path) {
                Storage::disk('public')->delete($materi->file_path);
            }
            foreach ($materi->kegiatans()->pluck('file_path')->filter() as $path) {
                Storage::disk('public')->delete($path);
            }
        }
        Materi::destroy($id);

        return redirect()->back()->with('message', 'Materi pembelajaran berhasil dihapus!');
    }

    /**
     * Toggle active publish status (ON/OFF) of a Materi.
     */
    public function toggleMateriStatus(int $id, Request $request)
    {
        $materi = Materi::query()->findOrFail($id);

        if ($request->has('is_published')) {
            $materi->is_published = $request->boolean('is_published');
        } else {
            $materi->is_published = ! (bool) $materi->is_published;
        }
        $materi->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'isPublished' => (bool) $materi->is_published,
                'message' => 'Status materi berhasil diubah menjadi ' . ($materi->is_published ? 'ON (Rilis)' : 'OFF (Draft)') . '!'
            ]);
        }

        return redirect()->back()->with('message', 'Status materi berhasil diubah!');
    }

    /**
     * Store or update Tujuan Pembelajaran (TP).
     */
    public function storeTp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'kode' => 'required|string',
            'mapel' => 'nullable|string|max:100',
            'judul' => 'required|string',
            'jenis' => 'required|string',
            'kktp' => 'required|integer',
            'semester' => 'required|string',
            'tahunAjaran' => 'nullable|string',
        ]);

        $taKode = $request->input('tahunAjaran') ?? $request->input('tahun_ajaran');
        $activeTa = null;
        if ($taKode) {
            $activeTa = TahunAjaran::query()->where('kode', '=', $taKode)->first();
        }
        if (! $activeTa) {
            $activeTa = TahunAjaran::query()->where('is_aktif', '=', true)->first();
        }

        $tpId = !empty($validated['id']) ? intval($validated['id']) : null;
        $semRaw = strtolower(trim((string)$validated['semester']));
        $normalizedSemester = (str_contains($semRaw, 'genap') || $semRaw === '2') ? 'Genap' : 'Ganjil';

        $dataToSave = [
            'kode' => $validated['kode'],
            'mapel' => $validated['mapel'] ?? null,
            'judul' => $validated['judul'],
            'jenis' => $validated['jenis'],
            'kktp' => $validated['kktp'],
            'semester' => $normalizedSemester,
            'tahun_ajaran_id' => $activeTa?->id,
        ];

        if ($tpId) {
            $tp = TujuanPembelajaran::query()->find($tpId);
            if ($tp) {
                $tp->update($dataToSave);
            } else {
                TujuanPembelajaran::query()->create($dataToSave);
            }
        } else {
            TujuanPembelajaran::query()->create($dataToSave);
        }

        return redirect()->back()->with('message', 'Tujuan Pembelajaran berhasil disimpan!');
    }

    /**
     * Store or update Class Schedule for Guru.
     */
    public function storeJadwal(Request $request): RedirectResponse
    {
        $user = $request->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', '=', $user?->id, 'and')->first();
        $guruId = $guru?->id;

        if (! $guruId) {
            throw ValidationException::withMessages(['guru' => 'Akun Anda belum terhubung ke data Guru! Silakan hubungi administrator.']);
        }

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'hari' => 'required|string',
            'jam_ke' => 'required',
            'waktu' => 'nullable|string',
            'kelas' => 'required|string',
            'mata_pelajaran' => 'required|string',
            'ruangan' => 'nullable|string',
            'keterangan' => 'nullable|string',
        ]);

        $kelasObj = Kelas::query()->where('nama_kelas', '=', $validated['kelas'])->first();
        if (! $kelasObj) {
            throw ValidationException::withMessages(['kelas' => 'Kelas "'.$validated['kelas'].'" tidak ditemukan di database!']);
        }

        // Validasi bentrok: pastikan kelas pada hari & jam tersebut belum diisi oleh guru lain
        $classConflict = Jadwal::query()
            ->with('guru')
            ->where('kelas_id', $kelasObj->id)
            ->where('hari', $validated['hari'])
            ->where('jam_ke', $validated['jam_ke'])
            ->where('guru_id', '!=', $guruId)
            ->first();

        if ($classConflict) {
            $conflictGuruName = $classConflict->guru?->nama ?? 'Guru lain';
            throw ValidationException::withMessages([
                'kelas' => "Kelas {$kelasObj->nama_kelas} pada hari {$validated['hari']} Jam ke-{$validated['jam_ke']} sudah terisi oleh {$conflictGuruName} ({$classConflict->mata_pelajaran})!"
            ]);
        }

        // Tentukan ID target jika user belum mengoper ID (mencegah dobel slot di jam yang sama untuk guru ini)
        $targetId = $validated['id'] ?? null;
        if (! $targetId) {
            $existingSlot = Jadwal::query()
                ->where('guru_id', $guruId)
                ->where('hari', $validated['hari'])
                ->where('jam_ke', $validated['jam_ke'])
                ->first();
            if ($existingSlot) {
                $targetId = $existingSlot->id;
            }
        }

        $jadwalData = [
            'guru_id' => $guruId,
            'kelas_id' => $kelasObj->id,
            'hari' => $validated['hari'],
            'jam_ke' => (string)$validated['jam_ke'],
            'waktu' => $validated['waktu'] ?? null,
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'ruangan' => $validated['ruangan'] ?: 'Ruang Kelas',
            'keterangan' => $validated['keterangan'] ?? null,
        ];

        if ($targetId) {
            $jadwalRecord = Jadwal::find($targetId);
            if ($jadwalRecord) {
                $jadwalRecord->update($jadwalData);
            } else {
                Jadwal::create($jadwalData);
            }
        } else {
            Jadwal::create($jadwalData);
        }

        // Otomatis hubungkan kelas ke tabel pivot guru_kelas jika belum terhubung
        if (! DB::table('guru_kelas')->where('guru_id', $guruId)->where('kelas_id', $kelasObj->id)->exists()) {
            DB::table('guru_kelas')->insert([
                'guru_id' => $guruId,
                'kelas_id' => $kelasObj->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('guru.dashboard', ['tab' => 'jadwal'])->with('message', 'Jadwal pelajaran berhasil disimpan!');
    }

    /**
     * Delete Class Schedule.
     */
    public function deleteJadwal(int $id): RedirectResponse
    {
        $user = request()->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', '=', $user?->id, 'and')->first();
        $guruId = $guru?->id;

        $jadwal = Jadwal::query()->find($id);
        if (! $jadwal) {
            throw ValidationException::withMessages(['jadwal' => 'Jadwal tidak ditemukan!']);
        }

        if ($guruId && $jadwal->guru_id !== $guruId) {
            throw ValidationException::withMessages(['jadwal' => 'Anda tidak memiliki akses untuk menghapus jadwal ini!']);
        }

        Jadwal::query()->where('id', '=', $id)->delete();

        return redirect()->route('guru.dashboard', ['tab' => 'jadwal'])->with('message', 'Jadwal pelajaran berhasil dihapus!');
    }

    /**
     * Delete Tujuan Pembelajaran (TP).
     */
    public function deleteTp(int $id): RedirectResponse
    {
        TujuanPembelajaran::query()->where('id', '=', $id)->delete();

        return redirect()->back()->with('message', 'Tujuan Pembelajaran berhasil dihapus!');
    }

    /**
     * Sync kelas ampuan guru ke tabel pivot guru_kelas.
     * Menerima array kelasIds yang dipilih oleh guru.
     */
    public function saveKelasAmpuan(Request $request): RedirectResponse
    {
        $user = $request->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', $user?->id)->first();

        if (! $guru) {
            return redirect()->back()->withErrors(['guru' => 'Data guru tidak ditemukan.']);
        }

        $validated = $request->validate([
            'kelas_ids' => 'required|array|min:1',
            'kelas_ids.*' => 'required|integer|exists:kelases,id',
        ]);

        // sync() replaces existing records — perfect for multi-select
        $guru->kelasAmpuan()->sync($validated['kelas_ids']);

        $count = count($validated['kelas_ids']);

        return redirect()->back()->with('message', "Kelas ampuan berhasil disimpan! ({$count} kelas aktif)");
    }

    /**
     * Hapus satu kelas ampuan guru dari tabel pivot.
     */
    public function removeKelasAmpuan(Request $request, int $kelasId): RedirectResponse
    {
        $user = $request->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', $user?->id)->first();

        if (! $guru) {
            return redirect()->back()->withErrors(['guru' => 'Data guru tidak ditemukan.']);
        }

        $guru->kelasAmpuan()->detach($kelasId);

        return redirect()->back()->with('message', 'Kelas ampuan berhasil dihapus dari daftar mengajar Anda.');
    }

    /**
     * Get monitoring read status for all students in the material's class.
     */
    public function getMateriMonitoring(Request $request, int $id): JsonResponse
    {
        $materi = Materi::with(['kelas', 'mapel', 'cbtJadwal', 'reads.siswa'])->findOrFail($id);

        $query = Siswa::query()->where('status', 'Aktif');
        if ($materi->kelas_id) {
            $query->where('kelas_id', $materi->kelas_id);
        }
        $siswas = $query->orderBy('nama', 'asc')->get();

        $readMap = $materi->reads->keyBy('siswa_id');

        $siswaStatusList = $siswas->map(function ($s) use ($readMap) {
            $read = $readMap->get($s->id);
            return [
                'id' => $s->id,
                'nama' => $s->nama,
                'nis' => $s->nis ?? ('100' . $s->id),
                'gender' => $s->jenis_kelamin ?? 'L',
                'status' => $read ? $read->status : 'belum_baca',
                'progressPersen' => $read ? ($read->progress_persen ?: ($read->status === 'selesai' ? 100 : 25)) : 0,
                'terakhirDibaca' => $read?->terakhir_dibaca_at?->format('d M Y H:i') ?? ($read?->completed_at?->format('d M Y H:i') ?? ($read?->opened_at?->format('d M Y H:i') ?? '-')),
                'openedAt' => $read?->opened_at?->format('d M Y, H:i') ?? '-',
                'completedAt' => $read?->completed_at?->format('d M Y, H:i') ?? '-',
            ];
        });

        $totalSiswa = $siswas->count();
        $totalRead = $readMap->count();
        $selesaiCount = $readMap->where('status', 'selesai')->count();
        $membacaCount = $readMap->where('status', 'sedang_membaca')->count();
        $belumBukaCount = max(0, $totalSiswa - $totalRead);
        $persentase = $totalSiswa > 0 ? round(($selesaiCount / $totalSiswa) * 100) : 0;

        return response()->json([
            'materi' => [
                'id' => $materi->id,
                'judul' => $materi->judul,
                'tipe' => $materi->tipe,
                'theme' => $materi->theme ?? 'dark',
                'targetKelas' => $materi->kelas?->nama_kelas ?? ($materi->mapel?->nama ? "Semua Kelas ({$materi->mapel->nama})" : 'Semua Kelas'),
                'kelas' => $materi->kelas?->nama_kelas ?? ($materi->mapel?->nama ? "Semua Kelas ({$materi->mapel->nama})" : 'Semua Kelas'),
                'mapel' => $materi->mapel?->nama ?? '-',
                'cbtJadwal' => $materi->cbtJadwal ? ['id' => $materi->cbtJadwal->id, 'judul' => $materi->cbtJadwal->judul_ujian] : null,
            ],
            'summary' => [
                'totalSiswa' => $totalSiswa,
                'selesaiCount' => $selesaiCount,
                'membacaCount' => $membacaCount,
                'belumBukaCount' => $belumBukaCount,
                'persentaseSelesai' => $persentase,
            ],
            'totalSiswa' => $totalSiswa,
            'totalRead' => $totalRead,
            'totalCompleted' => $selesaiCount,
            'persentase' => $persentase,
            'students' => $siswaStatusList,
            'siswaList' => $siswaStatusList,
        ]);
    }
}
