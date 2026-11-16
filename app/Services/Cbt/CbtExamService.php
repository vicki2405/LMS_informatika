<?php

namespace App\Services\Cbt;

use App\Models\CbtJadwal;
use App\Models\CbtSesi;
use App\Models\CbtSoal;
use App\Models\Jadwal;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CbtExamService
{
    public function assertJadwalEligible(CbtJadwal $jadwal, Siswa $siswa, bool $isEnteringWaitingRoom = false): void
    {
        if ($jadwal->status !== 'aktif') {
            $this->fail('Jadwal ujian saat ini sedang tidak aktif.');
        }

        if ($jadwal->kelas_id !== null && (int) $jadwal->kelas_id !== (int) $siswa->kelas_id) {
            $this->fail('Anda tidak terdaftar pada kelas ujian ini.');
        }

        // Pengecekan kesesuaian mapel kelas hanya berlaku untuk jadwal umum (kelas_id null).
        // Jika jadwal ditugaskan spesifik ke kelas siswa (kelas_id !== null), guru sudah secara eksplisit
        // menentukan bahwa kelas ini berhak mengikuti ujian tersebut.
        if ($siswa->kelas_id !== null && $jadwal->kelas_id === null) {
            $jadwal->loadMissing(['bankSoal.mataPelajaran', 'bankSoal.guru']);
            $mapelName = $jadwal->bankSoal?->mataPelajaran?->nama;
            $mapelKode = $jadwal->bankSoal?->mataPelajaran?->kode;

            if ($mapelName) {
                $cleanMapel = strtolower(trim(preg_replace('/\(.*?\)/', '', $mapelName)));
                $hasScheduledMapel = Jadwal::where('kelas_id', $siswa->kelas_id)
                    ->whereNotNull('mata_pelajaran')
                    ->where(function ($q) use ($mapelName, $mapelKode, $cleanMapel) {
                        $q->whereRaw('LOWER(TRIM(mata_pelajaran)) = ?', [strtolower(trim($mapelName))])
                            ->orWhereRaw('LOWER(TRIM(mata_pelajaran)) LIKE ?', ['%'.$cleanMapel.'%']);
                        if ($mapelKode) {
                            $q->orWhereRaw('LOWER(TRIM(mata_pelajaran)) = ?', [strtolower(trim($mapelKode))]);
                        }
                    })
                    ->exists();

                $classHasAnySchedule = Jadwal::where('kelas_id', $siswa->kelas_id)->exists();

                if ($classHasAnySchedule && ! $hasScheduledMapel) {
                    $this->fail('Mata pelajaran ujian ini tidak ada dalam jadwal pelajaran kelas Anda.');
                }
            }
        }

        if ($jadwal->jurusan_id !== null) {
            $siswa->loadMissing('kelas');
            if ((int) $jadwal->jurusan_id !== (int) $siswa->kelas?->jurusan_id) {
                $this->fail('Anda tidak terdaftar pada jurusan ujian ini.');
            }
        }

        $now = now();

        // Saat siswa memasuki Ruang Tunggu (Waiting Room), jangan blokir berdasarkan waktu_mulai.
        // Ruang tunggu dirancang agar siswa bisa berkumpul dan bersiap sebelum ujian dibuka.
        // Waktu mulai hanya divalidasi ketika siswa menekan tombol "Mulai Mengerjakan" (start).
        if (! $isEnteringWaitingRoom) {
            // Toleransi clock-skew 15 menit agar perbedaan jam client dan server hosting tidak memblokir siswa
            if ($jadwal->waktu_mulai && $now->copy()->addMinutes(15)->lt($jadwal->waktu_mulai)) {
                $this->fail('Ujian belum dimulai. Sesi ujian dijadwalkan pada '.$jadwal->waktu_mulai->format('d M Y H:i').' WIB.');
            }
        }

        if ($jadwal->waktu_selesai && $now->gt($jadwal->waktu_selesai)) {
            $this->fail('Waktu ujian telah berakhir pada '.$jadwal->waktu_selesai->format('d M Y H:i').' WIB.');
        }
    }

    public function start(CbtJadwal $jadwal, Siswa $siswa, string $token, Request $request): CbtSesi
    {
        $this->assertJadwalEligible($jadwal, $siswa);

        $throttleKey = 'cbt_token_fail:'.($siswa->id ?? 0).':'.$jadwal->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->fail("Terlalu banyak percobaan token salah. Tunggu {$seconds} detik sebelum mencoba lagi.");
        }

        $expectedToken = strtoupper(trim((string) $jadwal->token));
        $submittedToken = strtoupper(trim($token));

        if ($expectedToken === '' || ! hash_equals($expectedToken, $submittedToken)) {
            RateLimiter::hit($throttleKey, 60);
            $this->fail('Token ujian tidak valid.');
        }

        RateLimiter::clear($throttleKey);

        $sesi = CbtSesi::firstOrCreate(
            [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $siswa->id,
            ],
            [
                'status' => 'menunggu',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'soal_terjawab' => 0,
                'jumlah_pelanggaran' => 0,
                'sisa_waktu_detik' => ($jadwal->durasi_menit ?? 60) * 60,
                'waktu_gabung' => now(),
                'last_heartbeat' => now(),
            ]
        );

        if ($sesi->status === 'selesai') {
            $this->fail('Ujian ini sudah Anda kumpulkan.');
        }

        if ($sesi->status === 'terkunci') {
            $this->fail('Sesi ujian Anda terkunci. Hubungi guru pengawas.');
        }

        if ($sesi->waktu_mulai && $this->remainingSeconds($jadwal, $sesi) <= 0) {
            $this->fail('Waktu ujian telah habis.');
        }

        $sesi->update([
            'status' => 'mengerjakan',
            'token_entered' => $submittedToken,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'waktu_mulai' => $sesi->waktu_mulai ?? now(),
            'sisa_waktu_detik' => $this->remainingSeconds($jadwal, $sesi),
            'last_heartbeat' => now(),
        ]);

        return $sesi->fresh();
    }

    public function assertCanSubmit(CbtJadwal $jadwal, Siswa $siswa): CbtSesi
    {
        $sesi = CbtSesi::where('jadwal_id', $jadwal->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if (! $sesi) {
            $this->fail('Sesi ujian tidak ditemukan. Masuk melalui ruang tunggu terlebih dahulu.');
        }

        if ($sesi->status === 'selesai') {
            $this->fail('Ujian ini sudah Anda kumpulkan.');
        }

        if ($sesi->status === 'terkunci') {
            $this->fail('Sesi ujian Anda terkunci. Hubungi guru pengawas.');
        }

        if (! $sesi->token_entered || ! $sesi->waktu_mulai) {
            $this->fail('Anda belum memulai ujian dengan token yang valid.');
        }

        $deadline = $sesi->waktu_mulai->copy()
            ->addMinutes($jadwal->durasi_menit ?? 60)
            ->addSeconds(120);

        if (now()->gt($deadline)) {
            // Jika waktu ujian terlampaui saat auto-submit tiba di server,
            // sisa waktu diset ke 0 dan sistem tetap memproses penilaian lembar jawaban (graceful auto-finalize).
            $sesi->update(['sisa_waktu_detik' => 0]);
        }

        return $sesi;
    }

    public function remainingSeconds(CbtJadwal $jadwal, CbtSesi $sesi): int
    {
        $limit = ($jadwal->durasi_menit ?? 60) * 60;

        if (! $sesi->waktu_mulai) {
            return $limit;
        }

        $elapsed = (int) $sesi->waktu_mulai->diffInSeconds(now(), false);

        return max(0, $limit - max(0, $elapsed));
    }

    public function serializeQuestions(CbtJadwal $jadwal): array
    {
        $jadwal->loadMissing('bankSoal.soals');
        $isGameMode = ($jadwal->mode_ujian ?? 'formal') === 'game';

        return ($jadwal->bankSoal?->soals ?? collect())
            ->sortBy('nomor_urut')
            ->map(function ($s) {
                $rawInteractive = is_array($s->data_interaktif)
                    ? $s->data_interaktif
                    : json_decode($s->data_interaktif ?? '[]', true);
                $safeInteractiveData = null;

                if ($s->tipe_soal === 'match' && is_array($rawInteractive)) {
                    $pasanganKiri = [];
                    $pilihanKanan = [];
                    $kunciMatch = [];
                    foreach ($rawInteractive as $idx => $pair) {
                        $leftId = (string) ($pair['id'] ?? ($idx + 1));
                        $pasanganKiri[] = [
                            'id' => $leftId,
                            'teks' => $pair['kiri'] ?? '',
                        ];
                        if (! empty($pair['kanan'])) {
                            $pilihanKanan[] = [
                                'id' => 'k_'.($idx + 1),
                                'teks' => $pair['kanan'],
                            ];
                            $kunciMatch[$leftId] = $pair['kanan'];
                        }
                    }
                    shuffle($pilihanKanan);
                    $safeInteractiveData = [
                        'pasanganKiri' => $pasanganKiri,
                        'pilihanKanan' => $pilihanKanan,
                    ];
                } elseif ($s->tipe_soal === 'reorder' && is_array($rawInteractive)) {
                    $items = [];
                    foreach ($rawInteractive as $idx => $item) {
                        $itemId = (string) ($item['id'] ?? ($idx + 1));
                        $items[] = [
                            'id' => $itemId,
                            'teks' => $item['teks'] ?? '',
                        ];
                    }
                    shuffle($items);
                    $safeInteractiveData = ['items' => $items];
                } elseif ($s->tipe_soal === 'categorize' && is_array($rawInteractive)) {
                    $categories = $rawInteractive['kategori'] ?? [];
                    $rawItems = $rawInteractive['items'] ?? [];
                    $items = [];
                    foreach ($rawItems as $idx => $item) {
                        $itemId = (string) ($item['id'] ?? ($idx + 1));
                        $items[] = [
                            'id' => $itemId,
                            'teks' => $item['teks'] ?? '',
                        ];
                    }
                    shuffle($items);
                    $safeInteractiveData = [
                        'kategori' => $categories,
                        'items' => $items,
                    ];
                }

                $soalPayload = [
                    'id' => $s->id,
                    'nomorUrut' => $s->nomor_urut,
                    'tipeSoal' => $s->tipe_soal ?? 'pilihan_ganda',
                    'pertanyaan' => $s->pertanyaan,
                    'stimulusTeks' => $s->stimulus_teks,
                    'dataInteraktif' => $safeInteractiveData,
                    'opsiA' => $s->opsi_a,
                    'opsiB' => $s->opsi_b,
                    'opsiC' => $s->opsi_c,
                    'opsiD' => $s->opsi_d,
                    'opsiE' => $s->opsi_e,
                    'bobot' => $s->bobot ?? 1,
                    'mediaSoal' => $s->media_soal ? ('/storage/'.ltrim($s->media_soal, '/')) : null,
                    'tipeMediaSoal' => $s->tipe_media_soal,
                    'posisiMediaSoal' => $s->posisi_media_soal ?? 'bawah',
                    'gambarOpsiA' => $s->gambar_opsi_a ? ('/storage/'.ltrim($s->gambar_opsi_a, '/')) : null,
                    'gambarOpsiB' => $s->gambar_opsi_b ? ('/storage/'.ltrim($s->gambar_opsi_b, '/')) : null,
                    'gambarOpsiC' => $s->gambar_opsi_c ? ('/storage/'.ltrim($s->gambar_opsi_c, '/')) : null,
                    'gambarOpsiD' => $s->gambar_opsi_d ? ('/storage/'.ltrim($s->gambar_opsi_d, '/')) : null,
                    'gambarOpsiE' => $s->gambar_opsi_e ? ('/storage/'.ltrim($s->gambar_opsi_e, '/')) : null,
                ];

                return $soalPayload;
            })
            ->values()
            ->toArray();
    }

    public function evaluateSingleAnswer(CbtSoal $soal, mixed $jawaban): array
    {
        $tipe = $soal->tipe_soal ?? 'pilihan_ganda';
        $rawInteractive = is_array($soal->data_interaktif)
            ? $soal->data_interaktif
            : json_decode($soal->data_interaktif ?? '[]', true);

        $isCorrect = false;

        if ($tipe === 'match') {
            $ans = is_string($jawaban) ? (json_decode($jawaban, true) ?? []) : ($jawaban ?? []);
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
                $isCorrect = ($correctPairs === $totalPairs);
            }
        } elseif ($tipe === 'reorder') {
            $ans = is_string($jawaban) ? (json_decode($jawaban, true) ?? []) : ($jawaban ?? []);
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
                $isCorrect = ($correctPositions === $totalItems);
            }
        } elseif ($tipe === 'categorize') {
            $ans = is_string($jawaban) ? (json_decode($jawaban, true) ?? []) : ($jawaban ?? []);
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
                $isCorrect = ($correctItems === $totalItems);
            }
        } else {
            // Default: pilihan_ganda
            $jawabanSiswa = strtoupper(trim(is_string($jawaban) ? $jawaban : ''));
            $kunci = strtoupper(trim($soal->kunci_jawaban ?? ''));
            $isCorrect = ($jawabanSiswa !== '' && $jawabanSiswa === $kunci);
        }

        return [
            'is_correct' => $isCorrect,
            'xp' => $isCorrect ? 100 : 0,
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['cbt' => $message]);
    }
}
