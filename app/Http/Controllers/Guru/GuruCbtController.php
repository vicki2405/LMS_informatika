<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtNilai;
use App\Models\CbtSesi;
use App\Models\CbtSoal;
use App\Models\Guru;
use App\Models\NilaiTp;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Services\Security\FileSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\Jurusan;
use App\Models\Kelas;
use Inertia\Inertia;
use Inertia\Response;
class GuruCbtController extends Controller
{
    /**
     * Resolve Guru model from authenticated user.
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
     * Get list of Bank Soal with questions for Inertia props.
     */
    public static function getBankSoalsList(?int $guruId = null): array
    {
        $query = CbtBankSoal::query()
            ->with([
                'jurusan:id,kode,nama',
                'kelas:id,nama_kelas,tingkat',
                'mataPelajaran:id,kode,nama',
                'soals' => fn ($q) => $q->orderBy('nomor_urut', 'asc'),
            ])
            ->orderBy('id', 'desc');

        if ($guruId) {
            $query->where(function ($q) use ($guruId) {
                $q->where('guru_id', $guruId)->orWhereNull('guru_id');
            });
        }

        return $query->get()->map(function ($b) {
            return [
                'id' => $b->id,
                'judul' => $b->judul,
                'kode' => $b->kode ?? ('BS-'.strtoupper(Str::random(5))),
                'jurusanId' => $b->jurusan_id,
                'jurusanNama' => $b->jurusan?->nama ?? 'Semua Jurusan',
                'jurusanKode' => $b->jurusan?->kode ?? 'ALL',
                'kelasId' => $b->kelas_id,
                'kelasNama' => $b->kelas?->nama_kelas ?? 'Semua Kelas',
                'tingkat' => $b->kelas?->tingkat ?? 'X',
                'mapelId' => $b->mata_pelajaran_id,
                'mapelNama' => $b->mataPelajaran?->nama ?? 'Umum',
                'deskripsi' => $b->deskripsi ?? '',
                'jumlahPilihan' => $b->jumlah_pilihan ?? 5,
                'jumlahSoal' => $b->soals->count(),
                'soals' => $b->soals->map(fn ($s) => [
                    'id' => $s->id,
                    'bankSoalId' => $s->bank_soal_id,
                    'nomorUrut' => $s->nomor_urut,
                    'pertanyaan' => $s->pertanyaan,
                    'opsiA' => $s->opsi_a,
                    'opsiB' => $s->opsi_b,
                    'opsiC' => $s->opsi_c,
                    'opsiD' => $s->opsi_d,
                    'opsiE' => $s->opsi_e,
                    'kunciJawaban' => strtoupper($s->kunci_jawaban),
                    'bobot' => floatval($s->bobot ?? 1),
                    'tipeSoal' => $s->tipe_soal ?? 'pilihan_ganda',
                    'stimulusTeks' => $s->stimulus_teks,
                    'dataInteraktif' => $s->data_interaktif,
                    'mediaSoal' => $s->media_soal ? asset('storage/' . $s->media_soal) : null,
                    'tipeMediaSoal' => $s->tipe_media_soal,
                    'posisiMediaSoal' => $s->posisi_media_soal ?? 'bawah',
                    'gambarOpsiA' => $s->gambar_opsi_a ? asset('storage/' . $s->gambar_opsi_a) : null,
                    'gambarOpsiB' => $s->gambar_opsi_b ? asset('storage/' . $s->gambar_opsi_b) : null,
                    'gambarOpsiC' => $s->gambar_opsi_c ? asset('storage/' . $s->gambar_opsi_c) : null,
                    'gambarOpsiD' => $s->gambar_opsi_d ? asset('storage/' . $s->gambar_opsi_d) : null,
                    'gambarOpsiE' => $s->gambar_opsi_e ? asset('storage/' . $s->gambar_opsi_e) : null,
                ])->toArray(),
            ];
        })->toArray();
    }

    /**
     * Get list of CBT Schedules for Inertia props.
     */
    public static function getJadwalsList(?int $guruId = null, bool $withTrashed = false): array
    {
        $query = CbtJadwal::query()
            ->when($withTrashed, fn ($q) => $q->withTrashed())
            ->with([
                'bankSoal.mataPelajaran:id,nama',
                'jurusan:id,nama,kode',
                'kelas.jurusan:id,nama,kode',
                'nilais:id,jadwal_id,nilai,status',
            ])
            ->orderBy('id', 'desc');

        if ($guruId) {
            $query->where(function ($q) use ($guruId) {
                $q->where('guru_id', $guruId)->orWhereNull('guru_id');
            });
        }

        return $query->get()->map(function ($j) {
            $scores = $j->nilais->pluck('nilai')->filter()->toArray();
            $avgScore = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;

            $jurusanId = $j->jurusan_id ?? $j->kelas?->jurusan_id;
            $jurusanNama = $j->jurusan?->nama ?? $j->kelas?->jurusan?->nama ?? 'Semua Jurusan';
            $jurusanKode = $j->jurusan?->kode ?? $j->kelas?->jurusan?->kode ?? 'ALL';

            return [
                'id' => $j->id,
                'bankSoalId' => $j->bank_soal_id,
                'bankSoalJudul' => $j->bankSoal?->judul ?? 'Bank Soal CBT',
                'bankSoalKode' => $j->bankSoal?->kode ?? '-',
                'mapelNama' => $j->bankSoal?->mataPelajaran?->nama ?? 'Umum',
                'jurusanId' => $jurusanId,
                'jurusanNama' => $jurusanNama,
                'jurusanKode' => $jurusanKode,
                'kelasId' => $j->kelas_id,
                'kelasNama' => $j->kelas?->nama_kelas ?? 'Semua Kelas',
                'tingkat' => $j->kelas?->tingkat ?? 'X',
                'judulUjian' => $j->judul_ujian,
                'waktuMulai' => $j->waktu_mulai ? $j->waktu_mulai->format('Y-m-d H:i') : null,
                'waktuSelesai' => $j->waktu_selesai ? $j->waktu_selesai->format('Y-m-d H:i') : null,
                'durasiMenit' => $j->durasi_menit ?? 60,
                'token' => $j->token ?? strtoupper(Str::random(6)),
                'acakSoal' => (bool) ($j->acak_soal ?? true),
                'acakOpsi' => (bool) ($j->acak_opsi ?? true),
                'status' => $j->status ?? 'aktif',
                'modeUjian' => $j->mode_ujian ?? 'formal',
                'jumlahPeserta' => $j->nilais->count(),
                'rataRataNilai' => $avgScore,
                'isDeleted' => method_exists($j, 'trashed') ? $j->trashed() : false,
            ];
        })->toArray();
    }

