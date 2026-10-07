<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CbtJadwal;
use App\Models\CbtNilai;
use App\Models\Jadwal;
use App\Models\Materi;
use App\Models\NilaiTp;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\Tugas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiswaApiController extends Controller
{
    public function getMateri(): JsonResponse
    {
        $materi = Materi::where('is_published', true)->orderBy('id', 'desc')->paginate(20);
        return response()->json(['success' => true, 'data' => $materi]);
    }

    private function resolveSiswa($user): ?Siswa
    {
        $siswa = Siswa::where('nama', $user->name)->first();
        if (!$siswa && $user->kelas_id) {
            $siswa = Siswa::where('kelas_id', $user->kelas_id)->first();
        }
        return $siswa ?: Siswa::first();
    }

    public function getNilai(Request $request): JsonResponse
    {
        $user = $request->user();
        $siswa = $this->resolveSiswa($user);

        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan'], 404);
        }

        $nilai = NilaiTp::where('siswa_id', $siswa->id)->with('tp')->get();

        return response()->json(['success' => true, 'siswa' => $siswa->nama, 'data' => $nilai]);
    }

    public function getDaftarSiswa(Request $request): JsonResponse
    {
        $user = $request->user();
        $siswa = $this->resolveSiswa($user);
        $kelasId = $siswa?->kelas_id;

        $list = Siswa::where('kelas_id', $kelasId)
            ->where('status', 'Aktif')
            ->orderBy('nama', 'asc')
            ->get(['id', 'nama', 'jenis_kelamin', 'kelas_id'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'nis' => '100'.$s->id,
                'jenisKelamin' => $s->jenis_kelamin,
            ]);

        return response()->json(['success' => true, 'data' => $list]);
    }

    public function getCbtJadwals(Request $request): JsonResponse
    {
        $user = $request->user();
        $siswa = $this->resolveSiswa($user);
        $kelasId = $siswa?->kelas_id;

        $jadwals = CbtJadwal::with(['bankSoal.mataPelajaran', 'guru', 'kelas'])
            ->where('status', 'aktif')
            ->where(function ($q) use ($kelasId) {
                $q->whereNull('kelas_id');
                if ($kelasId) $q->orWhere('kelas_id', $kelasId);
            })
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $jadwals]);
    }

    public function getCbtNilais(Request $request): JsonResponse
    {
        $user = $request->user();
        $siswa = $this->resolveSiswa($user);
        $kelasId = $siswa?->kelas_id;

        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan'], 404);
        }

        $nilais = CbtNilai::with(['jadwal.bankSoal.mataPelajaran', 'siswa:id,nama'])
            ->whereHas('siswa', function ($q) use ($kelasId) {
                if ($kelasId) $q->where('kelas_id', $kelasId);
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'jadwalId' => $n->jadwal_id,
                'siswaId' => $n->siswa_id,
                'namaSiswa' => $n->siswa?->nama ?? 'Siswa',
                'nis' => '100'.$n->siswa_id,
                'judulUjian' => $n->jadwal?->judul_ujian ?? 'Ujian CBT',
                'mapelNama' => $n->jadwal?->bankSoal?->mataPelajaran?->nama ?? 'Umum',
                'nilai' => floatval($n->nilai),
                'status' => $n->status,
                'waktuSelesai' => $n->waktu_selesai ? $n->waktu_selesai->format('d M Y H:i') : '-',
            ]);

        return response()->json(['success' => true, 'data' => $nilais]);
    }

    public function submitCbtExam(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
            'answers' => 'nullable|array',
        ]);

        $user = $request->user();
        $siswaId = $validated['siswa_id'] ?? null;
        if (! $siswaId) {
            $siswa = $this->resolveSiswa($user);
            $siswaId = $siswa?->id ?? 1;
        }

        $jadwal = CbtJadwal::with(['bankSoal.soals'])->findOrFail($validated['jadwal_id']);
        $soals = $jadwal->bankSoal?->soals ?? collect();

        $totalSoal = $soals->count();
        $benar = 0;
        $salah = 0;
        $totalBobot = 0;
        $bobotBenar = 0;
        $answers = $validated['answers'] ?? [];

        foreach ($soals as $soal) {
            $bobot = $soal->bobot ?: 1;
            $totalBobot += $bobot;
            $tipe = $soal->tipe_soal ?? 'pilihan_ganda';
            $rawInteractive = is_array($soal->data_interaktif) ? $soal->data_interaktif : json_decode($soal->data_interaktif ?? '[]', true);

            if ($tipe === 'match') {
                $ans = $answers[$soal->id] ?? [];
                if (is_string($ans)) {
                    $ans = json_decode($ans, true) ?? [];
                }
                $totalPairs = is_array($rawInteractive) ? count($rawInteractive) : 0;
                $correctPairs = 0;
                if ($totalPairs > 0 && is_array($ans)) {
                    foreach ($rawInteractive as $idx => $pair) {
                        $leftId = (string) ($pair['id'] ?? ($idx + 1));
                        $expectedRight = trim($pair['kanan'] ?? '');
                        $givenRight = trim($ans[$leftId] ?? '');
                        if ($expectedRight !== '' && strcasecmp($expectedRight, $givenRight) === 0) {
                            $correctPairs++;
                        }
                    }
                    $ratio = $correctPairs / $totalPairs;
                    $bobotBenar += ($ratio * $bobot);
                    if ($ratio >= 0.5) {
                        $benar++;
                    } else {
                        $salah++;
                    }
                } else {
                    $salah++;
                }
            } elseif ($tipe === 'reorder') {
                $ans = $answers[$soal->id] ?? [];
                if (is_string($ans)) {
                    $ans = json_decode($ans, true) ?? [];
                }
                $expectedOrder = [];
                if (is_array($rawInteractive)) {
                    foreach ($rawInteractive as $idx => $item) {
                        $expectedOrder[] = (string) ($item['id'] ?? ($idx + 1));
                    }
                }
                $totalItems = count($expectedOrder);
                $correctPositions = 0;
                if ($totalItems > 0 && is_array($ans)) {
                    foreach ($expectedOrder as $pos => $id) {
                        if (isset($ans[$pos]) && (string) $ans[$pos] === (string) $id) {
                            $correctPositions++;
                        }
                    }
                    $ratio = $correctPositions / $totalItems;
                    $bobotBenar += ($ratio * $bobot);
                    if ($ratio >= 0.5) {
                        $benar++;
                    } else {
                        $salah++;
                    }
                } else {
                    $salah++;
                }
            } elseif ($tipe === 'categorize') {
                $ans = $answers[$soal->id] ?? [];
                if (is_string($ans)) {
                    $ans = json_decode($ans, true) ?? [];
                }
                $rawItems = is_array($rawInteractive) ? ($rawInteractive['items'] ?? []) : [];
                $totalItems = count($rawItems);
                $correctItems = 0;
                if ($totalItems > 0 && is_array($ans)) {
                    foreach ($rawItems as $idx => $item) {
                        $itemId = (string) ($item['id'] ?? ($idx + 1));
                        $expectedCat = trim($item['kategori'] ?? '');
                        $givenCat = trim($ans[$itemId] ?? '');
                        if ($expectedCat !== '' && strcasecmp($expectedCat, $givenCat) === 0) {
                            $correctItems++;
                        }
                    }
                    $ratio = $correctItems / $totalItems;
                    $bobotBenar += ($ratio * $bobot);
                    if ($ratio >= 0.5) {
                        $benar++;
                    } else {
                        $salah++;
                    }
                } else {
                    $salah++;
                }
            } else {
                $rawAns = $answers[$soal->id] ?? '';
                $jawabanSiswa = strtoupper(trim(is_string($rawAns) ? $rawAns : ''));
                $kunci = strtoupper(trim($soal->kunci_jawaban));

                if ($jawabanSiswa !== '' && $jawabanSiswa === $kunci) {
                    $benar++;
                    $bobotBenar += $bobot;
                } else {
                    $salah++;
                }
            }
        }

        $finalScore = $totalBobot > 0 ? round(($bobotBenar / $totalBobot) * 100, 1) : 0;
        $status = $finalScore >= 75 ? 'Tuntas' : 'Belum Tuntas';

        $cbtNilai = CbtNilai::updateOrCreate(
            [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $siswaId,
            ],
            [
                'bank_soal_id' => $jadwal->bank_soal_id,
                'jumlah_soal' => $totalSoal,
                'jumlah_benar' => $benar,
                'jumlah_salah' => $salah,
                'nilai' => $finalScore,
                'status' => $status,
                'waktu_mulai' => now(),
                'waktu_selesai' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Ujian CBT berhasil disubmit!',
            'data' => [
                'nilai' => $finalScore,
                'status' => $status,
                'benar' => $benar,
                'salah' => $salah,
            ],
        ]);
    }

    public function getJadwal(Request $request): JsonResponse
    {
        $user = $request->user();
        $siswa = $this->resolveSiswa($user);
        $kelasId = $siswa?->kelas_id;

        $jadwal = Jadwal::with('guru')
            ->when($kelasId, fn ($q) => $q->where('kelas_id', $kelasId))
            ->orderBy('jam_ke', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $jadwal]);
    }

    public function getTugas(Request $request): JsonResponse
    {
        $user = $request->user();
        $siswa = $this->resolveSiswa($user);
        $kelasId = $siswa?->kelas_id;
        $siswaId = $siswa?->id;

        $tugas = Tugas::where(function ($q) use ($kelasId) {
                if ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                }
            })
            ->with(['pengumpulans' => function ($q) use ($siswaId) {
                if ($siswaId) {
                    $q->where('siswa_id', $siswaId);
                }
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $tugas]);
    }

    public function submitTugas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tugas_id' => 'required|integer|exists:tugas,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
            'file_url' => 'required|url',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $siswaId = $validated['siswa_id'] ?? null;
        if (! $siswaId) {
            $siswa = $this->resolveSiswa($user);
            $siswaId = $siswa?->id;
        }

        if (! $siswaId) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan'], 404);
        }

        $pengumpulan = PengumpulanTugas::updateOrCreate(
            [
                'tugas_id' => $validated['tugas_id'],
                'siswa_id' => $siswaId,
            ],
            [
                'status' => 'Sudah Dikirim',
                'file_url' => $validated['file_url'],
                'catatan_siswa' => $validated['catatan'] ?? null,
                'dikirim_pada' => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Tugas berhasil dikumpulkan', 'data' => $pengumpulan]);
    }
}
