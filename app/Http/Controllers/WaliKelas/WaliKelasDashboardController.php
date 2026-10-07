<?php

namespace App\Http\Controllers\WaliKelas;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\KasusSiswa;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiTp;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WaliKelasDashboardController extends Controller
{
    /**
     * Render main Wali Kelas Dashboard view with live homeroom data from PostgreSQL.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Find assigned class for homeroom teacher: via guru_id, else fallback to user.kelas_id
        $assignedClassObj = $this->resolveKelasBinaan($request);

        $kelasBinaan = $assignedClassObj?->nama_kelas ?? 'Belum Ditugaskan';
        $kelasId = $assignedClassObj?->id;

        return Inertia::render('WaliKelas/Dashboard', [
            'kelasBinaanProp' => $kelasBinaan,
            'tingkatKelasBinaanProp' => $assignedClassObj?->tingkat ?? 'X',
            'kelasIdProp' => $kelasId,
            'siswaListProp' => $this->getSiswaList($kelasId),
            'absensiListProp' => $this->getAbsensiList($kelasId),
            'rekapTugasListProp' => $this->getRekapTugasList($kelasId),
            'rekapNilaiCatatanListProp' => $this->getRekapNilaiCatatanList($kelasId),
            'rekapNilaiSemesterProp' => $this->getRekapNilaiSemester($kelasId),
            'jadwalListProp' => $this->getJadwalList($kelasId),
            'guruListProp' => $this->getGuruList(),
            'tpListProp' => $this->getTpList(),
            'mapelListProp' => $this->getMapelList(),
            'kasusListProp' => $this->getKasusList($kelasId),
        ]);
    }

    /**
     * Resolve the homeroom class for the logged-in user.
     * Priority: gurus.kelas_binaan via kelases.guru_id, then fallback to users.kelas_id.
     */
    private function resolveKelasBinaan(Request $request)
    {
        $user = $request->user();
        $guru = $user?->guru ?? Guru::query()->where('user_id', '=', $user?->id)->first();

        if ($guru?->id) {
            $kelas = Kelas::query()->where('guru_id', '=', $guru->id)->first();
            if ($kelas) {
                return $kelas;
            }
        }

        if ($user?->kelas_id) {
            return Kelas::query()->where('id', '=', $user->kelas_id)->first();
        }

        return null;
    }

    private function getSiswaList(?int $kelasId)
    {
        return Siswa::query()
            ->where('kelas_id', '=', $kelasId)
            ->with(['kelas:id,nama_kelas,tingkat,guru_id', 'kelas.waliKelas:id,nama'])
            ->orderBy('nama', 'asc')
            ->get()
            ->map(function ($s) {
            return [
                'id' => $s->id,
                'nis' => $s->nis ?: ('100'.$s->id),
                'nama' => $s->nama,
                'jenisKelamin' => $s->jenis_kelamin,
                'kelas' => $s->kelas?->nama_kelas ?? 'X RPL 1',
                'tingkat' => $s->kelas?->tingkat ?? 'X',
                'waliKelas' => $s->kelas?->waliKelas?->nama ?? '-',
                'teleponSiswa' => $s->telepon ?? '081234567890',
                'namaOrangTua' => 'Bapak/Ibu '.explode(' ', $s->nama)[0],
                'teleponOrtu' => '081299887766',
                'linkMaps' => 'https://maps.google.com/?q=-6.200000,106.816666',
                'status' => $s->status ?? 'Aktif',
                'tanggal_status' => $s->tanggal_status,
            ];
        });
    }

