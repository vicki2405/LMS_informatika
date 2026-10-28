<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\MateriKegiatan;
use App\Models\MateriRead;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Services\Security\FileSecurityService;
use App\Services\Security\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GuruMateriController extends Controller
{
    private function resolveGuru(Request $request): ?Guru
    {
        return TeacherAccessService::resolveGuru($request->user());
    }

    private function ensureMateriAccess(Request $request, Materi $materi): void
    {
        abort_unless(
            TeacherAccessService::canAccessMateri($request->user(), $this->resolveGuru($request), $materi),
            403,
            'Akses ditolak: materi ini bukan milik Anda.'
        );
    }

    private function getTeacherKelasIds(?int $guruId): array
    {
        return TeacherAccessService::kelasIds($guruId ? Guru::find($guruId) : null);
    }

    private function getTeacherMapelList(?Guru $guru): array
    {
        if (! $guru) {
            return [];
        }

        $scheduleMapelNames = Jadwal::query()
            ->where('guru_id', $guru->id)
            ->whereNotNull('mata_pelajaran')
            ->pluck('mata_pelajaran')
            ->map(fn ($m) => trim((string)$m))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $guruMapelNames = !empty($guru->mapel_utama)
            ? array_map('trim', explode(',', $guru->mapel_utama))
            : [];

        $allNames = collect(array_merge($scheduleMapelNames, $guruMapelNames))
            ->filter()
            ->unique()
            ->values();

        if ($allNames->isEmpty()) {
            return [];
        }

        return DB::table('mata_pelajarans')
            ->whereIn('nama', $allNames->toArray())
            ->get()
            ->map(fn ($mp) => [
                'id' => $mp->id,
                'kode' => $mp->kode ?? 'MP',
                'nama' => $mp->nama,
            ])
            ->toArray();
    }

    public static function getMateriData(?int $guruId = null, array $kelasIds = [], array $mapelIds = []): array
    {
        $query = Materi::query()
            ->with(['tp', 'kelas', 'mapel', 'kegiatans', 'cbtJadwal'])
            ->withCount([
                'reads',
                'reads as reads_completed_count' => function ($q) {
                    $q->where('status', 'selesai');
                },
            ])
            ->orderBy('id', 'desc');

        if ($guruId) {
            $query->where(function ($q) use ($guruId, $mapelIds) {
                $q->where('guru_id', $guruId);
                if (! empty($mapelIds)) {
                    $q->orWhere(function ($sub) use ($mapelIds) {
                        $sub->whereNull('guru_id')->whereIn('mapel_id', $mapelIds);
                    });
                }
            });
        } elseif (! empty($mapelIds)) {
            $query->whereIn('mapel_id', $mapelIds);
        }

        if (! empty($kelasIds)) {
            $query->where(function ($q) use ($kelasIds) {
                $q->whereIn('kelas_id', $kelasIds)->orWhereNull('kelas_id');
            });
        }

        return $query->limit(500)->get()->map(fn (Materi $m) => self::serializeMateri($m))->toArray();
    }

    public static function serializeMateri(Materi $m): array
    {
        $mapelName = $m->mapel?->nama ?? $m->tp?->mapel ?? '';
        $targetKelas = $m->kelas?->nama_kelas ?? 'Semua Kelas';

        $readsCount = $m->reads_count ?? (method_exists($m, 'reads') ? $m->reads()->count() : 0);
        $readsCompletedCount = $m->reads_completed_count ?? (method_exists($m, 'reads') ? $m->reads()->where('status', 'selesai')->count() : 0);

        return [
            'id' => $m->id,
            'tpId' => $m->tp_id,
            'tpKode' => $m->tp?->kode ?? 'TP-01',
            'kelasId' => $m->kelas_id,
            'targetKelas' => $targetKelas,
            'mapelId' => $m->mapel_id,
            'mapel' => $mapelName,
            'mapelNama' => $mapelName,
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
            'readsCount' => $readsCount,
            'readsCompletedCount' => $readsCompletedCount,
        ];
    }

    public function createMateri(Request $request): Response
    {
        return $this->materiFormData($request, null);
    }

    public function editMateri(int $id, Request $request): Response
    {
        $materi = Materi::query()
            ->with(['tp', 'mapel', 'kelas', 'kegiatans', 'cbtJadwal'])
            ->withCount([
                'reads',
                'reads as reads_completed_count' => function ($q) {
                    $q->where('status', 'selesai');
                },
            ])
            ->findOrFail($id);

        $this->ensureMateriAccess($request, $materi);

        return $this->materiFormData($request, $materi);
    }

    private function materiFormData(Request $request, ?Materi $materi): Response
    {
        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;
        $teacherMapels = $this->getTeacherMapelList($guru);

        $teacherKelas = [];
        if ($guruId) {
            $kelasIds = $this->getTeacherKelasIds($guruId);
            $teacherKelas = Kelas::query()
                ->whereIn('id', $kelasIds)
                ->orderBy('nama_kelas', 'asc')
                ->get()
                ->toArray();
        }

        $masterKelas = Kelas::query()->orderBy('nama_kelas', 'asc')->get()->toArray();
        $teacherCbtJadwals = GuruCbtController::getJadwalsList($guruId);

        $teacherMapelNames = collect($teacherMapels)->pluck('nama')->merge(
            !empty($guru?->mapel_utama) ? array_map('trim', explode(',', $guru->mapel_utama)) : []
        )->unique()->filter()->implode(',');

        $tpQuery = TujuanPembelajaran::query()->with('tahunAjaran')->orderBy('kode', 'asc');
        if (!empty($teacherMapelNames)) {
            $mapelArr = array_map('trim', explode(',', $teacherMapelNames));
            $tpQuery->whereIn('mapel', $mapelArr);
        }
        $tpList = $tpQuery->get()->map(fn ($tp) => [
            'id' => $tp->id,
            'kode' => $tp->kode,
            'mapel' => $tp->mapel,
            'judul' => $tp->judul,
            'jenis' => $tp->jenis,
            'kktp' => $tp->kktp,
            'semester' => $tp->semester,
            'tahunAjaran' => $tp->tahunAjaran?->tahun ?? '2026/2027',
        ])->toArray();

        $jadwalList = [];
        if ($guruId) {
            $jadwalList = Jadwal::query()
                ->with(['kelas', 'guru'])
                ->where('guru_id', '=', $guruId)
                ->orderBy('hari', 'asc')
                ->orderBy('jam_ke', 'asc')
                ->get()
                ->map(fn ($j) => [
                    'id' => $j->id,
                    'hari' => $j->hari,
                    'jamKe' => $j->jam_ke,
                    'waktu' => $j->waktu,
                    'mapel' => $j->mata_pelajaran,
                    'kelas' => $j->kelas?->nama_kelas ?? 'X RPL 1',
                    'kelasId' => $j->kelas_id,
                ])->toArray();
        }

        return Inertia::render('Guru/MateriForm', [
            'materiProp' => $materi ? self::serializeMateri($materi) : null,
            'mapelListProp' => $teacherMapels,
            'kelasListProp' => $teacherKelas,
            'masterKelasListProp' => $masterKelas,
            'jadwalListProp' => $jadwalList,
            'tpListProp' => $tpList,
            'cbtJadwalListProp' => collect($teacherCbtJadwals)->map(fn ($j) => [
                'id' => $j['id'],
                'judul' => $j['judulUjian'] ?? ($j['bankSoalJudul'] ?? 'Ujian CBT'),
                'kelasId' => $j['kelasId'] ?? null,
                'durasi' => $j['durasiMenit'] ?? 60,
            ])->values()->toArray(),
        ]);
    }

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
            'id' => 'nullable|integer|exists:materis,id',
            'judul' => 'required|string|max:255',
            'tipe' => 'required|string',
            'theme' => 'nullable|string',
            'semester' => 'required|string',
            'file_url' => ['nullable', 'string', 'max:2048', 'regex:/^https?:\/\//i'],
            'fileUrl' => ['nullable', 'string', 'max:2048', 'regex:/^https?:\/\//i'],
            'file' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,mp4,mov,jpg,jpeg,png,webp|max:30720',
            'kegiatan_file.*' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,mp4,mov,jpg,jpeg,png,webp|max:30720',
            'kegiatan_files.*' => 'nullable|file|mimes:pdf,ppt,pptx,doc,docx,mp4,mov,jpg,jpeg,png,webp|max:30720',
        ]);

        $guru = $this->resolveGuru($request);

        if (! empty($validated['id'])) {
            $existingMateri = Materi::findOrFail($validated['id']);
            $this->ensureMateriAccess($request, $existingMateri);
        }

        $file = $request->file('file');
        $kegiatanRaw = $request->input('kegiatan');
        $kegiatan = is_string($kegiatanRaw) ? (json_decode($kegiatanRaw, true) ?: []) : (is_array($kegiatanRaw) ? $kegiatanRaw : []);
        $keyedFiles = $request->file('kegiatan_files') ?? [];
        $indexedFiles = array_values($request->file('kegiatan_file') ?? []);

        return DB::transaction(function () use (
            $validated, $file, $kegiatan, $keyedFiles, $indexedFiles,
            $tpId, $kelasId, $mapelId, $cbtJadwalId, $theme, $fileUrl, $deskripsi, $isPublished, $guru
        ) {
            $materi = Materi::query()->findOrNew($validated['id'] ?? null);
            $materi->guru_id = $materi->guru_id ?? $guru?->id;

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

                $newFile = $keyedFiles[$index]
                    ?? ($fileKey !== null && isset($keyedFiles[$fileKey]) ? $keyedFiles[$fileKey] : null)
                    ?? ($fileKey !== null && isset($indexedFiles[$fileKey]) ? $indexedFiles[$fileKey] : null)
                    ?? ($indexedFiles[$index] ?? null);

                $filePath = $step['filePath'] ?? $step['file_path'] ?? null;
                $fileUrl = $step['fileUrl'] ?? $step['file_url'] ?? null;

                if (! empty($fileUrl) && ! preg_match('/^https?:\/\//i', (string) $fileUrl)) {
                    throw ValidationException::withMessages([
                        'kegiatan' => 'URL kegiatan harus diawali http:// atau https://.',
                    ]);
                }

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
        });
    }

    public function deleteMateri(int $id): RedirectResponse
    {
        $materi = Materi::query()->findOrFail($id);
        $this->ensureMateriAccess(request(), $materi);

        if ($materi->file_path) {
            Storage::disk('public')->delete($materi->file_path);
        }
        foreach ($materi->kegiatans()->pluck('file_path')->filter() as $path) {
            Storage::disk('public')->delete($path);
        }

        $materi->delete();

        return redirect()->back()->with('message', 'Materi pembelajaran berhasil dihapus!');
    }

    public function toggleMateriStatus(int $id, Request $request)
    {
        $materi = Materi::query()->findOrFail($id);
        $this->ensureMateriAccess($request, $materi);

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

    public function bulkAction(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:publish,unpublish,delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:materis,id',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];
        $materis = Materi::whereIn('id', $ids)->get();

        abort_if(
            $materis->contains(fn (Materi $materi) => ! TeacherAccessService::canAccessMateri($request->user(), $this->resolveGuru($request), $materi)),
            403,
            'Akses ditolak: terdapat materi yang bukan milik Anda.'
        );

        if ($action === 'publish') {
            Materi::whereIn('id', $ids)->update(['is_published' => true]);
            $msg = count($ids) . ' materi berhasil diaktifkan (ON / Rilis)!';
        } elseif ($action === 'unpublish') {
            Materi::whereIn('id', $ids)->update(['is_published' => false]);
            $msg = count($ids) . ' materi berhasil dinonaktifkan (OFF / Draft)!';
        } else        if ($action === 'delete') {
            $materis->load('kegiatans');
            foreach ($materis as $m) {
                if ($m->file_path) {
                    Storage::disk('public')->delete($m->file_path);
                }
                foreach ($m->kegiatans as $k) {
                    if ($k->file_path) {
                        Storage::disk('public')->delete($k->file_path);
                    }
                }
                $m->delete();
            }
            $msg = count($ids) . ' materi berhasil dihapus!';
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('message', $msg);
    }

    public function getMateriMonitoring(Request $request, int $id): Response|JsonResponse
    {
        $materi = Materi::with(['kelas', 'mapel', 'tp', 'cbtJadwal', 'reads.siswa.kelas'])->findOrFail($id);
        $this->ensureMateriAccess($request, $materi);

        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;
        $mapelNama = $materi->mapel?->nama ?? ($materi->tp?->mapel ?? null);

        // Kunci kelas sasaran materi:
        // Jika materi untuk kelas spesifik -> hanya kelas itu.
        // Jika materi untuk Semua Kelas -> hanya kelas-kelas yang diajar guru untuk mapel materi ini.
        if ($materi->kelas_id) {
            $matchingKelasIds = [$materi->kelas_id];
        } else {
            $jadwalGuruQuery = Jadwal::query()
                ->where('guru_id', $guruId)
                ->whereNotNull('kelas_id');

            if (!empty($mapelNama)) {
                $jadwalGuruQuery->where(function ($q) use ($mapelNama) {
                    $q->where('mata_pelajaran', $mapelNama)
                        ->orWhereRaw('LOWER(TRIM(mata_pelajaran)) = ?', [strtolower(trim((string)$mapelNama))]);
                });
            }

            $matchingKelasIds = $jadwalGuruQuery->pluck('kelas_id')->unique()->values()->toArray();

            // Fallback jika belum ada jadwal spesifik untuk mapel ini, gunakan kelas ampuan guru umum
            if (empty($matchingKelasIds)) {
                $matchingKelasIds = $this->getTeacherKelasIds($guruId);
            }
        }

        $query = Siswa::with('kelas')->where('status', 'Aktif');
        if (!empty($matchingKelasIds)) {
            $query->whereIn('kelas_id', $matchingKelasIds);
        } elseif ($materi->kelas_id) {
            $query->where('kelas_id', $materi->kelas_id);
        }
        $siswas = $query->orderBy('nama', 'asc')->get();

        $matchingSiswaIds = $siswas->pluck('id')->toArray();
        $readMap = $materi->reads->keyBy('siswa_id');
        $validReads = $materi->reads->whereIn('siswa_id', $matchingSiswaIds);

        $masterKelasList = Kelas::whereIn('id', $matchingKelasIds)
            ->get(['id', 'nama_kelas', 'tingkat'])
            ->sort(function ($a, $b) {
                $t = strcmp($a->tingkat ?? 'X', $b->tingkat ?? 'X');
                if ($t !== 0) return $t;
                return strnatcasecmp($a->nama_kelas, $b->nama_kelas);
            })
            ->values();

        $siswaStatusList = $siswas->map(function ($s) use ($readMap) {
            $read = $readMap->get($s->id);
            return [
                'id' => $s->id,
                'nama' => $s->nama,
                'nis' => $s->nis ?? ('100' . $s->id),
                'gender' => $s->jenis_kelamin ?? 'L',
                'kelasId' => $s->kelas_id,
                'kelasNama' => $s->kelas?->nama_kelas ?? '-',
                'status' => $read ? $read->status : 'belum_baca',
                'progressPersen' => $read ? ($read->progress_persen ?: ($read->status === 'selesai' ? 100 : 25)) : 0,
                'terakhirDibaca' => $read?->terakhir_dibaca_at?->format('d M Y H:i') ?? ($read?->completed_at?->format('d M Y H:i') ?? ($read?->opened_at?->format('d M Y H:i') ?? '-')),
                'openedAt' => $read?->opened_at?->format('d M Y, H:i') ?? '-',
                'completedAt' => $read?->completed_at?->format('d M Y, H:i') ?? '-',
            ];
        });

        $totalSiswa = $siswas->count();
        $totalRead = $validReads->count();
        $selesaiCount = $validReads->where('status', 'selesai')->count();
        $membacaCount = $validReads->where('status', 'sedang_membaca')->count();
        $belumBukaCount = max(0, $totalSiswa - $totalRead);
        $persentase = $totalSiswa > 0 ? round(($selesaiCount / $totalSiswa) * 100) : 0;

        $payload = [
            'materi' => [
                'id' => $materi->id,
                'judul' => $materi->judul,
                'deskripsi' => $materi->deskripsi,
                'tipe' => $materi->tipe,
                'theme' => $materi->theme ?? 'dark',
                'targetKelas' => $materi->kelas?->nama_kelas ?? ($materi->mapel?->nama ? "Semua Kelas ({$materi->mapel->nama})" : 'Semua Kelas'),
                'kelas' => $materi->kelas?->nama_kelas ?? ($materi->mapel?->nama ? "Semua Kelas ({$materi->mapel->nama})" : 'Semua Kelas'),
                'mapel' => $materi->mapel?->nama ?? ($materi->tp?->mapel ?? '-'),
                'cbtJadwal' => $materi->cbtJadwal?->judul_ujian,
            ],
            'masterKelasList' => $masterKelasList,
            'kelasList' => $masterKelasList,
            'summary' => [
                'totalSiswa' => $totalSiswa,
                'totalRead' => $totalRead,
                'selesaiCount' => $selesaiCount,
                'membacaCount' => $membacaCount,
                'belumBukaCount' => $belumBukaCount,
                'persentase' => $persentase,
                'persentaseSelesai' => $persentase,
            ],
            'stats' => [
                'totalSiswa' => $totalSiswa,
                'totalRead' => $totalRead,
                'selesaiCount' => $selesaiCount,
                'membacaCount' => $membacaCount,
                'belumBukaCount' => $belumBukaCount,
                'persentaseSelesai' => $persentase,
            ],
            'totalSiswa' => $totalSiswa,
            'totalRead' => $totalRead,
            'selesaiCount' => $selesaiCount,
            'membacaCount' => $membacaCount,
            'belumBukaCount' => $belumBukaCount,
            'persentase' => $persentase,
            'students' => $siswaStatusList,
            'siswaList' => $siswaStatusList,
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Guru/MateriMonitoringPage', $payload);
    }
}