    /**
     * Get list of CBT Results for Inertia props.
     */
    public static function getNilaisList(?int $guruId = null): array
    {
        $query = CbtNilai::query()
            ->with([
                'jadwal' => fn ($q) => $q->withTrashed()->select('id', 'judul_ujian', 'kelas_id', 'bank_soal_id', 'guru_id'),
                'jadwal.bankSoal.mataPelajaran:id,nama',
                'jadwal.bankSoal.jurusan:id,nama,kode',
                'jadwal.kelas:id,nama_kelas,tingkat',
                'siswa:id,nama,kelas_id',
                'siswa.kelas.jurusan:id,nama,kode',
            ])
            ->orderBy('id', 'desc');

        if ($guruId) {
            $query->whereHas('jadwal', function ($q) use ($guruId) {
                $q->withTrashed()->where(function ($inner) use ($guruId) {
                    $inner->where('guru_id', $guruId)->orWhereNull('guru_id');
                });
            });
        }

        $nilais = $query->get();
        $jadwalIds = $nilais->pluck('jadwal_id')->unique()->filter()->values()->toArray();
        $siswaIds = $nilais->pluck('siswa_id')->unique()->filter()->values()->toArray();

        $sesisMap = CbtSesi::whereIn('jadwal_id', $jadwalIds)
            ->whereIn('siswa_id', $siswaIds)
            ->get()
            ->keyBy(fn ($s) => $s->jadwal_id . '_' . $s->siswa_id);

        return $nilais->map(function ($n) use ($sesisMap) {
            $kelasObj = $n->siswa?->kelas ?? $n->jadwal?->kelas;
            $jurusanObj = $kelasObj?->jurusan ?? $n->jadwal?->bankSoal?->jurusan;
            $mapelName = $n->jadwal?->bankSoal?->mataPelajaran?->nama ?? 'Informatika';

            $sesiKey = $n->jadwal_id . '_' . $n->siswa_id;
            $sesi = $sesisMap->get($sesiKey);

            $durasiMenitRiil = null;
            if ($n->waktu_mulai && $n->waktu_selesai) {
                $durasiMenitRiil = max(1, round($n->waktu_mulai->diffInSeconds($n->waktu_selesai) / 60));
            }

            return [
                'id' => $n->id,
                'jadwalId' => $n->jadwal_id,
                'judulUjian' => $n->jadwal?->judul_ujian ?? 'Ujian CBT',
                'bankSoalJudul' => $n->jadwal?->bankSoal?->judul ?? '-',
                'siswaId' => $n->siswa_id,
                'nis' => '100'.$n->siswa_id,
                'namaSiswa' => $n->siswa?->nama ?? 'Siswa',
                'kelasNama' => $kelasObj?->nama_kelas ?? 'X RPL 1',
                'tingkat' => $kelasObj?->tingkat ?? 'X',
                'jurusanNama' => $jurusanObj?->nama ?? 'Rekayasa Perangkat Lunak',
                'jurusanKode' => $jurusanObj?->kode ?? 'RPL',
                'mapelNama' => $mapelName,
                'jumlahSoal' => $n->jumlah_soal ?: 5,
                'jumlahBenar' => $n->jumlah_benar,
                'jumlahSalah' => $n->jumlah_salah,
                'nilai' => floatval($n->nilai),
                'status' => $n->status ?? (floatval($n->nilai) >= 75 ? 'Tuntas' : 'Belum Tuntas'),
                'waktuMulai' => $n->waktu_mulai ? $n->waktu_mulai->format('d M Y H:i') : '-',
                'waktuSelesai' => $n->waktu_selesai ? $n->waktu_selesai->format('d M Y H:i') : '-',
                'durasiMenitRiil' => $durasiMenitRiil,
                'sesiId' => $sesi?->id,
                'jumlahPelanggaran' => $sesi?->jumlah_pelanggaran ?? 0,
                'sesiStatus' => $sesi?->status ?? 'selesai',
            ];
        })->toArray();
    }

