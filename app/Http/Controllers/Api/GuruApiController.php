<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Materi;
use App\Models\NilaiTp;
use App\Models\TujuanPembelajaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuruApiController extends Controller
{
    public function getJadwal(Request $request): JsonResponse
    {
        $jadwals = Jadwal::with('kelas')->paginate(50);
        return response()->json(['success' => true, 'data' => $jadwals]);
    }

    public function getAbsensi(Request $request): JsonResponse
    {
        $absensi = Absensi::with(['kelas', 'siswa'])->orderBy('tanggal', 'desc')->paginate(50);
        return response()->json(['success' => true, 'data' => $absensi]);
    }

    public function storeAbsensiBatch(Request $request): JsonResponse
    {
        $request->validate([
            'tanggal' => 'required|date',
            'kelas_id' => 'required|integer',
            'rows' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->rows as $row) {
                if (empty($row['status'])) continue;
                Absensi::updateOrCreate(
                    ['tanggal' => $request->tanggal, 'siswa_id' => $row['siswaId']],
                    ['kelas_id' => $request->kelas_id, 'status' => $row['status'], 'keterangan' => $row['keterangan'] ?? $row['status']]
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Presensi batch berhasil disimpan']);
    }

    public function getJurnal(): JsonResponse
    {
        $jurnals = Jurnal::with('kelas')->orderBy('tanggal', 'desc')->paginate(50);
        return response()->json(['success' => true, 'data' => $jurnals]);
    }

    public function storeJurnal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'jam_ke' => 'required|string',
            'kelas_id' => 'required|integer',
            'mata_pelajaran' => 'required|string',
            'materi' => 'required|string',
            'uraian' => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        $guru = $request->user()?->guru ?? \App\Models\Guru::query()->where('user_id', '=', $request->user()?->id)->first();
        $jurnal = Jurnal::updateOrCreate(['id' => $request->id], array_merge($validated, ['guru_id' => $guru?->id]));
        return response()->json(['success' => true, 'message' => 'Jurnal berhasil disimpan', 'data' => $jurnal]);
    }

    public function deleteJurnal(int $id): JsonResponse
    {
        Jurnal::where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Jurnal berhasil dihapus']);
    }

    public function getTp(): JsonResponse
    {
        $tps = TujuanPembelajaran::paginate(50);
        return response()->json(['success' => true, 'data' => $tps]);
    }

    public function storeTp(Request $request): JsonResponse
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
            $tp = TujuanPembelajaran::find($tpId);
            if ($tp) {
                $tp->update($dataToSave);
            } else {
                $tp = TujuanPembelajaran::create($dataToSave);
            }
        } else {
            $tp = TujuanPembelajaran::create($dataToSave);
        }

        return response()->json(['success' => true, 'message' => 'Tujuan Pembelajaran berhasil disimpan', 'data' => $tp]);
    }

    public function deleteTp(int $id): JsonResponse
    {
        TujuanPembelajaran::where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Tujuan Pembelajaran berhasil dihapus']);
    }

    public function getNilai(): JsonResponse
    {
        $nilai = NilaiTp::with(['tp', 'siswa'])->paginate(50);
        return response()->json(['success' => true, 'data' => $nilai]);
    }

    public function storeNilaiBatch(Request $request): JsonResponse
    {
        $request->validate([
            'tp_id' => 'required|integer',
            'scores' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->scores as $s) {
                if (isset($s['nilai']) && $s['nilai'] !== null) {
                    NilaiTp::updateOrCreate(
                        ['tp_id' => $request->tp_id, 'siswa_id' => $s['siswaId']],
                        ['nilai' => floatval($s['nilai'])]
                    );
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Nilai batch berhasil disimpan']);
    }

    public function getMateri(): JsonResponse
    {
        $materi = Materi::with('tp')->orderBy('id', 'desc')->paginate(50);
        return response()->json(['success' => true, 'data' => $materi]);
    }

    public function toggleMateriStatus(int $id, Request $request): JsonResponse
    {
        $materi = Materi::findOrFail($id);
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

    public function getCbtBankSoal(): JsonResponse
    {
        $data = \App\Models\CbtBankSoal::with(['jurusan', 'kelas', 'mataPelajaran', 'soals'])->orderBy('id', 'desc')->paginate(50);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeCbtBankSoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'kode' => 'nullable|string|max:50',
            'jurusan_id' => 'nullable|integer',
            'kelas_id' => 'nullable|integer',
            'mata_pelajaran_id' => 'nullable|integer',
            'deskripsi' => 'nullable|string',
        ]);

        $item = \App\Models\CbtBankSoal::create($validated);
        return response()->json(['success' => true, 'message' => 'Bank Soal berhasil dibuat', 'data' => $item]);
    }

    public function getCbtJadwal(): JsonResponse
    {
        $data = \App\Models\CbtJadwal::with(['bankSoal', 'kelas', 'nilais'])->orderBy('id', 'desc')->paginate(50);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeCbtJadwal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'kelas_id' => 'nullable|integer',
            'judul_ujian' => 'required|string|max:255',
            'durasi_menit' => 'nullable|integer',
            'acak_soal' => 'nullable|boolean',
            'acak_opsi' => 'nullable|boolean',
            'status' => 'nullable|string|in:aktif,nonaktif',
        ]);

        $item = \App\Models\CbtJadwal::create($validated);
        return response()->json(['success' => true, 'message' => 'Jadwal CBT berhasil dibuat', 'data' => $item]);
    }

    public function toggleCbtJadwal(int $id): JsonResponse
    {
        $jadwal = \App\Models\CbtJadwal::findOrFail($id);
        $newStatus = $jadwal->status === 'aktif' ? 'nonaktif' : 'aktif';
        $jadwal->update(['status' => $newStatus]);
        return response()->json(['success' => true, 'message' => 'Status berhasil diubah', 'status' => $newStatus]);
    }

    public function deleteCbtBankSoal(int $id): JsonResponse
    {
        $bankSoal = \App\Models\CbtBankSoal::findOrFail($id);
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
            'id' => 'nullable|integer',
            'bank_soal_id' => 'required|integer|exists:cbt_bank_soals,id',
            'nomor_urut' => 'nullable|integer',
            'tipe_soal' => 'nullable|string|in:pilihan_ganda,match,reorder,categorize',
            'stimulus_teks' => 'nullable|string',
            'data_interaktif' => 'nullable',
            'pertanyaan' => 'required|string',
            'bobot' => 'nullable|numeric|min:0.01',
            'posisi_media_soal' => 'nullable|string|in:atas,bawah,samping',
            'media_soal_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg,gif,mp3,wav,ogg,m4a|max:12288',
            'tipe_media_soal' => 'nullable|string|in:image,audio',
            'hapus_media_soal' => 'nullable',
            'gambar_opsi_a_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg,gif|max:5120',
            'gambar_opsi_b_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg,gif|max:5120',
            'gambar_opsi_c_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg,gif|max:5120',
            'gambar_opsi_d_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg,gif|max:5120',
            'gambar_opsi_e_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg,gif|max:5120',
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

        $existing = ! empty($validated['id']) ? \App\Models\CbtSoal::find($validated['id']) : null;

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
                $path = $file->store('cbt_media/opsi', 'public');
                $data[$colKey] = $path;
            } elseif ($request->boolean($delKey)) {
                if ($existing?->$colKey) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($existing->$colKey);
                }
                $data[$colKey] = null;
            }
        }

        $soal = \App\Models\CbtSoal::updateOrCreate(['id' => $request->id], $data);
        return response()->json(['success' => true, 'message' => 'Soal CBT berhasil disimpan', 'data' => $soal]);
    }

    public function deleteCbtSoal(int $id): JsonResponse
    {
        $soal = \App\Models\CbtSoal::findOrFail($id);
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
                        if (in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                            $decoded = base64_decode($rawBase64);
                            if ($decoded !== false) {
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
        $jadwal = \App\Models\CbtJadwal::findOrFail($id);
        $jadwal->delete();
        return response()->json(['success' => true, 'message' => 'Jadwal CBT berhasil dihapus']);
    }

    public function getCbtNilai(): JsonResponse
    {
        $data = \App\Models\CbtNilai::with(['jadwal', 'siswa', 'bankSoal'])->orderBy('id', 'desc')->paginate(50);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getMonitoringData(int $id): JsonResponse
    {
        return app(\App\Http\Controllers\Guru\GuruCbtController::class)->getMonitoringData($id);
    }

    public function resetSesiSiswa(Request $request, int $id): JsonResponse
    {
        $sesi = \App\Models\CbtSesi::findOrFail($id);
        \App\Models\CbtNilai::where('jadwal_id', $sesi->jadwal_id)->where('siswa_id', $sesi->siswa_id)->delete();
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
        $sesi = \App\Models\CbtSesi::findOrFail($id);
        $sesi->update(['status' => 'terkunci', 'last_heartbeat' => now()]);
        return response()->json(['success' => true, 'message' => 'Sesi siswa berhasil dikunci']);
    }

    public function bukaKunciSesiSiswa(Request $request, int $id): JsonResponse
    {
        $sesi = \App\Models\CbtSesi::findOrFail($id);
        $sesi->update(['status' => 'mengerjakan', 'jumlah_pelanggaran' => 0, 'last_heartbeat' => now()]);
        return response()->json(['success' => true, 'message' => 'Kunci ujian siswa berhasil dibuka']);
    }

    public function deleteSesiSiswa(Request $request, int $id): JsonResponse
    {
        $sesi = \App\Models\CbtSesi::findOrFail($id);
        \App\Models\CbtNilai::where('jadwal_id', $sesi->jadwal_id)->where('siswa_id', $sesi->siswa_id)->delete();
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

        $guru = $request->user()?->guru ?? \App\Models\Guru::query()->where('user_id', '=', $request->user()?->id)->first();
        $guruId = $guru?->id;

        $jadwal = \App\Models\CbtJadwal::with(['bankSoal.mataPelajaran'])->findOrFail($validated['jadwal_id']);

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
}