    private function getRekapTugasList(?int $kelasId)
    {
        if (! $kelasId) {
            return [];
        }

        $siswas = Siswa::query()->where('kelas_id', '=', $kelasId)->get();
        $tugasList = Tugas::query()->with('guru')->where('kelas_id', '=', $kelasId)->get();

        $tugasIds = $tugasList->pluck('id')->toArray();
        $pengumpulans = ! empty($tugasIds)
            ? PengumpulanTugas::query()->whereIn('tugas_id', $tugasIds, 'and', false)->get()->groupBy('tugas_id')
            : collect();

        $rekap = [];
        $uniqueId = 1;

        foreach ($tugasList as $tugas) {
            $tugasPengumpulans = $pengumpulans->get($tugas->id) ?? collect();
            $groupedBySiswa = $tugasPengumpulans->groupBy('siswa_id');

            foreach ($siswas as $siswa) {
                $sub = $groupedBySiswa->get($siswa->id)?->first();

                $status = 'Belum Tuntas';
                if ($sub) {
                    if (! is_null($sub->nilai)) {
                        $status = ($sub->nilai >= 75) ? 'Tuntas' : 'Remedial';
                    } else {
                        $status = ($sub->status === 'Sudah Dikirim') ? 'Tuntas' : 'Belum Tuntas';
                    }
                }

                $rekap[] = [
                    'id' => $uniqueId++,
                    'source' => 'Tugas',
                    'nis' => '100'.$siswa->id,
                    'namaSiswa' => $siswa->nama,
                    'mapel' => $tugas->mata_pelajaran ?? 'Informatika',
                    'guruPengajar' => $tugas->guru?->name ?? 'Budi Santoso, S.Kom.',
                    'judulTugas' => $tugas->judul,
                    'nilai' => $sub?->nilai,
                    'status' => $status,
                    'tanggalKumpul' => $sub?->dikirim_pada?->format('d M Y') ?? '-',
                ];
            }
        }

        return $rekap;
    }

    private function getRekapNilaiCatatanList(?int $kelasId)
    {
        if (! $kelasId) {
            return [];
        }

        $gurus = Guru::query()->get(['id', 'nama', 'mapel_utama']);

        $resolveGuru = function (?int $guruId, string $mapel) use ($gurus) {
            if ($guruId && ($guru = $gurus->firstWhere('id', $guruId))) {
                return $guru->nama;
            }
            $target = strtolower(trim($mapel));
            foreach ($gurus as $guru) {
                $mapels = array_map('trim', explode(',', (string) $guru->mapel_utama));
                if (in_array($target, array_map('strtolower', $mapels), true)) {
                    return $guru->nama;
                }
            }
            return '-';
        };

        $records = NilaiTp::query()
            ->with(['tp', 'siswa', 'guru'])
            ->whereHas('siswa', function ($q) use ($kelasId) {
                $q->where('kelas_id', '=', $kelasId);
            })
            ->orderBy('siswa_id', 'asc')
            ->orderBy('tp_id', 'asc')
            ->get()
            ->groupBy(fn ($n) => $n->siswa_id.'|'.($n->guru_id ?? 'm:'.$n->tp?->mapel));

        $result = [];

        foreach ($records as $items) {
            $first = $items->first();
            $mapel = $first->tp?->mapel ?? 'Umum';

            $scored = $items->filter(fn ($n) => floatval($n->nilai) > 0);
            $avg = $scored->count() > 0
                ? round($scored->avg(fn ($n) => floatval($n->nilai)), 1)
                : null;

            $kktp = $scored->count() > 0 ? $scored->min(fn ($n) => $n->tp?->kktp ?? 75) : 75;

            $kodeTps = $items->pluck('tp.kode')->filter()->unique()->sort()->values();
            $semesters = $items->pluck('tp.semester')->filter()->unique()->values();
            $catatans = $items->pluck('catatan')->filter()->map('trim')->unique();

            $result[] = [
                'id' => 'nilai-avg-'.$first->siswa_id,
                'source' => 'Nilai TP',
                'nis' => '100'.$first->siswa_id,
                'namaSiswa' => $first->siswa?->nama ?? '-',
                'mapel' => $mapel,
                'guruPengajar' => $resolveGuru($first->guru_id, $mapel),
                'kodeTp' => $kodeTps->implode(', '),
                'semester' => $semesters->implode(', '),
                'judulTugas' => 'Nilai Akhir (Rata-rata '.$scored->count().' TP)',
                'nilai' => $avg,
                'status' => $avg === null ? 'Belum Dinilai' : ($avg >= $kktp ? 'Tuntas' : 'Belum Tuntas'),
                'catatan' => $catatans->count() > 0 ? $catatans->implode('; ') : '',
                'tanggalKumpul' => $items->first()->created_at?->format('d M Y') ?? '-',
            ];
        }

        return $result;
    }