    /**
     * Store or update Bank Soal.
     */
    public function storeBankSoal(Request $request): RedirectResponse
    {
        $guru = $this->resolveGuru($request);

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'judul' => 'required|string|max:255',
            'kode' => 'nullable|string|max:50',
            'jurusan_id' => 'nullable|integer',
            'kelas_id' => 'nullable|integer',
            'mata_pelajaran_id' => 'nullable|integer',
            'deskripsi' => 'nullable|string',
            'jumlah_pilihan' => 'nullable|integer|min:3|max:5',
        ]);

        $data = [
            'judul' => $validated['judul'],
            'kode' => !empty($validated['kode']) ? $validated['kode'] : ('BS-'.strtoupper(Str::random(6))),
            'jurusan_id' => !empty($validated['jurusan_id']) ? $validated['jurusan_id'] : null,
            'kelas_id' => !empty($validated['kelas_id']) ? $validated['kelas_id'] : null,
            'mata_pelajaran_id' => !empty($validated['mata_pelajaran_id']) ? $validated['mata_pelajaran_id'] : null,
            'guru_id' => $guru?->id,
            'deskripsi' => $validated['deskripsi'] ?? null,
            'jumlah_pilihan' => $validated['jumlah_pilihan'] ?? 5,
        ];

        if (! empty($validated['id'])) {
            CbtBankSoal::where('id', $validated['id'])->update($data);
            $msg = 'Bank Soal CBT berhasil diperbarui!';
        } else {
            CbtBankSoal::create($data);
            $msg = 'Bank Soal CBT baru berhasil ditambahkan!';
        }

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo) {
            return redirect($redirectTo)->with('message', $msg);
        }
        return redirect()->to(route('guru.dashboard') . '?tab=cbt-jadwal')->with('message', $msg);
    }

    /**
     * Delete Bank Soal.
     */
    public function deleteBankSoal(int $id): RedirectResponse
    {
        $user = request()->user();
        $guru = $this->resolveGuru(request());
        $guruId = $guru?->id;

        $query = CbtBankSoal::where('id', $id);
        if ($guruId && ! $user->hasRole('admin')) {
            $query->where(function ($q) use ($guruId) {
                $q->whereNull('guru_id')->orWhere('guru_id', $guruId);
            });
        }
        $query->delete();

        return redirect()->back()->with('message', 'Bank Soal CBT berhasil dihapus!');
    }

    /**
     * Store or update Question (Butir Soal A-E).
     */
    public function storeSoal(Request $request): RedirectResponse
    {
        // Normalize decimal comma to dot (e.g. "2,5" -> "2.5")
        if ($request->has('bobot')) {
            $request->merge(['bobot' => str_replace(',', '.', (string)$request->input('bobot'))]);
        }

        $tipeSoal = $request->input('tipe_soal', 'pilihan_ganda');
        if (! in_array($tipeSoal, ['pilihan_ganda', 'match', 'reorder', 'categorize'])) {
            $tipeSoal = 'pilihan_ganda';
        }

        $rules = [
            'id' => 'nullable|integer',
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

        $existing = ! empty($validated['id']) ? CbtSoal::find($validated['id']) : null;

        // Locked & sequential question numbering
        if (! empty($validated['id'])) {
            $nomorUrut = $existing?->nomor_urut ?? ($validated['nomor_urut'] ?? 1);
        } else {
            $lastNomor = CbtSoal::where('bank_soal_id', $validated['bank_soal_id'])->max('nomor_urut') ?? 0;
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

        // Handle Question Media (Image or Audio file)
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
                Storage::disk('public')->delete($existing->media_soal);
            }
            $data['media_soal'] = null;
            $data['tipe_media_soal'] = null;
        }

        // Handle Option Images (A - E)
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
                    Storage::disk('public')->delete($existing->$colKey);
                }
                $data[$colKey] = null;
            }
        }

        if (! empty($validated['id'])) {
            CbtSoal::where('id', $validated['id'])->update($data);
            $msg = "Butir soal #{$nomorUrut} berhasil diperbarui!";
        } else {
            CbtSoal::create($data);
            $msg = "Butir soal #{$nomorUrut} baru berhasil ditambahkan!";
        }

        return redirect()->back()->with('message', $msg);
    }

    /**
     * Delete Question (Butir Soal) and re-sequence remaining questions.
     */
    public function deleteSoal(int $id): RedirectResponse
    {
        $user = request()->user();
        $guru = $this->resolveGuru(request());
        $guruId = $guru?->id;

        $soal = CbtSoal::with('bankSoal')->find($id);
        if ($soal) {
            if ($guruId && ! $user->hasRole('admin')) {
                if ($soal->bankSoal && $soal->bankSoal->guru_id && $soal->bankSoal->guru_id !== $guruId) {
                    return redirect()->back()->with('error', 'Akses ditolak: Anda tidak memiliki hak untuk menghapus butir soal ini.');
                }
            }

            $bankSoalId = $soal->bank_soal_id;

            // Delete associated media files
            if ($soal->media_soal) {
                Storage::disk('public')->delete($soal->media_soal);
            }
            foreach (['a', 'b', 'c', 'd', 'e'] as $opt) {
                $colKey = "gambar_opsi_{$opt}";
                if ($soal->$colKey) {
                    Storage::disk('public')->delete($soal->$colKey);
                }
            }

            $soal->delete();

            // Re-sequence remaining questions to always be strictly 1, 2, 3, 4, ...
            $remaining = CbtSoal::where('bank_soal_id', $bankSoalId)
                ->orderBy('nomor_urut', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($remaining as $index => $item) {
                $correctNumber = $index + 1;
                if ($item->nomor_urut !== $correctNumber) {
                    $item->update(['nomor_urut' => $correctNumber]);
                }
            }
        }

        return redirect()->back()->with('message', 'Butir soal CBT berhasil dihapus dan nomor soal telah diurutkan kembali!');
    }

    /**
     * Update points weight for ALL questions in a Bank Soal (Bulk setting).
     */
    public function updateAllBobot(Request $request): RedirectResponse
    {
        if ($request->has('bobot')) {
            $request->merge(['bobot' => str_replace(',', '.', (string)$request->input('bobot'))]);
        }

        $validated = $request->validate([
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'bobot' => 'required|numeric|min:0.01',
        ]);

        $user = request()->user();
        $guru = $this->resolveGuru(request());
        $guruId = $guru?->id;

        if ($guruId && ! $user->hasRole('admin')) {
            $bankSoal = CbtBankSoal::find($validated['bank_soal_id']);
            if ($bankSoal && $bankSoal->guru_id && $bankSoal->guru_id !== $guruId) {
                return redirect()->back()->with('error', 'Akses ditolak: Anda tidak memiliki hak untuk mengubah bobot bank soal ini.');
            }
        }

        $bobot = floatval($validated['bobot']);

        $updatedCount = CbtSoal::where('bank_soal_id', $validated['bank_soal_id'])
            ->update(['bobot' => $bobot]);

        $formattedBobot = fmod($bobot, 1) === 0.0 ? number_format($bobot, 0) : str_replace('.', ',', (string)$bobot);

        return redirect()->back()->with('message', "Bobot seluruh butir soal ({$updatedCount} soal) berhasil diubah menjadi {$formattedBobot} poin!");
    }

    /**
     * Render Dedicated Page: Import Butir Soal CBT Massal (Word / CSV).
     */
    public function renderImportPage(int $bankSoalId, Request $request): Response
    {
        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;

        $bankSoal = CbtBankSoal::query()
            ->with([
                'jurusan:id,kode,nama',
                'kelas:id,nama_kelas,tingkat',
                'mataPelajaran:id,kode,nama',
            ])
            ->findOrFail($bankSoalId);

        $user = $request->user();
        if ($guruId && ! $user->hasRole('admin')) {
            if ($bankSoal->guru_id && $bankSoal->guru_id !== $guruId) {
                abort(403, 'Akses ditolak: Anda tidak memiliki izin untuk mengelola bank soal ini.');
            }
        }

        $existingQuestionsCount = CbtSoal::where('bank_soal_id', $bankSoalId)->count();

        return Inertia::render('Guru/CbtImportSoalPage', [
            'bankSoal' => [
                'id' => $bankSoal->id,
                'kode' => $bankSoal->kode,
                'judul' => $bankSoal->judul,
                'tingkat' => $bankSoal->tingkat,
                'jurusan' => $bankSoal->jurusan?->nama,
                'jurusanKode' => $bankSoal->jurusan?->kode,
                'kelas' => $bankSoal->kelas?->nama_kelas,
                'mataPelajaran' => $bankSoal->mataPelajaran?->nama,
                'existingCount' => $existingQuestionsCount,
            ],
        ]);
    }

    /**
     * Bulk import CBT questions (from Word parser or CSV).
     */
    public function importBulkSoal(Request $request): RedirectResponse
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

        $user = request()->user();
        $guru = $this->resolveGuru(request());
        $guruId = $guru?->id;

        if ($guruId && ! $user->hasRole('admin')) {
            $bankSoal = CbtBankSoal::find($validated['bank_soal_id']);
            if ($bankSoal && $bankSoal->guru_id && $bankSoal->guru_id !== $guruId) {
                return redirect()->back()->with('error', 'Akses ditolak: Anda tidak memiliki hak untuk mengimpor soal ke bank soal ini.');
            }
        }

        $bankSoalId = $validated['bank_soal_id'];
        $soalsInput = $validated['soals'];

        DB::transaction(function () use ($bankSoalId, $soalsInput) {
            $lastNomor = CbtSoal::where('bank_soal_id', $bankSoalId)->max('nomor_urut') ?? 0;
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

                // Simpan gambar dari base64 jika ada
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
                                $filename = 'cbt_media/soals/' . Str::random(30) . '.' . $type;
                                Storage::disk('public')->put($filename, $decoded);
                                $mediaPath = $filename;
                                $tipeMedia = 'image';
                            }
                        }
                    }
                }

                CbtSoal::create([
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
        $redirectTo = $request->input('redirect_to');
        if ($redirectTo) {
            return redirect($redirectTo)->with('message', "Berhasil mengimpor {$count} butir soal ke dalam Bank Soal!");
        }
        return redirect()->to(route('guru.dashboard') . '?tab=cbt-bank-soal')->with('message', "Berhasil mengimpor {$count} butir soal ke dalam Bank Soal!");
    }

    /**
     * Store or update CBT Schedule.
     */
    /**
     * Render Dedicated Page: Buat Jadwal Pelaksanaan CBT Baru.
     */
    public function createJadwal(Request $request): Response
    {
        return $this->cbtJadwalFormData($request, null);
    }

    /**
     * Render Dedicated Page: Edit Jadwal Pelaksanaan CBT.
     */
    public function editJadwal(int $id, Request $request): Response
    {
        $jadwal = CbtJadwal::query()
            ->with(['bankSoal.mataPelajaran', 'jurusan', 'kelas'])
            ->findOrFail($id);

        return $this->cbtJadwalFormData($request, $jadwal);
    }

    /**
     * Helper to prepare Inertia props for CbtJadwalForm page.
     */
    private function cbtJadwalFormData(Request $request, ?CbtJadwal $jadwal): Response
    {
        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;

        $bankSoalList = self::getBankSoalsList($guruId);
        $jurusanList = Jurusan::query()->orderBy('id', 'asc')->get();

        $guruJadwals = \App\Models\Jadwal::query()
            ->when($guruId, fn ($q) => $q->where('guru_id', $guruId))
            ->get(['kelas_id', 'mata_pelajaran']);

        $mapelsByKelasId = [];
        foreach ($guruJadwals as $gj) {
            if ($gj->kelas_id && $gj->mata_pelajaran) {
                $mapelsByKelasId[$gj->kelas_id][] = $gj->mata_pelajaran;
            }
        }

        $masterKelasList = Kelas::query()->with(['jurusan'])->get()
            ->sort(function ($a, $b) {
                $t = strcmp($a->tingkat ?? 'X', $b->tingkat ?? 'X');
                if ($t !== 0) return $t;
                return strnatcasecmp($a->nama_kelas, $b->nama_kelas);
            })
            ->values()
            ->map(function ($k) use ($mapelsByKelasId) {
                $mapels = array_values(array_unique($mapelsByKelasId[$k->id] ?? []));
                return [
                    'id' => $k->id,
                    'namaKelas' => $k->nama_kelas,
                    'tingkat' => $k->tingkat ?? 'X',
                    'jurusan' => $k->jurusan?->nama ?? '',
                    'jurusanId' => $k->jurusan_id,
                    'jurusanKode' => $k->jurusan?->kode ?? '',
                    'mapels' => $mapels,
                ];
            })->toArray();

        $serializedJadwal = null;
        if ($jadwal) {
            $jurusanId = $jadwal->jurusan_id ?? $jadwal->kelas?->jurusan_id;
            $serializedJadwal = [
                'id' => $jadwal->id,
                'bank_soal_id' => $jadwal->bank_soal_id,
                'jurusan_id' => $jurusanId,
                'kelas_id' => $jadwal->kelas_id,
                'judul_ujian' => $jadwal->judul_ujian,
                'waktu_mulai' => $jadwal->waktu_mulai ? $jadwal->waktu_mulai->format('Y-m-d\TH:i') : '',
                'waktu_selesai' => $jadwal->waktu_selesai ? $jadwal->waktu_selesai->format('Y-m-d\TH:i') : '',
                'durasi_menit' => $jadwal->durasi_menit ?? 60,
                'token' => $jadwal->token ?? '',
                'acak_soal' => (bool) ($jadwal->acak_soal ?? true),
                'acak_opsi' => (bool) ($jadwal->acak_opsi ?? true),
                'status' => $jadwal->status ?? 'aktif',
                'mode_ujian' => $jadwal->mode_ujian ?? 'formal',
            ];
        }

        return Inertia::render('Guru/CbtJadwalForm', [
            'jadwalProp' => $serializedJadwal,
            'bankSoalList' => $bankSoalList,
            'masterKelasList' => $masterKelasList,
            'jurusanList' => $jurusanList,
        ]);
    }

    public function storeJadwal(Request $request): RedirectResponse
    {
        $guru = $this->resolveGuru($request);

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'jurusan_id' => 'nullable|integer',
            'kelas_id' => 'nullable|integer',
            'judul_ujian' => 'nullable|string|max:255',
            'waktu_mulai' => 'nullable|date',
            'waktu_selesai' => 'nullable|date',
            'durasi_menit' => 'nullable|integer|min:5|max:300',
            'token' => 'nullable|string|max:20',
            'acak_soal' => 'nullable|boolean',
            'acak_opsi' => 'nullable|boolean',
            'status' => 'nullable|string|in:aktif,nonaktif',
            'mode_ujian' => 'nullable|string|in:formal,game',
        ]);

        $bankSoal = CbtBankSoal::find($validated['bank_soal_id']);
        $judulUjian = !empty($validated['judul_ujian']) 
            ? trim($validated['judul_ujian']) 
            : ($bankSoal?->judul ?? 'Ujian CBT');

        $jurusanId = !empty($validated['jurusan_id']) ? $validated['jurusan_id'] : null;
        if (!$jurusanId && !empty($validated['kelas_id'])) {
            $jurusanId = \App\Models\Kelas::where('id', $validated['kelas_id'])->value('jurusan_id');
        }

        $data = [
            'bank_soal_id' => $validated['bank_soal_id'],
            'jurusan_id' => $jurusanId,
            'kelas_id' => !empty($validated['kelas_id']) ? $validated['kelas_id'] : null,
            'guru_id' => $guru?->id,
            'judul_ujian' => $judulUjian,
            'waktu_mulai' => !empty($validated['waktu_mulai']) ? Carbon::parse($validated['waktu_mulai']) : null,
            'waktu_selesai' => !empty($validated['waktu_selesai']) ? Carbon::parse($validated['waktu_selesai']) : null,
            'durasi_menit' => $validated['durasi_menit'] ?? 60,
            'token' => !empty($validated['token']) ? $validated['token'] : strtoupper(Str::random(6)),
            'acak_soal' => $request->boolean('acak_soal', true),
            'acak_opsi' => $request->boolean('acak_opsi', true),
            'status' => $validated['status'] ?? 'aktif',
            'mode_ujian' => $request->input('mode_ujian', 'formal'),
        ];

        if (! empty($validated['id'])) {
            CbtJadwal::where('id', $validated['id'])->update($data);
            $msg = 'Jadwal CBT berhasil diperbarui!';
        } else {
            CbtJadwal::create($data);
            $msg = 'Jadwal CBT baru berhasil dibuat!';
        }

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo) {
            return redirect($redirectTo)->with('message', $msg);
        }

        return redirect()->to(route('guru.dashboard') . '?tab=cbt-jadwal')->with('message', $msg);
    }

    /**
     * Toggle CBT Schedule active/inactive status.
     */
    public function toggleJadwalStatus(int $id): RedirectResponse
    {
        $jadwal = CbtJadwal::findOrFail($id);
        $newStatus = $jadwal->status === 'aktif' ? 'nonaktif' : 'aktif';
        $jadwal->update(['status' => $newStatus]);

        $statusText = $newStatus === 'aktif' ? 'Diaktifkan (Siswa dapat mengerjakan)' : 'Dinonaktifkan';

        return redirect()->back()->with('message', "Status jadwal CBT berhasil diubah menjadi {$statusText}!");
    }

    /**
     * Automatically synchronize a CBT score into TujuanPembelajaran (NilaiTp).
     */
    public static function syncCbtNilaiToTp(CbtNilai $cbtNilai, ?CbtJadwal $jadwal = null): void
    {
        if (! $jadwal) {
            $jadwal = $cbtNilai->jadwal()->withTrashed()->with(['bankSoal.mataPelajaran'])->first();
        }

        $mapel = $jadwal?->bankSoal?->mataPelajaran?->nama 
            ?? $cbtNilai->bankSoal?->mataPelajaran?->nama 
            ?? 'Informatika';

        $activeTa = TahunAjaran::getActive();
        $taId = $activeTa?->id ?? 1;
        $semester = $activeTa?->semester ?? 'Ganjil';

        $rawTitle = $jadwal?->judul_ujian ?? $cbtNilai->bankSoal?->judul ?? 'Ujian CBT';
        $tpTitle = str_starts_with($rawTitle, 'CBT:') ? $rawTitle : ('CBT: ' . $rawTitle);

        $tp = TujuanPembelajaran::where('judul', $tpTitle)
            ->where('mapel', $mapel)
            ->first();

        if (! $tp) {
            $tp = TujuanPembelajaran::where('judul', $rawTitle)
                ->where('mapel', $mapel)
                ->first();
        }

        if (! $tp) {
            $nextCount = TujuanPembelajaran::where('mapel', $mapel)->count() + 1;
            $tpKode = 'TP-CBT' . $nextCount;

            $tp = TujuanPembelajaran::create([
                'kode' => $tpKode,
                'judul' => $tpTitle,
                'mapel' => $mapel,
                'jenis' => 'Sumatif',
                'kktp' => 75,
                'semester' => $semester,
                'tahun_ajaran_id' => $taId,
            ]);
        }

        NilaiTp::updateOrCreate(
            [
                'tp_id' => $tp->id,
                'siswa_id' => $cbtNilai->siswa_id,
            ],
            [
                'nilai' => floatval($cbtNilai->nilai),
                'catatan' => "Nilai CBT Otomatis ({$cbtNilai->jumlah_benar} Benar, {$cbtNilai->jumlah_salah} Salah)",
                'guru_id' => $jadwal?->guru_id,
            ]
        );
    }

    /**
     * Batch synchronize all CBT scores into TujuanPembelajaran (NilaiTp).
     */
    public static function syncAllPendingCbtScoresToTp(?int $guruId = null): int
    {
        $query = CbtNilai::with(['jadwal' => fn ($q) => $q->withTrashed()->with('bankSoal.mataPelajaran'), 'bankSoal.mataPelajaran']);
        if ($guruId) {
            $query->whereHas('jadwal', function ($q) use ($guruId) {
                $q->withTrashed()->where(function ($inner) use ($guruId) {
                    $inner->where('guru_id', $guruId)->orWhereNull('guru_id');
                });
            });
        }

        $allScores = $query->get();
        $synced = 0;
        foreach ($allScores as $score) {
            self::syncCbtNilaiToTp($score, $score->jadwal);
            $synced++;
        }
        return $synced;
    }

    /**
     * Delete CBT Schedule (Soft-delete: preserves student exam scores in cbt-nilai and tab=nilai).
     */
    public function deleteJadwal(int $id): RedirectResponse
    {
        $jadwal = CbtJadwal::find($id);
        if ($jadwal) {
            // Pastikan seluruh nilai tersinkronisasi aman ke NilaiTp sebelum jadwal di-softdelete
            $cbtNilais = CbtNilai::where('jadwal_id', $jadwal->id)->get();
            foreach ($cbtNilais as $n) {
                self::syncCbtNilaiToTp($n, $jadwal);
            }

            $jadwal->delete();
        }

        return redirect()->back()->with('message', 'Jadwal CBT berhasil dihapus (riwayat nilai siswa tetap tersimpan aman di CBT Nilai dan Nilai TP)!');
    }

    /**
     * Store or update CBT Score.
     */
    public function storeNilai(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'siswa_id' => 'required|integer|exists:siswas,id',
            'jumlah_soal' => 'nullable|integer',
            'jumlah_benar' => 'required|integer',
            'jumlah_salah' => 'required|integer',
            'nilai' => 'required|numeric|min:0|max:100',
            'status' => 'nullable|string',
        ]);

        $status = $validated['status'] ?? (floatval($validated['nilai']) >= 75 ? 'Tuntas' : 'Belum Tuntas');

        $data = [
            'jadwal_id' => $validated['jadwal_id'],
            'siswa_id' => $validated['siswa_id'],
            'jumlah_soal' => $validated['jumlah_soal'] ?? ($validated['jumlah_benar'] + $validated['jumlah_salah']),
            'jumlah_benar' => $validated['jumlah_benar'],
            'jumlah_salah' => $validated['jumlah_salah'],
            'nilai' => $validated['nilai'],
            'status' => $status,
            'waktu_selesai' => now(),
        ];

        if (! empty($validated['id'])) {
            CbtNilai::where('id', $validated['id'])->update($data);
            $cbtNilai = CbtNilai::find($validated['id']);
            $msg = 'Hasil nilai CBT berhasil diperbarui!';
        } else {
            $cbtNilai = CbtNilai::create($data);
            $msg = 'Hasil nilai CBT berhasil dicatat!';
        }

        if ($cbtNilai) {
            self::syncCbtNilaiToTp($cbtNilai);
        }

        return redirect()->back()->with('message', $msg);
    }

    /**
     * Delete CBT Score.
     */
    public function deleteNilai(int $id): RedirectResponse
    {
        CbtNilai::where('id', $id)->delete();

        return redirect()->back()->with('message', 'Data nilai CBT berhasil dihapus!');
    }

    /**
     * Get real-time CBT Monitoring data for a specific exam schedule.
     */
    public function getMonitoringData(int $id): JsonResponse
    {
        $jadwal = CbtJadwal::with(['bankSoal.mataPelajaran', 'kelas', 'jurusan'])->findOrFail($id);
        $sesisRaw = CbtSesi::with(['siswa.kelas'])
            ->where('jadwal_id', $id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $nilais = CbtNilai::where('jadwal_id', $id)->get()->keyBy('siswa_id');

        $sesis = $sesisRaw->map(function ($s) use ($nilais) {
            $nilai = $nilais->get($s->siswa_id);

            return [
                'id' => $s->id,
                'siswaId' => $s->siswa_id,
                'namaSiswa' => $s->siswa?->nama ?? 'Siswa',
                'nis' => '100'.$s->siswa_id,
                'kelas' => $s->siswa?->kelas?->nama_kelas ?? '-',
                'status' => $s->status,
                'jumlahPelanggaran' => $s->jumlah_pelanggaran,
                'soalTerjawab' => $s->soal_terjawab,
                'sisaWaktuDetik' => $s->sisa_waktu_detik,
                'waktuGabung' => $s->waktu_gabung ? $s->waktu_gabung->format('H:i:s') : '-',
                'waktuMulai' => $s->waktu_mulai ? $s->waktu_mulai->format('H:i:s') : '-',
                'waktuSelesai' => $s->waktu_selesai ? $s->waktu_selesai->format('H:i:s') : '-',
                'nilai' => $nilai ? floatval($nilai->nilai) : null,
                'nilaiStatus' => $nilai?->status,
                'isOnline' => $s->last_heartbeat && $s->last_heartbeat->diffInSeconds(now()) < 60,
            ];
        });

        $stats = [
            'totalPeserta' => $sesis->count(),
            'menunggu' => $sesis->where('status', 'menunggu')->count(),
            'mengerjakan' => $sesis->where('status', 'mengerjakan')->count(),
            'selesai' => $sesis->where('status', 'selesai')->count(),
            'terkunci' => $sesis->where('status', 'terkunci')->count(),
            'totalPelanggaran' => $sesis->sum('jumlahPelanggaran'),
            'siswaMelanggar' => $sesis->where('jumlahPelanggaran', '>', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'jadwal' => [
                'id' => $jadwal->id,
                'judulUjian' => $jadwal->judul_ujian,
                'mapel' => $jadwal->bankSoal?->mataPelajaran?->nama ?? '-',
                'token' => $jadwal->token,
                'status' => $jadwal->status,
                'durasiMenit' => $jadwal->durasi_menit,
            ],
            'stats' => $stats,
            'peserta' => $sesis->values(),
        ]);
    }

    /**
     * Reset student exam session (e.g. if their laboratory PC experienced hardware issues).
     */
    public function resetSesiSiswa(Request $request, int $id)
    {
        $sesi = CbtSesi::findOrFail($id);
        $namaSiswa = $sesi->siswa?->nama ?? 'Siswa';

        // Delete submitted score if any so student can re-take cleanly
        CbtNilai::where('jadwal_id', $sesi->jadwal_id)->where('siswa_id', $sesi->siswa_id)->delete();

        $sesi->update([
            'status' => 'menunggu',
            'jumlah_pelanggaran' => 0,
            'soal_terjawab' => 0,
            'last_heartbeat' => now(),
        ]);

        $msg = "Sesi ujian untuk siswa {$namaSiswa} berhasil direset!";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return redirect()->back()->with('message', $msg);
    }

    /**
     * Lock student exam session if caught violating anti-cheat rules.
     */
    public function kunciSesiSiswa(Request $request, int $id)
    {
        $sesi = CbtSesi::findOrFail($id);
        $namaSiswa = $sesi->siswa?->nama ?? 'Siswa';

        $sesi->update([
            'status' => 'terkunci',
            'last_heartbeat' => now(),
        ]);

        $msg = "Sesi ujian untuk siswa {$namaSiswa} telah dikunci!";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return redirect()->back()->with('message', $msg);
    }

    /**
     * Unlock student exam session so student can resume exam.
     */
    public function bukaKunciSesiSiswa(Request $request, int $id)
    {
        $sesi = CbtSesi::findOrFail($id);
        $namaSiswa = $sesi->siswa?->nama ?? 'Siswa';

        $sesi->update([
            'status' => 'mengerjakan',
            'jumlah_pelanggaran' => 0,
            'last_heartbeat' => now(),
        ]);

        $msg = "Kunci ujian siswa {$namaSiswa} berhasil dibuka! Siswa dapat melanjutkan ujian.";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return redirect()->back()->with('message', $msg);
    }

    /**
     * Delete student session from CBT monitoring.
     */
    public function deleteSesiSiswa(Request $request, int $id)
    {
        $sesi = CbtSesi::findOrFail($id);
        $namaSiswa = $sesi->siswa?->nama ?? 'Siswa';

        // Also delete any existing exam result so student can rejoin anew
        CbtNilai::where('jadwal_id', $sesi->jadwal_id)->where('siswa_id', $sesi->siswa_id)->delete();
        $sesi->delete();

        $msg = "Sesi peserta ujian {$namaSiswa} berhasil dihapus dari monitoring!";
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return redirect()->back()->with('message', $msg);
    }

    /**
     * Transfer CBT exam scores directly into Tujuan Pembelajaran (NilaiTp).
     */
    public function transferToNilaiTp(Request $request): RedirectResponse
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
            'tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajarans,id',
            'overwrite' => 'nullable|boolean',
            'siswa_ids' => 'nullable|array',
            'siswa_ids.*' => 'integer|exists:siswas,id',
        ]);

        $guru = $this->resolveGuru($request);
        $guruId = $guru?->id;

        $jadwal = CbtJadwal::with(['bankSoal.mataPelajaran', 'bankSoal.jurusan', 'kelas'])->findOrFail($validated['jadwal_id']);

        // Determine or create Target TP
        if ($validated['mode'] === 'new_tp') {
            $tpMapel = !empty($validated['mapel']) ? $validated['mapel'] : ($jadwal->bankSoal?->mataPelajaran?->nama ?? 'Informatika');
            $tpJudul = !empty($validated['judul']) ? $validated['judul'] : ('Ujian CBT - ' . $jadwal->judul_ujian);
            $tpKode = !empty($validated['kode']) ? $validated['kode'] : ('TP-CBT' . rand(10, 99));

            $activeTa = TahunAjaran::getActive();
            $taId = !empty($validated['tahun_ajaran_id']) ? $validated['tahun_ajaran_id'] : ($activeTa?->id ?? 1);

            $tp = TujuanPembelajaran::create([
                'kode' => $tpKode,
                'judul' => $tpJudul,
                'mapel' => $tpMapel,
                'jenis' => $validated['jenis'] ?? 'Sumatif',
                'kktp' => $validated['kktp'] ?? 75,
                'semester' => $validated['semester'] ?? 'Ganjil',
                'tahun_ajaran_id' => $taId,
            ]);
            $targetTpId = $tp->id;
            $tpName = $tp->judul;
        } else {
            if (empty($validated['tp_id'])) {
                return redirect()->back()->with('error', 'Silakan pilih Tujuan Pembelajaran tujuan transfer.');
            }
            $targetTpId = $validated['tp_id'];
            $tp = TujuanPembelajaran::findOrFail($targetTpId);
            $tpName = $tp->judul;
        }

        // Fetch CBT scores
        $nilaisQuery = CbtNilai::where('jadwal_id', $jadwal->id);
        if (!empty($validated['siswa_ids'])) {
            $nilaisQuery->whereIn('siswa_id', $validated['siswa_ids']);
        }
        $cbtNilais = $nilaisQuery->get();

        if ($cbtNilais->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data nilai ujian CBT untuk ditransfer.');
        }

        $overwrite = isset($validated['overwrite']) ? (bool)$validated['overwrite'] : true;
        $transferredCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($cbtNilais, $targetTpId, $guruId, $overwrite, &$transferredCount, &$skippedCount) {
            foreach ($cbtNilais as $cbt) {
                if (!$overwrite) {
                    $exists = NilaiTp::where('tp_id', $targetTpId)->where('siswa_id', $cbt->siswa_id)->exists();
                    if ($exists) {
                        $skippedCount++;
                        continue;
                    }
                }

                NilaiTp::updateOrCreate(
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

        $msg = "Berhasil memasukkan {$transferredCount} nilai siswa dari CBT ke Penilaian TP ({$tpName})!";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} siswa dilewati karena sudah memiliki nilai).";
        }

        return redirect()->back()->with('message', $msg);
    }
}
