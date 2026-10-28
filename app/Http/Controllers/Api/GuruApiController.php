<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtNilai;
use App\Models\CbtSesi;
use App\Models\CbtSoal;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Services\Security\FileSecurityService;
use App\Services\Security\TeacherAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuruApiController extends Controller
{
    private function resolveGuru(Request $request): ?Guru
    {
        return TeacherAccessService::resolveGuru($request->user());
    }

    private function ensureKelasAccess(Request $request, ?int $kelasId): void
    {
        abort_unless(
            TeacherAccessService::canAccessKelas($request->user(), $this->resolveGuru($request), $kelasId),
            403,
            'Akses ditolak: Anda tidak mengajar kelas ini.'
        );
    }

    private function ensureBankSoalAccess(Request $request, CbtBankSoal $bankSoal): void
    {
        abort_unless(
            TeacherAccessService::canAccessBankSoal($request->user(), $this->resolveGuru($request), $bankSoal),
            403,
            'Akses ditolak: bank soal ini bukan milik Anda.'
        );
    }

    private function ensureJadwalAccess(Request $request, CbtJadwal $jadwal): void
    {
        abort_unless(
            TeacherAccessService::canAccessJadwal($request->user(), $this->resolveGuru($request), $jadwal),
            403,
            'Akses ditolak: jadwal CBT ini bukan milik Anda.'
        );
    }

    private function ensureMateriAccess(Request $request, Materi $materi): void
    {
        abort_unless(
            TeacherAccessService::canAccessMateri($request->user(), $this->resolveGuru($request), $materi),
            403,
            'Akses ditolak: materi ini bukan milik Anda.'
        );
    }

    private function ensureTpAccess(Request $request, TujuanPembelajaran $tp): void
    {
        abort_unless(
            TeacherAccessService::canAccessTp($request->user(), $this->resolveGuru($request), $tp),
            403,
            'Akses ditolak: TP ini bukan milik mapel yang Anda ajar.'
        );
    }

    private function ensureSesiAccess(Request $request, CbtSesi $sesi): void
    {
        abort_unless(
            TeacherAccessService::canAccessSesi($request->user(), $this->resolveGuru($request), $sesi),
            403,
            'Akses ditolak: sesi ujian ini bukan milik Anda.'
        );
    }
    public function getJadwal(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = Jadwal::with('kelas');

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->where('guru_id', $guru?->id);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function getAbsensi(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = Absensi::with(['kelas', 'siswa'])->orderBy('tanggal', 'desc');

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->whereIn('kelas_id', TeacherAccessService::kelasIds($guru));
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function storeAbsensiBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'kelas_id' => 'required|integer|exists:kelases,id',
            'rows' => 'required|array',
            'rows.*.siswaId' => 'required|integer|exists:siswas,id',
            'rows.*.status' => 'nullable|string|in:Hadir,Sakit,Izin,Alpa',
        ]);

        $this->ensureKelasAccess($request, (int) $validated['kelas_id']);

        $rowSiswaIds = collect($validated['rows'])->pluck('siswaId')->filter()->unique()->values();
        $validSiswaIds = Siswa::whereIn('id', $rowSiswaIds)
            ->where('kelas_id', $validated['kelas_id'])
            ->pluck('id');

        if ($rowSiswaIds->diff($validSiswaIds)->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Terdapat siswa yang bukan anggota kelas ini.'], 422);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['rows'] as $row) {
                if (empty($row['status'])) {
                    continue;
                }
                Absensi::updateOrCreate(
                    ['tanggal' => $validated['tanggal'], 'siswa_id' => $row['siswaId']],
                    ['kelas_id' => $validated['kelas_id'], 'status' => $row['status'], 'keterangan' => $row['keterangan'] ?? $row['status']]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Presensi batch berhasil disimpan']);
    }

    public function getJurnal(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = Jurnal::with('kelas')->orderBy('tanggal', 'desc');

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->where('guru_id', $guru?->id);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function storeJurnal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:jurnals,id',
            'tanggal' => 'required|date',
            'jam_ke' => 'required|string',
            'kelas_id' => 'required|integer|exists:kelases,id',
            'mata_pelajaran' => 'required|string',
            'materi' => 'required|string',
            'uraian' => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        $this->ensureKelasAccess($request, (int) $validated['kelas_id']);

        $guru = $this->resolveGuru($request);
        if (! $guru) {
            return response()->json(['success' => false, 'message' => 'Akun belum terhubung ke data guru.'], 403);
        }

        if (! empty($validated['id'])) {
            $existingJurnal = Jurnal::findOrFail($validated['id']);
            abort_unless(
                TeacherAccessService::isAdmin($request->user()) || $existingJurnal->guru_id === $guru->id,
                403,
                'Akses ditolak: jurnal ini bukan milik Anda.'
            );
        }

        $jurnal = Jurnal::updateOrCreate(['id' => $validated['id'] ?? null], array_merge($validated, ['guru_id' => $guru->id]));
        return response()->json(['success' => true, 'message' => 'Jurnal berhasil disimpan', 'data' => $jurnal]);
    }

    public function deleteJurnal(int $id): JsonResponse
    {
        $jurnal = Jurnal::findOrFail($id);
        $guru = $this->resolveGuru(request());

        abort_unless(
            TeacherAccessService::isAdmin(request()->user()) || ($guru && $jurnal->guru_id === $guru->id),
            403,
            'Akses ditolak: jurnal ini bukan milik Anda.'
        );

        $jurnal->delete();
        return response()->json(['success' => true, 'message' => 'Jurnal berhasil dihapus']);
    }

    public function getTp(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = TujuanPembelajaran::query();

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->whereIn('mapel', TeacherAccessService::mapelNames($guru));
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function storeTp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:tujuan_pembelajarans,id',
            'kode' => 'required|string',
            'mapel' => 'nullable|string|max:100',
            'judul' => 'required|string',
            'jenis' => 'required|string',
            'kktp' => 'required|integer',
            'semester' => 'required|string',
            'tahunAjaran' => 'nullable|string',
        ]);

        $guru = $this->resolveGuru($request);
        $mapel = trim((string) ($validated['mapel'] ?? ''));

        abort_unless(
            TeacherAccessService::isAdmin($request->user())
                || ($mapel !== '' && in_array($mapel, TeacherAccessService::mapelNames($guru), true)),
            403,
            'Akses ditolak: mapel ini bukan mapel yang Anda ajar.'
        );

        $tpId = !empty($validated['id']) ? intval($validated['id']) : null;
        $dataToSave = [
            'kode' => $validated['kode'],
            'mapel' => $validated['mapel'] ?? null,
            'judul' => $validated['judul'],
            'jenis' => $validated['jenis'],
            'kktp' => $validated['kktp'],
            'semester' => $validated['semester'],
        ];

        if ($tpId) {
            $tp = TujuanPembelajaran::findOrFail($tpId);
            $this->ensureTpAccess($request, $tp);
            $tp->update($dataToSave);
        } else {
            $tp = TujuanPembelajaran::create($dataToSave);
        }

        return response()->json(['success' => true, 'message' => 'Tujuan Pembelajaran berhasil disimpan', 'data' => $tp]);
    }

    public function deleteTp(int $id): JsonResponse
    {
        $tp = TujuanPembelajaran::findOrFail($id);
        $this->ensureTpAccess(request(), $tp);
        $tp->delete();
        return response()->json(['success' => true, 'message' => 'Tujuan Pembelajaran berhasil dihapus']);
    }

    public function getNilai(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = NilaiTp::with(['tp', 'siswa']);

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->whereHas('tp', fn ($q) => $q->whereIn('mapel', TeacherAccessService::mapelNames($guru)));
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function storeNilaiBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tp_id' => 'required|integer|exists:tujuan_pembelajarans,id',
            'scores' => 'required|array',
            'scores.*.siswaId' => 'required|integer|exists:siswas,id',
            'scores.*.nilai' => 'nullable|numeric|min:0|max:100',
        ]);

        $tp = TujuanPembelajaran::findOrFail($validated['tp_id']);
        $this->ensureTpAccess($request, $tp);

        $guru = $this->resolveGuru($request);
        $siswaIds = collect($validated['scores'])->pluck('siswaId')->filter()->unique()->values();
        $validSiswaIds = Siswa::whereIn('id', $siswaIds)
            ->whereIn('kelas_id', TeacherAccessService::kelasIds($guru))
            ->pluck('id');

        if ($siswaIds->diff($validSiswaIds)->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Terdapat siswa yang bukan anggota kelas yang Anda ajar.'], 422);
        }

        DB::transaction(function () use ($validated, $guru) {
            foreach ($validated['scores'] as $s) {
                if (isset($s['nilai']) && $s['nilai'] !== null) {
                    NilaiTp::updateOrCreate(
                        ['tp_id' => $validated['tp_id'], 'siswa_id' => $s['siswaId']],
                        ['nilai' => floatval($s['nilai']), 'guru_id' => $guru?->id]
                    );
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Nilai batch berhasil disimpan']);
    }

    public function getMateri(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = Materi::with('tp')->orderBy('id', 'desc');

        if (! TeacherAccessService::isAdmin($request->user())) {
            abort_unless($guru, 403, 'Akun belum terhubung ke data guru.');

            $kelasIds = TeacherAccessService::kelasIds($guru);
            $mapelIds = TeacherAccessService::mapelIds($guru);
            $guruId = $guru->id;

            $query->where(function ($q) use ($guruId, $kelasIds, $mapelIds) {
                $q->where('guru_id', $guruId);

                if (! empty($kelasIds)) {
                    $q->orWhere(fn ($sub) => $sub->whereNull('guru_id')->whereIn('kelas_id', $kelasIds));
                }

                if (! empty($mapelIds)) {
                    $q->orWhere(fn ($sub) => $sub->whereNull('guru_id')->whereIn('mapel_id', $mapelIds));
                }
            });
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function toggleMateriStatus(int $id, Request $request): JsonResponse
    {
        $materi = Materi::findOrFail($id);
        $this->ensureMateriAccess($request, $materi);
        if ($request->has('is_published')) {
            $materi->is_published = $request->boolean('is_published');
        } else {
            $materi->is_published = ! (bool) $materi->is_published;
        }
        $materi->save();

        return response()->json([
            'success' => true,
            'isPublished' => (bool) $materi->is_published,
            'message' => 'Status materi berhasil diubah menjadi ' . ($materi->is_published ? 'ON (Rilis)' : 'OFF (Draft)')
        ]);
    }

    // --- CBT REST API Endpoints ---

    public function getCbtBankSoal(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = CbtBankSoal::with(['jurusan', 'kelas', 'mataPelajaran', 'soals'])->orderBy('id', 'desc');

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->where(function ($q) use ($guru) {
                $q->where('guru_id', $guru?->id)->orWhereNull('guru_id');
            });
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function storeCbtBankSoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'kode' => 'nullable|string|max:50',
            'jurusan_id' => 'nullable|integer',
            'kelas_id' => 'nullable|integer|exists:kelases,id',
            'mata_pelajaran_id' => 'nullable|integer',
            'deskripsi' => 'nullable|string',
        ]);

        $validated['guru_id'] = $this->resolveGuru($request)?->id;
        $item = CbtBankSoal::create($validated);
        return response()->json(['success' => true, 'message' => 'Bank Soal berhasil dibuat', 'data' => $item]);
    }

    public function getCbtJadwal(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = CbtJadwal::with(['bankSoal', 'kelas', 'nilais'])->orderBy('id', 'desc');

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->where(function ($q) use ($guru) {
                $q->where('guru_id', $guru?->id)->orWhereNull('guru_id');
            });
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function storeCbtJadwal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'kelas_id' => 'nullable|integer|exists:kelases,id',
            'judul_ujian' => 'required|string|max:255',
            'durasi_menit' => 'nullable|integer|min:5|max:300',
            'acak_soal' => 'nullable|boolean',
            'acak_opsi' => 'nullable|boolean',
            'status' => 'nullable|string|in:aktif,nonaktif',
        ]);

        $bankSoal = CbtBankSoal::findOrFail($validated['bank_soal_id']);
        $this->ensureBankSoalAccess($request, $bankSoal);

        if (! empty($validated['kelas_id'])) {
            $this->ensureKelasAccess($request, (int) $validated['kelas_id']);
        }

        $validated['guru_id'] = $this->resolveGuru($request)?->id;
        $item = CbtJadwal::create($validated);
        return response()->json(['success' => true, 'message' => 'Jadwal CBT berhasil dibuat', 'data' => $item]);
    }

    public function toggleCbtJadwal(int $id): JsonResponse
    {
        $jadwal = CbtJadwal::findOrFail($id);
        $this->ensureJadwalAccess(request(), $jadwal);
        $newStatus = $jadwal->status === 'aktif' ? 'nonaktif' : 'aktif';
        $jadwal->update(['status' => $newStatus]);
        return response()->json(['success' => true, 'message' => 'Status berhasil diubah', 'status' => $newStatus]);
    }

    public function deleteCbtBankSoal(int $id): JsonResponse
    {
        $bankSoal = CbtBankSoal::findOrFail($id);
        $this->ensureBankSoalAccess(request(), $bankSoal);
        $bankSoal->delete();
        return response()->json(['success' => true, 'message' => 'Bank Soal CBT berhasil dihapus']);
    }

    public function storeCbtSoal(Request $request): JsonResponse
    {
        if ($request->has('bobot')) {
            $request->merge(['bobot' => str_replace(',', '.', (string)$request->input('bobot'))]);
        }

        $tipeSoal = $request->input('tipe_soal', 'pilihan_ganda');
        if (! in_array($tipeSoal, ['pilihan_ganda', 'match', 'reorder', 'categorize'])) {
            $tipeSoal = 'pilihan_ganda';
        }

        $rules = [
            'id' => 'nullable|integer|exists:cbt_soals,id',
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'nomor_urut' => 'nullable|integer',
            'tipe_soal' => 'nullable|string|in:pilihan_ganda,match,reorder,categorize',
            'stimulus_teks' => 'nullable|string',
            'data_interaktif' => 'nullable',
            'pertanyaan' => 'required|string',
            'bobot' => 'nullable|numeric|min:0.01',
            'posisi_media_soal' => 'nullable|string|in:atas,bawah,samping',
            'media_soal_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp3,wav,ogg,m4a|max:12288',
            'tipe_media_soal' => 'nullable|string|in:image,audio',
            'hapus_media_soal' => 'nullable',
            'gambar_opsi_a_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'gambar_opsi_b_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'gambar_opsi_c_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'gambar_opsi_d_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'gambar_opsi_e_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'hapus_gambar_opsi_a' => 'nullable',
            'hapus_gambar_opsi_b' => 'nullable',
            'hapus_gambar_opsi_c' => 'nullable',
            'hapus_gambar_opsi_d' => 'nullable',
            'hapus_gambar_opsi_e' => 'nullable',
        ];

        if ($tipeSoal === 'pilihan_ganda') {
            $rules['opsi_a'] = 'required|string';
            $rules['opsi_b'] = 'required|string';
            $rules['opsi_c'] = 'required|string';
            $rules['opsi_d'] = 'required|string';
            $rules['opsi_e'] = 'nullable|string';
            $rules['kunci_jawaban'] = 'required|string|in:A,B,C,D,E,a,b,c,d,e';
        } else {
            $rules['opsi_a'] = 'nullable|string';
            $rules['opsi_b'] = 'nullable|string';
            $rules['opsi_c'] = 'nullable|string';
            $rules['opsi_d'] = 'nullable|string';
            $rules['opsi_e'] = 'nullable|string';
            $rules['kunci_jawaban'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        $existing = ! empty($validated['id']) ? CbtSoal::findOrFail($validated['id']) : null;
        $targetBank = CbtBankSoal::findOrFail($validated['bank_soal_id']);
        $this->ensureBankSoalAccess($request, $targetBank);

        if ($existing) {
            abort_unless(
                TeacherAccessService::canAccessSoal($request->user(), $this->resolveGuru($request), $existing),
                403,
                'Akses ditolak: butir soal ini bukan milik Anda.'
            );
        }

        if (! empty($validated['id'])) {
            $nomorUrut = $existing?->nomor_urut ?? ($validated['nomor_urut'] ?? 1);
        } else {
            $lastNomor = \App\Models\CbtSoal::where('bank_soal_id', $validated['bank_soal_id'])->max('nomor_urut') ?? 0;
            $nomorUrut = $lastNomor + 1;
        }

        $bobot = isset($validated['bobot']) && $validated['bobot'] !== '' ? floatval($validated['bobot']) : 1.0;

        $rawInteractive = $request->input('data_interaktif');
        if (is_string($rawInteractive)) {
            $rawInteractive = json_decode($rawInteractive, true);
        }

        $data = [
            'bank_soal_id' => $validated['bank_soal_id'],
            'nomor_urut' => $nomorUrut,
            'tipe_soal' => $tipeSoal,
            'pertanyaan' => $validated['pertanyaan'],
            'stimulus_teks' => $request->input('stimulus_teks'),
            'data_interaktif' => $rawInteractive,
            'opsi_a' => $validated['opsi_a'] ?? '',
            'opsi_b' => $validated['opsi_b'] ?? '',
            'opsi_c' => $validated['opsi_c'] ?? '',
            'opsi_d' => $validated['opsi_d'] ?? '',
            'opsi_e' => $validated['opsi_e'] ?? '',
            'kunci_jawaban' => ! empty($validated['kunci_jawaban']) ? strtoupper($validated['kunci_jawaban']) : 'A',
            'bobot' => $bobot,
            'posisi_media_soal' => $request->input('posisi_media_soal', $existing?->posisi_media_soal ?? 'bawah'),
        ];

        // Handle Question Media
        if ($request->hasFile('media_soal_file')) {
            $file = $request->file('media_soal_file');
            FileSecurityService::validateUploadedFile($file, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp3', 'wav', 'ogg', 'm4a']);
            $ext = strtolower($file->getClientOriginalExtension());
            $isAudio = in_array($ext, ['mp3', 'wav', 'ogg', 'm4a']);
            $path = $file->store('cbt_media/soals', 'public');
            $data['media_soal'] = $path;
            $data['tipe_media_soal'] = $isAudio ? 'audio' : 'image';
        } elseif ($request->boolean('hapus_media_soal')) {
            if ($existing?->media_soal) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($existing->media_soal);
            }
            $data['media_soal'] = null;
            $data['tipe_media_soal'] = null;
        }

        // Handle Option Images
        foreach (['a', 'b', 'c', 'd', 'e'] as $opt) {
            $fileKey = "gambar_opsi_{$opt}_file";
            $delKey = "hapus_gambar_opsi_{$opt}";
            $colKey = "gambar_opsi_{$opt}";

            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                FileSecurityService::validateUploadedFile($file, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                $path = $file->store('cbt_media/opsi', 'public');
                $data[$colKey] = $path;
            } elseif ($request->boolean($delKey)) {
                if ($existing?->$colKey) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($existing->$colKey);
                }
                $data[$colKey] = null;
            }
        }

        if ($existing) {
            $existing->update($data);
            $soal = $existing;
        } else {
            $soal = CbtSoal::create($data);
        }
        return response()->json(['success' => true, 'message' => 'Soal CBT berhasil disimpan', 'data' => $soal]);
    }

    public function deleteCbtSoal(int $id): JsonResponse
    {
        $soal = CbtSoal::with('bankSoal')->findOrFail($id);
        abort_unless(
            TeacherAccessService::canAccessSoal(request()->user(), $this->resolveGuru(request()), $soal),
            403,
            'Akses ditolak: Anda tidak memiliki hak untuk menghapus butir soal ini'
        );

        $bankSoalId = $soal->bank_soal_id;

        if ($soal->media_soal) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($soal->media_soal);
        }
        foreach (['a', 'b', 'c', 'd', 'e'] as $opt) {
            $colKey = "gambar_opsi_{$opt}";
            if ($soal->$colKey) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($soal->$colKey);
            }
        }

        $soal->delete();

        // Re-sequence remaining questions
        $remaining = \App\Models\CbtSoal::where('bank_soal_id', $bankSoalId)
            ->orderBy('nomor_urut', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($remaining as $index => $item) {
            $correctNumber = $index + 1;
            if ($item->nomor_urut !== $correctNumber) {
                $item->update(['nomor_urut' => $correctNumber]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Butir soal CBT berhasil dihapus dan nomor soal telah diurutkan kembali']);
    }

    public function updateAllCbtBobot(Request $request): JsonResponse
    {
        if ($request->has('bobot')) {
            $request->merge(['bobot' => str_replace(',', '.', (string)$request->input('bobot'))]);
        }

        $validated = $request->validate([
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'bobot' => 'required|numeric|min:0.01',
        ]);

        $bankSoal = CbtBankSoal::findOrFail($validated['bank_soal_id']);
        $this->ensureBankSoalAccess($request, $bankSoal);

        $bobot = floatval($validated['bobot']);

        $updatedCount = \App\Models\CbtSoal::where('bank_soal_id', $validated['bank_soal_id'])
            ->update(['bobot' => $bobot]);

        return response()->json([
            'success' => true,
            'message' => "Bobot seluruh butir soal ({$updatedCount} soal) berhasil diubah menjadi {$bobot} poin",
            'updated_count' => $updatedCount,
            'bobot' => $bobot
        ]);
    }

    public function importBulkCbtSoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'soals' => 'required|array|min:1',
            'soals.*.pertanyaan' => 'required|string',
            'soals.*.opsi_a' => 'required|string',
            'soals.*.opsi_b' => 'required|string',
            'soals.*.opsi_c' => 'required|string',
            'soals.*.opsi_d' => 'required|string',
            'soals.*.opsi_e' => 'nullable|string',
            'soals.*.kunci_jawaban' => 'required|string|in:A,B,C,D,E,a,b,c,d,e',
            'soals.*.bobot' => 'nullable',
            'soals.*.posisi_media_soal' => 'nullable|string|in:atas,bawah,samping',
            'soals.*.gambar_base64' => 'nullable|string',
        ]);

        $bankSoal = CbtBankSoal::findOrFail($validated['bank_soal_id']);
        $this->ensureBankSoalAccess($request, $bankSoal);

        $bankSoalId = $validated['bank_soal_id'];
        $soalsInput = $validated['soals'];

        \Illuminate\Support\Facades\DB::transaction(function () use ($bankSoalId, $soalsInput) {
            $lastNomor = \App\Models\CbtSoal::where('bank_soal_id', $bankSoalId)->max('nomor_urut') ?? 0;
            $currentNomor = $lastNomor;

            foreach ($soalsInput as $s) {
                $currentNomor++;
                $bobotRaw = isset($s['bobot']) && $s['bobot'] !== '' ? str_replace(',', '.', (string)$s['bobot']) : '1.0';
                $bobot = floatval($bobotRaw);
                if ($bobot <= 0) {
                    $bobot = 1.0;
                }

                $mediaPath = null;
                $tipeMedia = null;
                $posisiMedia = $s['posisi_media_soal'] ?? 'bawah';

                if (!empty($s['gambar_base64'])) {
                    $rawBase64 = $s['gambar_base64'];
                    if (preg_match('/^data:image\/(\w+);base64,/', $rawBase64, $matchType)) {
                        $rawBase64 = substr($rawBase64, strpos($rawBase64, ',') + 1);
                        $type = strtolower($matchType[1]);
                        if ($type === 'jpeg') $type = 'jpg';
                        if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $decoded = base64_decode($rawBase64);
                            if ($decoded !== false) {
                                FileSecurityService::validateBase64Image($decoded, $type);
                                $filename = 'cbt_media/soals/' . \Illuminate\Support\Str::random(30) . '.' . $type;
                                \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $decoded);
                                $mediaPath = $filename;
                                $tipeMedia = 'image';
                            }
                        }
                    }
                }

                \App\Models\CbtSoal::create([
                    'bank_soal_id' => $bankSoalId,
                    'nomor_urut' => $currentNomor,
                    'pertanyaan' => trim($s['pertanyaan']),
                    'opsi_a' => trim($s['opsi_a']),
                    'opsi_b' => trim($s['opsi_b']),
                    'opsi_c' => trim($s['opsi_c']),
                    'opsi_d' => trim($s['opsi_d']),
                    'opsi_e' => isset($s['opsi_e']) && trim($s['opsi_e']) !== '' ? trim($s['opsi_e']) : '-',
                    'kunci_jawaban' => strtoupper(trim($s['kunci_jawaban'])),
                    'bobot' => $bobot,
                    'media_soal' => $mediaPath,
                    'tipe_media_soal' => $tipeMedia,
                    'posisi_media_soal' => $posisiMedia,
                ]);
            }
        });

        $count = count($soalsInput);
        return response()->json([
            'success' => true,
            'message' => "Berhasil mengimpor {$count} butir soal ke dalam Bank Soal",
            'imported_count' => $count,
        ]);
    }

    public function deleteCbtJadwal(int $id): JsonResponse
    {
        $jadwal = CbtJadwal::findOrFail($id);
        $this->ensureJadwalAccess(request(), $jadwal);
        $jadwal->delete();
        return response()->json(['success' => true, 'message' => 'Jadwal CBT berhasil dihapus']);
    }

    public function getCbtNilai(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $query = CbtNilai::with(['jadwal', 'siswa', 'bankSoal'])->orderBy('id', 'desc');

        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->whereHas('jadwal', function ($q) use ($guru) {
                $q->withTrashed()->where(function ($inner) use ($guru) {
                    $inner->where('guru_id', $guru?->id)->orWhereNull('guru_id');
                });
            });
        }

        return response()->json(['success' => true, 'data' => $query->paginate(50)]);
    }

    public function getMonitoringData(Request $request, int $id): JsonResponse
    {
        return app(\App\Http\Controllers\Guru\GuruCbtController::class)->getMonitoringData($request, $id);
    }

    public function resetSesiSiswa(Request $request, int $id): JsonResponse
    {
        $sesi = CbtSesi::with('jadwal')->findOrFail($id);
        $this->ensureSesiAccess($request, $sesi);
        CbtNilai::where('jadwal_id', $sesi->jadwal_id)->where('siswa_id', $sesi->siswa_id)->delete();
        $sesi->update([
            'status' => 'menunggu',
            'jumlah_pelanggaran' => 0,
            'soal_terjawab' => 0,
            'last_heartbeat' => now(),
        ]);
        return response()->json(['success' => true, 'message' => 'Sesi siswa berhasil direset']);
    }

    public function kunciSesiSiswa(Request $request, int $id): JsonResponse
    {
        $sesi = CbtSesi::with('jadwal')->findOrFail($id);
        $this->ensureSesiAccess($request, $sesi);
        $sesi->update(['status' => 'terkunci', 'last_heartbeat' => now()]);
        return response()->json(['success' => true, 'message' => 'Sesi siswa berhasil dikunci']);
    }

    public function bukaKunciSesiSiswa(Request $request, int $id): JsonResponse
    {
        $sesi = CbtSesi::with('jadwal')->findOrFail($id);
        $this->ensureSesiAccess($request, $sesi);
        $sesi->update(['status' => 'mengerjakan', 'jumlah_pelanggaran' => 0, 'last_heartbeat' => now()]);
        return response()->json(['success' => true, 'message' => 'Kunci ujian siswa berhasil dibuka']);
    }

    public function deleteSesiSiswa(Request $request, int $id): JsonResponse
    {
        $sesi = CbtSesi::with('jadwal')->findOrFail($id);
        $this->ensureSesiAccess($request, $sesi);
        CbtNilai::where('jadwal_id', $sesi->jadwal_id)->where('siswa_id', $sesi->siswa_id)->delete();
        $sesi->delete();
        return response()->json(['success' => true, 'message' => 'Sesi peserta berhasil dihapus']);
    }

    public function transferCbtToNilaiTp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'mode' => 'required|string|in:existing_tp,new_tp',
            'tp_id' => 'nullable|integer|exists:tujuan_pembelajarans,id',
            'kode' => 'nullable|string|max:50',
            'judul' => 'nullable|string|max:255',
            'mapel' => 'nullable|string|max:100',
            'jenis' => 'nullable|string|max:50',
            'kktp' => 'nullable|numeric|min:0|max:100',
            'semester' => 'nullable|string|in:Ganjil,Genap',
            'tahun_ajaran_id' => 'nullable|integer',
            'overwrite' => 'nullable|boolean',
            'siswa_ids' => 'nullable|array',
            'siswa_ids.*' => 'integer|exists:siswas,id',
        ]);

        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;

        $jadwal = CbtJadwal::with(['bankSoal.mataPelajaran'])->findOrFail($validated['jadwal_id']);
        $this->ensureJadwalAccess($request, $jadwal);

        if (! empty($validated['tp_id'])) {
            $targetTp = TujuanPembelajaran::findOrFail($validated['tp_id']);
            $this->ensureTpAccess($request, $targetTp);
        }

        if ($validated['mode'] === 'new_tp') {
            $tp = \App\Models\TujuanPembelajaran::create([
                'kode' => $validated['kode'] ?? ('TP-CBT' . rand(10, 99)),
                'judul' => $validated['judul'] ?? ('Ujian CBT - ' . $jadwal->judul_ujian),
                'mapel' => $validated['mapel'] ?? ($jadwal->bankSoal?->mataPelajaran?->nama ?? 'Umum'),
                'jenis' => $validated['jenis'] ?? 'Sumatif',
                'kktp' => $validated['kktp'] ?? 75,
                'semester' => $validated['semester'] ?? 'Ganjil',
                'tahun_ajaran_id' => $validated['tahun_ajaran_id'] ?? 1,
            ]);
            $targetTpId = $tp->id;
        } else {
            $targetTpId = $validated['tp_id'];
        }

        $nilaisQuery = \App\Models\CbtNilai::where('jadwal_id', $jadwal->id);
        if (!empty($validated['siswa_ids'])) {
            $nilaisQuery->whereIn('siswa_id', $validated['siswa_ids']);
        }
        $cbtNilais = $nilaisQuery->get();

        $overwrite = isset($validated['overwrite']) ? (bool)$validated['overwrite'] : true;
        $transferredCount = 0;

        DB::transaction(function () use ($cbtNilais, $targetTpId, $guruId, $overwrite, &$transferredCount) {
            foreach ($cbtNilais as $cbt) {
                if (!$overwrite) {
                    $exists = \App\Models\NilaiTp::where('tp_id', $targetTpId)->where('siswa_id', $cbt->siswa_id)->exists();
                    if ($exists) continue;
                }

                \App\Models\NilaiTp::updateOrCreate(
                    [
                        'tp_id' => $targetTpId,
                        'siswa_id' => $cbt->siswa_id,
                    ],
                    [
                        'nilai' => floatval($cbt->nilai),
                        'catatan' => "Nilai CBT: {$cbt->jumlah_benar} Benar, {$cbt->jumlah_salah} Salah",
                        'guru_id' => $guruId,
                    ]
                );
                $transferredCount++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil mentransfer {$transferredCount} nilai CBT ke Nilai TP",
            'transferred_count' => $transferredCount,
            'tp_id' => $targetTpId
        ]);
    }

    /**
     * Store or update schedule via API.
     */
    public function storeJadwal(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;

        if (! $guruId) {
            return response()->json(['success' => false, 'message' => 'Akun Anda belum terhubung ke data Guru.'], 403);
        }

        $validated = $request->validate([
            'id' => 'nullable|integer|exists:jadwals,id',
            'hari' => 'required|string',
            'jam_ke' => 'required',
            'waktu' => 'nullable|string',
            'kelas' => 'required|string',
            'mata_pelajaran' => 'required|string',
            'ruangan' => 'nullable|string',
            'keterangan' => 'nullable|string',
        ]);

        $kelasObj = Kelas::where('nama_kelas', $validated['kelas'])->first();
        if (! $kelasObj) {
            return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], 422);
        }

        if (! empty($validated['id'])) {
            $existing = Jadwal::findOrFail($validated['id']);
            abort_unless(
                $existing->guru_id === $guruId || TeacherAccessService::isAdmin($request->user()),
                403,
                'Akses ditolak: jadwal ini bukan milik Anda.'
            );
        }

        // Conflict check
        $conflict = Jadwal::with('guru')
            ->where('kelas_id', $kelasObj->id)
            ->where('hari', $validated['hari'])
            ->where('jam_ke', $validated['jam_ke'])
            ->where('guru_id', '!=', $guruId)
            ->first();

        if ($conflict) {
            $conflictName = $conflict->guru?->nama ?? 'Guru lain';
            return response()->json([
                'success' => false,
                'message' => "Kelas {$kelasObj->nama_kelas} pada hari {$validated['hari']} Jam ke-{$validated['jam_ke']} sudah diisi oleh {$conflictName} ({$conflict->mata_pelajaran})."
            ], 422);
        }

        $targetId = $validated['id'] ?? null;
        if (! $targetId) {
            $existingSlot = Jadwal::where('guru_id', $guruId)
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
            'jam_ke' => (string) $validated['jam_ke'],
            'waktu' => $validated['waktu'] ?? null,
            'mata_pelajaran' => $validated['mata_pelajaran'],
            'ruangan' => $validated['ruangan'] ?: 'Ruang Kelas',
            'keterangan' => $validated['keterangan'] ?? null,
        ];

        if ($targetId) {
            $record = Jadwal::find($targetId);
            $record?->update($jadwalData);
        } else {
            $record = Jadwal::create($jadwalData);
        }

        // Auto link to guru_kelas if not exists
        if (! DB::table('guru_kelas')->where('guru_id', $guruId)->where('kelas_id', $kelasObj->id)->exists()) {
            DB::table('guru_kelas')->insert([
                'guru_id' => $guruId,
                'kelas_id' => $kelasObj->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Jadwal pelajaran berhasil disimpan.', 'data' => $record]);
    }

    /**
     * Delete schedule via API.
     */
    public function deleteJadwal(Request $request, int $id): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $jadwal = Jadwal::findOrFail($id);

        abort_unless(
            TeacherAccessService::isAdmin($request->user()) || ($guru && $jadwal->guru_id === $guru->id),
            403,
            'Anda tidak memiliki akses untuk menghapus jadwal ini.'
        );

        $jadwal->delete();

        return response()->json(['success' => true, 'message' => 'Jadwal pelajaran berhasil dihapus.']);
    }

    /**
     * Get list of classes taught by guru based on Schedule and Kelas Ampuan.
     */
    public function getKelas(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        $kelasIds = TeacherAccessService::kelasIds($guru);

        $query = Kelas::with(['jurusan', 'waliKelas'])->withCount('siswas');
        if (! TeacherAccessService::isAdmin($request->user())) {
            $query->whereIn('id', $kelasIds);
        }

        $kelases = $query->get()->sort(function ($a, $b) {
            $t = strcmp($a->tingkat ?? 'X', $b->tingkat ?? 'X');
            if ($t !== 0) return $t;
            return strnatcasecmp($a->nama_kelas, $b->nama_kelas);
        })->values();

        return response()->json(['success' => true, 'data' => $kelases]);
    }

    /**
     * Save kelas ampuan via API.
     */
    public function saveKelasAmpuan(Request $request): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        if (! $guru) {
            return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan.'], 403);
        }

        $validated = $request->validate([
            'kelas_ids' => 'required|array|min:1',
            'kelas_ids.*' => 'required|integer|exists:kelases,id',
        ]);

        $guru->kelasAmpuan()->sync($validated['kelas_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Kelas ampuan berhasil disimpan.',
            'count' => count($validated['kelas_ids'])
        ]);
    }

    /**
     * Remove single kelas ampuan via API.
     */
    public function removeKelasAmpuan(Request $request, int $kelasId): JsonResponse
    {
        $guru = $this->resolveGuru($request);
        if (! $guru) {
            return response()->json(['success' => false, 'message' => 'Data guru tidak ditemukan.'], 403);
        }

        $guru->kelasAmpuan()->detach($kelasId);

        return response()->json(['success' => true, 'message' => 'Kelas ampuan berhasil dihapus.']);
    }

    /**
     * Clear column of TP grades via API.
     */
    public function clearNilaiTpKolom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tpId' => 'required|integer|exists:tujuan_pembelajarans,id',
            'kelas' => 'required|string',
        ]);

        $kelasObj = Kelas::where('nama_kelas', $validated['kelas'])->first();
        if (! $kelasObj) {
            return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], 422);
        }

        $this->ensureKelasAccess($request, $kelasObj->id);

        $tp = TujuanPembelajaran::findOrFail($validated['tpId']);
        $this->ensureTpAccess($request, $tp);

        $siswaIds = Siswa::where('kelas_id', $kelasObj->id)->pluck('id');
        $deleted = NilaiTp::where('tp_id', $tp->id)->whereIn('siswa_id', $siswaIds)->delete();

        return response()->json([
            'success' => true,
            'message' => "Seluruh nilai kolom TP {$tp->kode} untuk kelas {$kelasObj->nama_kelas} berhasil dikosongkan.",
            'deleted' => $deleted
        ]);
    }

    /**
     * Swap or move TP grades via API.
     */
    public function swapOrMoveNilaiTp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sourceTpId' => 'required|integer|exists:tujuan_pembelajarans,id|different:targetTpId',
            'targetTpId' => 'required|integer|exists:tujuan_pembelajarans,id',
            'kelas' => 'required|string',
            'mode' => 'required|in:swap,move',
        ]);

        $kelasObj = Kelas::where('nama_kelas', $validated['kelas'])->first();
        if (! $kelasObj) {
            return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], 422);
        }

        $this->ensureKelasAccess($request, $kelasObj->id);

        $sourceTp = TujuanPembelajaran::findOrFail($validated['sourceTpId']);
        $targetTp = TujuanPembelajaran::findOrFail($validated['targetTpId']);
        $this->ensureTpAccess($request, $sourceTp);
        $this->ensureTpAccess($request, $targetTp);

        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;

        $siswaIds = Siswa::where('kelas_id', $kelasObj->id)->pluck('id')->toArray();
        $sourceTpId = (int) $validated['sourceTpId'];
        $targetTpId = (int) $validated['targetTpId'];

        DB::transaction(function () use ($sourceTpId, $targetTpId, $siswaIds, $validated, $guruId) {
            $sourceRecords = NilaiTp::where('tp_id', $sourceTpId)->whereIn('siswa_id', $siswaIds)->get()->keyBy('siswa_id');
            $targetRecords = NilaiTp::where('tp_id', $targetTpId)->whereIn('siswa_id', $siswaIds)->get()->keyBy('siswa_id');

            if ($validated['mode'] === 'swap') {
                foreach ($siswaIds as $sId) {
                    $src = $sourceRecords->get($sId);
                    $tgt = $targetRecords->get($sId);

                    $srcNilai = $src?->nilai;
                    $srcCatatan = $src?->catatan;
                    $tgtNilai = $tgt?->nilai;
                    $tgtCatatan = $tgt?->catatan;

                    if ($srcNilai !== null && $srcNilai !== '') {
                        NilaiTp::updateOrCreate(
                            ['tp_id' => $targetTpId, 'siswa_id' => $sId],
                            ['nilai' => $srcNilai, 'catatan' => $srcCatatan, 'guru_id' => $guruId]
                        );
                    } else {
                        NilaiTp::where('tp_id', $targetTpId)->where('siswa_id', $sId)->delete();
                    }

                    if ($tgtNilai !== null && $tgtNilai !== '') {
                        NilaiTp::updateOrCreate(
                            ['tp_id' => $sourceTpId, 'siswa_id' => $sId],
                            ['nilai' => $tgtNilai, 'catatan' => $tgtCatatan, 'guru_id' => $guruId]
                        );
                    } else {
                        NilaiTp::where('tp_id', $sourceTpId)->where('siswa_id', $sId)->delete();
                    }
                }
            } elseif ($validated['mode'] === 'move') {
                foreach ($siswaIds as $sId) {
                    $src = $sourceRecords->get($sId);
                    if ($src && $src->nilai !== null && $src->nilai !== '') {
                        NilaiTp::updateOrCreate(
                            ['tp_id' => $targetTpId, 'siswa_id' => $sId],
                            ['nilai' => $src->nilai, 'catatan' => $src->catatan, 'guru_id' => $guruId]
                        );
                    }
                }
                NilaiTp::where('tp_id', $sourceTpId)->whereIn('siswa_id', $siswaIds)->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => $validated['mode'] === 'swap' ? 'Nilai kedua TP berhasil ditukar.' : 'Nilai TP berhasil dipindahkan.'
        ]);
    }
}