    private function getRekapNilaiSemester(?int $kelasId)
    {
        $siswasInClass = Siswa::query()->where('kelas_id', '=', $kelasId)->get();
        $siswaIds = $siswasInClass->pluck('id')->toArray();

        // Load all nilai with TP relation to get mapel info
        $allNilaiTps = ! empty($siswaIds)
            ? NilaiTp::query()
                ->with('tp')
                ->whereIn('siswa_id', $siswaIds)
                ->get()
                ->groupBy('siswa_id')
            : collect();

        return $siswasInClass->map(function ($s) use ($allNilaiTps) {
            // Build scores map: { 'TP-01' => nilai, 'TP-02' => nilai, ... }
            $scores = [];
            if (isset($allNilaiTps[$s->id])) {
                foreach ($allNilaiTps[$s->id] as $nilaiRecord) {
                    $kode = $nilaiRecord->tp?->kode;
                    if ($kode) {
                        $scores[$kode] = floatval($nilaiRecord->nilai);
                    }
                }
            }

            // Calculate overall average from all recorded scores
            $allScores = array_values($scores);
            $avg = count($allScores) > 0
                ? round(array_sum($allScores) / count($allScores), 1)
                : 0;

            return [
                'id' => $s->id,
                'nis' => '100'.$s->id,
                'namaSiswa' => $s->nama,
                'scores' => $scores,
                'nilaiAkhir' => $avg,
                'predikat' => $avg >= 90 ? 'A (Sangat Baik)' : ($avg >= 80 ? 'B (Baik)' : ($avg >= 75 ? 'C (Cukup)' : 'D (Perlu Bimbingan)')),
                'status' => $avg >= 75 ? 'Tuntas' : 'Belum Tuntas',
            ];
        });
    }

    /**
     * Get list of all TPs with mapel and semester info for dynamic column headers.
     */
    private function getTpList(): array
    {
        return TujuanPembelajaran::query()
            ->orderBy('mapel', 'asc')
            ->orderBy('kode', 'asc')
            ->get()
            ->map(function ($tp) {
                return [
                    'id' => $tp->id,
                    'kode' => $tp->kode,
                    'mapel' => $tp->mapel ?? 'Umum',
                    'judul' => $tp->judul,
                    'jenis' => $tp->jenis,
                    'kktp' => $tp->kktp,
                    'semester' => $tp->semester,
                ];
            })
            ->toArray();
    }

    /**
     * Get unique mapel list from tujuan_pembelajarans.
     */
    private function getMapelList(): array
    {
        return MataPelajaran::query()
            ->orderBy('nama', 'asc')
            ->pluck('nama')
            ->toArray();
    }

    private function getJadwalList(?int $kelasId)
    {
        return Jadwal::query()->with('guru')->where('kelas_id', '=', $kelasId)->orderBy('jam_ke', 'asc')->get()->map(function ($j) {
            return [
                'id' => $j->id,
                'hari' => $j->hari,
                'jamKe' => $j->jam_ke,
                'waktu' => $j->waktu,
                'mapel' => $j->mata_pelajaran,
                'guruId' => $j->guru_id,
                'guru' => $j->guru?->name ?? 'Budi Santoso, S.Kom.',
                'ruangan' => $j->ruangan ?? 'Lab Komputer 1',
            ];
        });
    }

    private function getGuruList()
    {
        return User::role('guru')->with('guru')->get()->map(function ($g) {
            return [
                'id' => $g->id,
                'nama' => $g->name,
                'email' => $g->email,
                'mapelUtama' => $g->guru?->mapel_utama ?? 'Mata Pelajaran',
            ];
        });
    }

