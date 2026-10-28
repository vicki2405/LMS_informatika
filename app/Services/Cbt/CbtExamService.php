<?php

namespace App\Services\Cbt;

use App\Models\CbtJadwal;
use App\Models\CbtSesi;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CbtExamService
{
    public function assertJadwalEligible(CbtJadwal $jadwal, Siswa $siswa): void
    {
        if ($jadwal->status !== 'aktif') {
            $this->fail('Jadwal ujian tidak aktif.');
        }

        if ($jadwal->kelas_id !== null && (int) $jadwal->kelas_id !== (int) $siswa->kelas_id) {
            $this->fail('Anda tidak terdaftar pada kelas ujian ini.');
        }

        if ($jadwal->jurusan_id !== null) {
            $siswa->loadMissing('kelas');
            if ((int) $jadwal->jurusan_id !== (int) $siswa->kelas?->jurusan_id) {
                $this->fail('Anda tidak terdaftar pada jurusan ujian ini.');
            }
        }

        $now = now();

        if ($jadwal->waktu_mulai && $now->lt($jadwal->waktu_mulai)) {
            $this->fail('Ujian belum dimulai.');
        }

        if ($jadwal->waktu_selesai && $now->gt($jadwal->waktu_selesai)) {
            $this->fail('Waktu ujian telah berakhir.');
        }
    }

    public function start(CbtJadwal $jadwal, Siswa $siswa, string $token, Request $request): CbtSesi
    {
        $this->assertJadwalEligible($jadwal, $siswa);

        $expectedToken = strtoupper(trim((string) $jadwal->token));
        $submittedToken = strtoupper(trim($token));

        if ($expectedToken === '' || ! hash_equals($expectedToken, $submittedToken)) {
            $this->fail('Token ujian tidak valid.');
        }

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
            $this->fail('Waktu ujian telah habis.');
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
            ->map(function ($s) use ($isGameMode) {
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
                    if ($isGameMode) {
                        $safeInteractiveData['kunciJawaban'] = $kunciMatch;
                    }
                } elseif ($s->tipe_soal === 'reorder' && is_array($rawInteractive)) {
                    $items = [];
                    $kunciReorder = [];
                    foreach ($rawInteractive as $idx => $item) {
                        $itemId = (string) ($item['id'] ?? ($idx + 1));
                        $items[] = [
                            'id' => $itemId,
                            'teks' => $item['teks'] ?? '',
                        ];
                        $kunciReorder[] = $itemId;
                    }
                    shuffle($items);
                    $safeInteractiveData = ['items' => $items];
                    if ($isGameMode) {
                        $safeInteractiveData['kunciJawaban'] = $kunciReorder;
                    }
                } elseif ($s->tipe_soal === 'categorize' && is_array($rawInteractive)) {
                    $categories = $rawInteractive['kategori'] ?? [];
                    $rawItems = $rawInteractive['items'] ?? [];
                    $items = [];
                    $kunciCategorize = [];
                    foreach ($rawItems as $idx => $item) {
                        $itemId = (string) ($item['id'] ?? ($idx + 1));
                        $items[] = [
                            'id' => $itemId,
                            'teks' => $item['teks'] ?? '',
                        ];
                        $kunciCategorize[$itemId] = trim($item['kategori'] ?? '');
                    }
                    shuffle($items);
                    $safeInteractiveData = [
                        'kategori' => $categories,
                        'items' => $items,
                    ];
                    if ($isGameMode) {
                        $safeInteractiveData['kunciJawaban'] = $kunciCategorize;
                    }
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
                    'mediaSoal' => $s->media_soal ? asset('storage/'.$s->media_soal) : null,
                    'tipeMediaSoal' => $s->tipe_media_soal,
                    'posisiMediaSoal' => $s->posisi_media_soal ?? 'bawah',
                    'gambarOpsiA' => $s->gambar_opsi_a ? asset('storage/'.$s->gambar_opsi_a) : null,
                    'gambarOpsiB' => $s->gambar_opsi_b ? asset('storage/'.$s->gambar_opsi_b) : null,
                    'gambarOpsiC' => $s->gambar_opsi_c ? asset('storage/'.$s->gambar_opsi_c) : null,
                    'gambarOpsiD' => $s->gambar_opsi_d ? asset('storage/'.$s->gambar_opsi_d) : null,
                    'gambarOpsiE' => $s->gambar_opsi_e ? asset('storage/'.$s->gambar_opsi_e) : null,
                ];

                if ($isGameMode && (! $s->tipe_soal || $s->tipe_soal === 'pilihan_ganda')) {
                    $soalPayload['kunciJawaban'] = strtoupper((string) $s->kunci_jawaban);
                }

                return $soalPayload;
            })
            ->values()
            ->toArray();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['cbt' => $message]);
    }
}