    /**
     * Store or update Homeroom Student in database.
     */
    public function storeSiswa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'nama' => 'required|string|max:255',
            'jenisKelamin' => 'required|string',
            'kelas' => 'required|string',
            'teleponSiswa' => 'nullable|string',
            'status' => 'nullable|string|in:Aktif,Pindah,Keluar',
            'tanggal_status' => 'nullable|date',
        ]);

        $kelasObj = Kelas::query()->where('nama_kelas', '=', $validated['kelas'])->first();

        $status = $validated['status'] ?? 'Aktif';
        $tanggal_status = $status !== 'Aktif' ? ($validated['tanggal_status'] ?? null) : null;

        if (! empty($validated['id'])) {
            Siswa::query()->where('id', '=', $validated['id'])->update([
                'nama' => $validated['nama'],
                'jenis_kelamin' => $validated['jenisKelamin'],
                'kelas_id' => $kelasObj?->id,
                'telepon' => $validated['teleponSiswa'] ?? null,
                'status' => $status,
                'tanggal_status' => $tanggal_status,
            ]);
        } else {
            Siswa::updateOrCreate(
                ['nama' => $validated['nama']],
                [
                    'jenis_kelamin' => $validated['jenisKelamin'],
                    'kelas_id' => $kelasObj?->id,
                    'telepon' => $validated['teleponSiswa'] ?? null,
                    'status' => $status,
                    'tanggal_status' => $tanggal_status,
                ]
            );
        }

        return redirect()->back()->with('message', 'Data Siswa kelas binaan berhasil disimpan ke database!');
    }

    /**
     * Delete Student from Homeroom Class in database.
     */
    public function deleteSiswa(int $id): RedirectResponse
    {
        Siswa::query()->where('id', '=', $id)->delete();

        return redirect()->back()->with('message', 'Siswa berhasil dihapus / mutasi keluar dari kelas binaan!');
    }

    /**
     * Store or update Class Schedule for Homeroom Class.
     */
    public function storeJadwal(Request $request): RedirectResponse
    {
        $assignedClassObj = $this->resolveKelasBinaan($request);

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'hari' => 'required|string',
            'jam_ke' => 'required',
            'waktu' => 'nullable|string',
            'mata_pelajaran' => 'required|string',
            'guru_id' => 'nullable|integer',
            'ruangan' => 'nullable|string',
        ]);

        if (! $assignedClassObj) {
            throw ValidationException::withMessages(['kelas' => 'Anda belum memiliki kelas binaan! Jadwal tidak dapat disimpan.']);
        }

        Jadwal::query()->updateOrCreate(
            ['id' => $validated['id'] ?? null],
            [
                'kelas_id' => $assignedClassObj->id,
                'hari' => $validated['hari'],
                'jam_ke' => $validated['jam_ke'],
                'waktu' => $validated['waktu'],
                'mata_pelajaran' => $validated['mata_pelajaran'],
                'guru_id' => $validated['guru_id'] ?? null,
                'ruangan' => $validated['ruangan'] ?? 'Ruang Kelas',
            ]
        );

        return redirect()->back()->with('message', 'Jadwal pelajaran kelas binaan berhasil disimpan!');
    }

    private function getAbsensiList(?int $kelasId)
    {
        $query = Absensi::query()
            ->select(['id', 'tanggal', 'kelas_id', 'siswa_id', 'status', 'keterangan'])
            ->with(['siswa:id,nama', 'kelas:id,nama_kelas'])
            ->orderBy('tanggal', 'desc');

        if ($kelasId) {
            $query->where('kelas_id', '=', $kelasId);
        }

        // Ambil data absensi tahun berjalan (12 bulan) agar rekapan bulanan wali kelas lengkap
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
                'nis' => '100'.$a->siswa_id,
                'status' => $a->status,
                'keterangan' => $a->keterangan ?? $a->status,
                'jamKe' => 'Jam 1 - 2 (07.00 - 08.30)',
            ];
        });
    }

    /**
     * Store bulk class attendance for Wali Kelas.
     */
    public function storeAbsensiBatch(Request $request): RedirectResponse
    {
        $assignedClassObj = $this->resolveKelasBinaan($request);
        $kelasId = $assignedClassObj?->id;

        if (! $kelasId) {
            throw ValidationException::withMessages(['kelas' => 'Anda belum memiliki kelas binaan! Presensi tidak dapat disimpan.']);
        }

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'rows' => 'required|array',
        ]);

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

        return redirect()->back()->with('message', 'Presensi kelas binaan berhasil disimpan oleh Wali Kelas!');
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
        $assignedClassObj = $this->resolveKelasBinaan($request);
        $kelasId = $assignedClassObj?->id;

        $validated = $request->validate([
            'tanggal'  => 'required|date_format:Y-m-d',
            'kelas_id' => 'nullable|integer',
        ]);

        $resolvedKelasId = $validated['kelas_id'] ?? $kelasId;

        $query = Absensi::query()->where('tanggal', '=', $validated['tanggal']);

        if ($resolvedKelasId) {
            $query->where('kelas_id', '=', $resolvedKelasId);
        }

        $deleted = $query->delete();

        return redirect()->back()->with('message', "Absensi tanggal {$validated['tanggal']} berhasil dibatalkan ({$deleted} record dihapus).");
    }

    /**
     * Delete individual attendance record by ID.
     */
    public function deleteAbsensi(int $id): RedirectResponse
    {
        Absensi::query()->where('id', '=', $id)->delete();

        return redirect()->back()->with('message', 'Catatan absensi berhasil dihapus!');
    }

    /**
     * Delete Class Schedule.
     */
    public function deleteJadwal(int $id): RedirectResponse
    {
        Jadwal::query()->where('id', '=', $id)->delete();

        return redirect()->back()->with('message', 'Jadwal pelajaran berhasil dihapus!');
    }

    private function getKasusList(?int $kelasId)
    {
        if (! $kelasId) {
            return [];
        }

        return KasusSiswa::query()
            ->with('siswa')
            ->whereHas('siswa', function ($q) use ($kelasId) {
                $q->where('kelas_id', '=', $kelasId);
            })
            ->orderBy('tanggal', 'desc')
            ->get()
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'siswaId' => $k->siswa_id,
                    'namaSiswa' => $k->siswa?->nama ?? '-',
                    'tanggal' => $k->tanggal,
                    'namaKasus' => $k->nama_kasus,
                    'kategori' => $k->kategori,
                    'poin' => $k->poin,
                    'tindakan' => $k->tindakan,
                    'status' => $k->status,
                    'catatan' => $k->catatan,
                ];
            })
            ->toArray();
    }

    /**
     * Store or update Homeroom Student behavioral case in database.
     */
    public function storeKasus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'siswaId' => 'required|integer',
            'tanggal' => 'required|date',
            'namaKasus' => 'required|string|max:255',
            'kategori' => 'required|string|in:Ringan,Sedang,Berat',
            'poin' => 'required|integer|min:0',
            'tindakan' => 'nullable|string',
            'status' => 'required|string|in:Dalam Pemantauan,Selesai',
            'catatan' => 'nullable|string',
        ]);

        KasusSiswa::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            [
                'siswa_id' => $validated['siswaId'],
                'tanggal' => $validated['tanggal'],
                'nama_kasus' => $validated['namaKasus'],
                'kategori' => $validated['kategori'],
                'poin' => $validated['poin'],
                'tindakan' => $validated['tindakan'] ?? null,
                'status' => $validated['status'],
                'catatan' => $validated['catatan'] ?? null,
            ]
        );

        return redirect()->back()->with('message', 'Data kasus siswa berhasil disimpan!');
    }

    /**
     * Delete Student case in database.
     */
    public function deleteKasus(int $id): RedirectResponse
    {
        KasusSiswa::query()->where('id', '=', $id)->delete();

        return redirect()->back()->with('message', 'Data kasus siswa berhasil dihapus!');
    }
}
