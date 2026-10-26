<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\CbtJadwal;
use App\Models\CbtNilai;
use App\Models\CbtSesi;
use App\Models\CbtSoal;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\MateriRead;
use App\Models\NilaiTp;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\Tugas;
use App\Http\Controllers\Guru\GuruCbtController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SiswaDashboardController extends Controller
{
    /**
     * Render main Student Dashboard view with live data from database.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Find Student Record in Database
        $userName = trim($user->name);
        $siswaObj = Siswa::query()->where('nama', '=', $userName)->first();

        if (! $siswaObj) {
            $siswaObj = Siswa::query()->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($userName)])->first();
        }

        if (! $siswaObj && $user->kelas_id) {
            $siswaObj = Siswa::query()
                ->where('kelas_id', $user->kelas_id)
                ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($userName)])
                ->first();
        }

        $siswaId = $siswaObj?->id;
        $kelasId = $siswaObj?->kelas_id ?? $user->kelas_id;
        $kelasObj = $siswaObj?->kelas ?? ($kelasId ? Kelas::query()->with('jurusan')->where('id', '=', $kelasId)->first() : null);
        $jurusanId = $kelasObj?->jurusan_id;
        $namaKelas = $kelasObj?->nama_kelas ?? 'Belum Ada Kelas';

        $cbtJadwals = $this->getCbtJadwals($kelasId, $jurusanId);
        $cbtNilais = $this->getCbtNilais($kelasId);
        $cbtJadwalIds = collect($cbtJadwals)->pluck('id')->toArray();
        $cbtSesis = $this->getCbtSesis($cbtJadwalIds, $kelasId);

        // Ambil mata pelajaran aktif yang terjadwal resmi diajarkan pada kelas siswa ini
        $kelasMapels = [];
        if ($kelasId) {
            $kelasMapels = Jadwal::query()
                ->where('kelas_id', '=', $kelasId)
                ->pluck('mata_pelajaran')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (empty($kelasMapels)) {
                $kelasMapels = NilaiTp::query()
                    ->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasId))
                    ->with('tp')
                    ->get()
                    ->pluck('tp.mapel')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }
        }

        return Inertia::render('Siswa/Dashboard', [
            'siswaObjProp' => [
                'id' => $siswaId,
                'nama' => $user->name,
                'nis' => $siswaObj?->id ? ('100'.$siswaObj->id) : '-',
                'kelas' => $namaKelas,
                'tingkat' => $kelasObj?->tingkat ?? 'X',
                'jurusan' => $kelasObj?->jurusan?->nama ?? 'Umum',
                'telepon' => $siswaObj?->telepon ?? '-',
                'isAkunKelas' => true,
            ],
            'siswaListProp' => $this->getSiswaListByKelas($kelasId),
            'cbtJadwalListProp' => $cbtJadwals,
            'cbtNilaiListProp' => $cbtNilais,
            'cbtSesiListProp' => $cbtSesis,
            'jadwalListProp' => $this->getJadwalData($kelasId),
            'materiListProp' => $this->getMateriData($kelasId),
            'tugasListProp' => $this->getTugasData($kelasId, $siswaId),
            'nilaiTpListProp' => $this->getNilaiTpData($siswaId),
            'tpListProp' => $this->getTpData($kelasId, $kelasMapels),
            'mapelListProp' => $kelasMapels,
            'nilaiListProp' => $this->getNilaiDataByKelas($kelasId),
            'tahunAjaranAktifProp' => fn () => TahunAjaran::getActive(),
        ]);
    }

    /**
     * Get list of active students in the current class for shared account identity selection.
     */
    private function getSiswaListByKelas(?int $kelasId): array
    {
        if (! $kelasId) {
            return [];
        }

        return Siswa::where('kelas_id', $kelasId)
            ->where('status', 'Aktif')
            ->orderBy('nama', 'asc')
            ->get(['id', 'nama', 'jenis_kelamin', 'kelas_id'])
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'nama' => $s->nama,
                    'nis' => '100'.$s->id,
                    'jenisKelamin' => $s->jenis_kelamin,
                ];
            })
            ->toArray();
    }

    private function getJadwalData(?int $kelasId): array
    {
        if (! $kelasId) {
            return [];
        }

        return Jadwal::query()->with('guru')->where('kelas_id', '=', $kelasId)->orderBy('jam_ke', 'asc')->get()->map(function ($j) {
            return [
                'id' => $j->id,
                'hari' => $j->hari,
                'jamKe' => $j->jam_ke,
                'waktu' => $j->waktu,
                'mapel' => $j->mata_pelajaran,
                'guru' => $j->guru?->nama ?? 'Guru Pengajar',
                'ruangan' => $j->ruangan ?? '-',
            ];
        })->toArray();
    }

    private function getMateriData(?int $kelasId): array
    {
        if (! $kelasId) {
            return [];
        }

        // Ambil daftar mata pelajaran unik yang aktif diajarkan pada kelas siswa ini dari Jadwal
        $kelasMapelNames = Jadwal::query()
            ->where('kelas_id', '=', $kelasId)
            ->pluck('mata_pelajaran')
            ->filter()
            ->map(fn ($m) => strtolower(trim($m)))
            ->unique()
            ->values()
            ->toArray();

        return Materi::query()
            ->with(['tp', 'mapel', 'kegiatans', 'cbtJadwal', 'reads'])
            ->where('is_published', '=', true)
            ->where(function ($q) use ($kelasId, $kelasMapelNames) {
                // 1. Ditujukan spesifik untuk kelas siswa ini
                $q->where('kelas_id', '=', $kelasId);

                // 2. ATAU ditujukan untuk semua kelas pada mapel tersebut (kelas_id null),
                // TAPI HANYA JIKA kelas siswa ini memang memiliki mapel tersebut dalam jadwal pelajarannya!
                if (! empty($kelasMapelNames)) {
                    $q->orWhere(function ($sub) use ($kelasMapelNames) {
                        $sub->whereNull('kelas_id')
                            ->where(function ($mQuery) use ($kelasMapelNames) {
                                $mQuery->whereHas('mapel', function ($mq) use ($kelasMapelNames) {
                                    $mq->whereIn(DB::raw('LOWER(TRIM(nama))'), $kelasMapelNames)
                                       ->orWhereIn(DB::raw('LOWER(TRIM(kode))'), $kelasMapelNames);
                                })->orWhereHas('tp', function ($tq) use ($kelasMapelNames) {
                                    $tq->whereIn(DB::raw('LOWER(TRIM(mapel))'), $kelasMapelNames);
                                });
                            });
                    });
                } else {
                    $q->orWhereNull('kelas_id');
                }
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'tpId' => $m->tp_id,
                    'tpKode' => $m->tp?->kode ?? 'TP',
                    'tpJudul' => $m->tp?->judul ?? '',
                    'tp' => $m->tp ? [
                        'id' => $m->tp->id,
                        'kode' => $m->tp->kode,
                        'judul' => $m->tp->judul,
                        'mapel' => $m->tp->mapel,
                    ] : null,
                    'mapel' => $m->mapel?->nama ?? $m->tp?->mapel ?? 'Mata Pelajaran',
                    'judul' => $m->judul,
                    'tipe' => $m->tipe,
                    'semester' => $m->semester ?? $m->tp?->semester ?? 'Ganjil',
                    'mapelId' => $m->mapel_id,
                    'isPublished' => (bool) $m->is_published,
                    'theme' => $m->theme ?? 'dark',
                    'cbtJadwalId' => $m->cbt_jadwal_id,
                    'cbtJadwal' => $m->cbtJadwal ? [
                        'id' => $m->cbtJadwal->id,
                        'judul' => $m->cbtJadwal->judul_ujian,
                        'tipe' => $m->cbtJadwal->mode_ujian ?? 'CBT',
                        'durasiMenit' => $m->cbtJadwal->durasi_menit,
                    ] : null,
                    'fileUrl' => $m->file_path ? asset('storage/'.$m->file_path) : ($m->file_url ?? ''),
                    'filePath' => $m->file_path,
                    'fileName' => $m->file_path ? basename($m->file_path) : '',
                    'deskripsi' => $m->deskripsi ?? '',
                    'deskripsiSingkat' => $m->deskripsi ?? '',
                    'kegiatan' => $m->kegiatans->map(fn ($s) => [
                        'id' => $s->id,
                        'label' => $s->label,
                        'judul' => $s->judul,
                        'layout' => $s->layout ?? 'standard',
                        'konten' => $s->konten,
                        'filePath' => $s->file_path,
                        'fileName' => $s->file_path ? basename($s->file_path) : '',
                        'fileUrl' => $s->file_path ? asset('storage/'.$s->file_path) : ($s->file_url ?? ''),
                    ])->values(),
                    'reads' => $m->reads->map(fn ($r) => [
                        'siswaId' => $r->siswa_id,
                        'status' => $r->status,
                        'progressPersen' => $r->progress_persen,
                        'terakhirDibaca' => $r->terakhir_dibaca_at?->format('d M Y H:i'),
                    ])->values(),
                    'tanggal' => $m->created_at?->format('d M Y') ?? '-',
                ];
            })->toArray();
    }

    private function getTugasData(?int $kelasId, ?int $siswaId): array
    {
        if (! $kelasId) {
            return [];
        }

        $tugasList = Tugas::query()
            ->where('kelas_id', '=', $kelasId)
            ->with(['pengumpulans' => function ($q) use ($siswaId) {
                if ($siswaId) {
                    $q->where('siswa_id', '=', $siswaId);
                }
            }])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($t) {
                $pengumpulan = $t->pengumpulans->first();

                return [
                    'id' => $t->id,
                    'mapel' => $t->mata_pelajaran,
                    'judul' => $t->judul,
                    'deadline' => $t->tenggat?->format('d M Y, H.i').' WIB',
                    'status' => $pengumpulan?->status ?? 'Belum Dikerjakan',
                    'nilai' => $pengumpulan?->nilai,
                    'instruksi' => $t->instruksi ?? '',
                ];
            });

        return $tugasList->toArray();
    }

    /**
     * Get active CBT exam schedules for this class / jurusan.
     */
    private function getCbtJadwals(?int $kelasId, ?int $jurusanId): array
    {
        $jadwalIds = CbtJadwal::where('status', 'aktif')->pluck('id');
        $takenNilais = CbtNilai::whereIn('jadwal_id', $jadwalIds)
            ->whereHas('siswa', function ($q) use ($kelasId) {
                if ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                }
            })
            ->get(['id', 'jadwal_id', 'siswa_id', 'nilai', 'status']);

        return CbtJadwal::query()
            ->with([
                'bankSoal.mataPelajaran:id,nama',
                'bankSoal.soals' => fn ($q) => $q->orderBy('nomor_urut', 'asc'),
                'guru:id,nama',
                'jurusan:id,nama,kode',
                'kelas:id,nama_kelas',
            ])
            ->where('status', 'aktif')
            ->where(function ($q) use ($kelasId) {
                $q->whereNull('kelas_id');
                if ($kelasId) {
                    $q->orWhere('kelas_id', $kelasId);
                }
            })
            ->where(function ($q) use ($jurusanId) {
                $q->whereNull('jurusan_id');
                if ($jurusanId) {
                    $q->orWhere('jurusan_id', $jurusanId);
                }
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($j) use ($takenNilais) {
                $jadwalNilais = $takenNilais->where('jadwal_id', $j->id);

                return [
                    'id' => $j->id,
                    'judulUjian' => $j->judul_ujian,
                    'bankSoalJudul' => $j->bankSoal?->judul ?? 'Paket Ujian CBT',
                    'mapelNama' => $j->bankSoal?->mataPelajaran?->nama ?? 'Mata Pelajaran',
                    'guruNama' => $j->guru?->nama ?? 'Guru Pengajar',
                    'durasiMenit' => $j->durasi_menit ?? 60,
                    'waktuMulai' => $j->waktu_mulai ? $j->waktu_mulai->format('d M Y H:i') : 'Fleksibel',
                    'waktuSelesai' => $j->waktu_selesai ? $j->waktu_selesai->format('d M Y H:i') : 'Selesai',
                    'token' => $j->token,
                    'acakSoal' => (bool) ($j->acak_soal ?? true),
                    'acakOpsi' => (bool) ($j->acak_opsi ?? true),
                    'modeUjian' => $j->mode_ujian ?? 'formal',
                    'jumlahSoal' => $j->bankSoal?->soals->count() ?? 0,
                    'totalSelesai' => $jadwalNilais->count(),
                    'riwayatPeserta' => $jadwalNilais->map(fn ($r) => [
                        'siswaId' => $r->siswa_id,
                        'nilai' => floatval($r->nilai),
                        'status' => $r->status,
                    ])->values()->toArray(),
                    // Send question items safely without revealing correct keys in formal mode
                    'soals' => $j->bankSoal?->soals->map(function ($s) use ($j) {
                        $isGameMode = ($j->mode_ujian ?? 'formal') === 'game';
                        $rawInteractive = is_array($s->data_interaktif) ? $s->data_interaktif : json_decode($s->data_interaktif ?? '[]', true);
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
                                        'id' => 'k_' . ($idx + 1),
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
                            $safeInteractiveData = [
                                'items' => $items,
                            ];
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
                            'mediaSoal' => $s->media_soal ? asset('storage/' . $s->media_soal) : null,
                            'tipeMediaSoal' => $s->tipe_media_soal,
                            'posisiMediaSoal' => $s->posisi_media_soal ?? 'bawah',
                            'gambarOpsiA' => $s->gambar_opsi_a ? asset('storage/' . $s->gambar_opsi_a) : null,
                            'gambarOpsiB' => $s->gambar_opsi_b ? asset('storage/' . $s->gambar_opsi_b) : null,
                            'gambarOpsiC' => $s->gambar_opsi_c ? asset('storage/' . $s->gambar_opsi_c) : null,
                            'gambarOpsiD' => $s->gambar_opsi_d ? asset('storage/' . $s->gambar_opsi_d) : null,
                            'gambarOpsiE' => $s->gambar_opsi_e ? asset('storage/' . $s->gambar_opsi_e) : null,
                        ];

                        if ($isGameMode && (! $s->tipe_soal || $s->tipe_soal === 'pilihan_ganda')) {
                            $soalPayload['kunciJawaban'] = strtoupper($s->kunci_jawaban);
                        }

                        return $soalPayload;
                    })->values()->toArray() ?? [],
                ];
            })
            ->toArray();
    }

    /**
     * Get CBT exam history and scores for students in this class.
     */
    private function getCbtNilais(?int $kelasId): array
    {
        if (! $kelasId) {
            return [];
        }

        return CbtNilai::query()
            ->with([
                'jadwal.bankSoal.mataPelajaran:id,nama',
                'jadwal.guru:id,nama',
                'siswa:id,nama,kelas_id',
            ])
            ->whereHas('siswa', function ($q) use ($kelasId) {
                $q->where('kelas_id', $kelasId);
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'jadwalId' => $n->jadwal_id,
                    'siswaId' => $n->siswa_id,
                    'namaSiswa' => $n->siswa?->nama ?? 'Siswa',
                    'nis' => '100'.$n->siswa_id,
                    'judulUjian' => $n->jadwal?->judul_ujian ?? 'Ujian CBT',
                    'mapelNama' => $n->jadwal?->bankSoal?->mataPelajaran?->nama ?? 'Umum',
                    'guruNama' => $n->jadwal?->guru?->nama ?? 'Guru Pengajar',
                    'jumlahSoal' => $n->jumlah_soal,
                    'jumlahBenar' => $n->jumlah_benar,
                    'jumlahSalah' => $n->jumlah_salah,
                    'nilai' => floatval($n->nilai),
                    'status' => $n->status ?? (floatval($n->nilai) >= 75 ? 'Tuntas' : 'Belum Tuntas'),
                    'waktuSelesai' => $n->waktu_selesai ? $n->waktu_selesai->format('d M Y H:i') : '-',
                ];
            })
            ->toArray();
    }

    private function getNilaiTpData(?int $siswaId): array
    {
        return NilaiTp::query()->where('siswa_id', '=', $siswaId)->with('tp')->get()->map(function ($n) {
            $nilai = $n->nilai;
            $predikat = '-';
            if (! is_null($nilai)) {
                if ($nilai >= 90) {
                    $predikat = 'A';
                } elseif ($nilai >= 80) {
                    $predikat = 'B';
                } elseif ($nilai >= 75) {
                    $predikat = 'C';
                } else {
                    $predikat = 'D';
                }
            }

            return [
                'id' => $n->id,
                'kodeTP' => $n->tp?->kode ?? 'TP-01',
                'mapel' => $n->tp?->mapel ?? 'Umum',
                'deskripsi' => $n->tp?->judul ?? 'Tujuan Pembelajaran',
                'sudahDinilai' => ! is_null($nilai),
                'nilai' => $nilai,
                'predikat' => $predikat,
                'catatanGuru' => null,
            ];
        })->toArray();
    }

    private function getTpData(?int $kelasId = null, array $kelasMapels = []): array
    {
        $activeTa = TahunAjaran::getActive();

        $query = TujuanPembelajaran::query()
            ->with('tahunAjaran')
            ->orderBy('kode', 'asc');

        if ($activeTa) {
            $query->where(function ($q) use ($activeTa) {
                $q->where('tahun_ajaran_id', '=', $activeTa->id)
                  ->orWhereNull('tahun_ajaran_id');
            });
        }

        if (! empty($kelasMapels)) {
            $query->where(function ($q) use ($kelasMapels) {
                $q->whereIn('mapel', $kelasMapels);
                foreach ($kelasMapels as $m) {
                    $q->orWhere('mapel', 'LIKE', "%{$m}%");
                }
            });
        }

        return $query->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'kode' => $t->kode,
                    'mapel' => $t->mapel ?? 'Umum',
                    'judul' => $t->judul,
                    'jenis' => $t->jenis,
                    'kktp' => $t->kktp ?? 75,
                    'semester' => $t->semester ?? 'Ganjil',
                    'tahunAjaran' => $t->tahunAjaran?->kode ?? '2025/2026',
                ];
            })
            ->toArray();
    }

    private function getNilaiDataByKelas(?int $kelasId): array
    {
        $query = NilaiTp::query()->with(['tp', 'siswa']);
        if ($kelasId) {
            $query->whereHas('siswa', function ($q) use ($kelasId) {
                $q->where('kelas_id', $kelasId);
            });
        }

        return $query->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'tpId' => $n->tp_id,
                'siswaId' => $n->siswa_id,
                'nilai' => $n->nilai,
                'catatan' => $n->catatan,
            ];
        })->toArray();
    }

    /**
     * Resolve and authorize the active student for student-role operations.
     * Prevents IDOR (Insecure Direct Object Reference) while supporting both
     * individual student accounts and shared classroom accounts.
     */
    private function resolveAuthorizedSiswa(Request $request, ?int $requestedSiswaId = null): ?Siswa
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $userName = trim($user->name);

        // 1. Check if user is an individual student (user's name matches a Siswa record)
        $personalSiswa = Siswa::query()
            ->where(function ($q) use ($userName) {
                $q->where('nama', '=', $userName)
                  ->orWhereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($userName)]);
            })
            ->when($user->kelas_id, fn ($q) => $q->where('kelas_id', $user->kelas_id))
            ->first();

        if ($personalSiswa) {
            // Individual student account: always strictly bound to themselves
            return $personalSiswa;
        }

        // 2. If user is a shared classroom account (user has kelas_id)
        if ($user->kelas_id) {
            if ($requestedSiswaId) {
                return Siswa::query()
                    ->where('id', $requestedSiswaId)
                    ->where('kelas_id', $user->kelas_id)
                    ->first();
            }
            return Siswa::query()->where('kelas_id', $user->kelas_id)->first();
        }

        // 3. Fallback for admin or unassigned accounts
        if ($requestedSiswaId) {
            return Siswa::query()->find($requestedSiswaId);
        }

        return null;
    }

    /**
     * Persist a student task submission (pengumpulan tugas) into the database.
     */
    public function submitTugas(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tugasId' => 'required|integer|exists:tugas,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
            'linkHasil' => 'required|url',
            'catatan' => 'nullable|string|max:1000',
        ]);

        $requestedId = $validated['siswa_id'] ?? $request->input('siswa_id') ?? $request->input('siswaId');
        $siswaObj = $this->resolveAuthorizedSiswa($request, $requestedId ? (int) $requestedId : null);

        if (! $siswaObj) {
            return redirect()->back()->with('error', 'Akun siswa tidak terhubung ke data siswa yang valid. Silakan pilih identitas siswa.');
        }

        PengumpulanTugas::updateOrCreate(
            [
                'tugas_id' => $validated['tugasId'],
                'siswa_id' => $siswaObj->id,
            ],
            [
                'status' => 'Sudah Dikirim',
                'file_url' => $validated['linkHasil'],
                'catatan_siswa' => $validated['catatan'] ?? null,
                'dikirim_pada' => now(),
            ]
        );

        return redirect()->back()->with('message', "Tugas berhasil dikirim ke guru untuk {$siswaObj->nama}!");
    }

    /**
     * Submit and grade CBT exam answers from student.
     */
    public function submitCbtExam(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
            'answers' => 'nullable|array',
            'waktu_mulai' => 'nullable|string',
        ]);

        $siswaObj = $this->resolveAuthorizedSiswa($request, $validated['siswa_id'] ?? null);

        if (! $siswaObj) {
            return redirect()->back()->with('error', 'Identitas siswa tidak valid atau tidak terdaftar di kelas ini.');
        }

        $siswaId = $siswaObj->id;

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
                // Default: pilihan_ganda
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
                'waktu_mulai' => ! empty($validated['waktu_mulai']) ? Carbon::parse($validated['waktu_mulai']) : now(),
                'waktu_selesai' => now(),
            ]
        );

        // Otomatis sinkronkan nilai ke Lembar Nilai Siswa (NilaiTp)
        GuruCbtController::syncCbtNilaiToTp($cbtNilai, $jadwal);

        // Count actual non-empty answered questions
        $actualAnsweredCount = collect($answers)->filter(function ($val) {
            if (is_array($val)) {
                return count(array_filter($val)) > 0;
            }
            return $val !== null && trim((string) $val) !== '';
        })->count();

        // Update CBT session status to selesai
        CbtSesi::where('jadwal_id', $jadwal->id)
            ->where('siswa_id', $siswaId)
            ->update([
                'status' => 'selesai',
                'soal_terjawab' => min($totalSoal, $actualAnsweredCount),
                'waktu_selesai' => now(),
                'last_heartbeat' => now(),
            ]);

        $siswaRecord = Siswa::find($siswaId);
        $namaSiswa = $siswaRecord ? $siswaRecord->nama : 'Siswa';

        return redirect()->back()->with('message', "Ujian CBT berhasil diselesaikan untuk {$namaSiswa}! Nilai: {$finalScore} ({$status})");
    }

    /**
     * Get active CBT exam sessions for students in this class.
     */
    private function getCbtSesis(array $jadwalIds, ?int $kelasId): array
    {
        if (empty($jadwalIds)) {
            return [];
        }

        return CbtSesi::with(['siswa:id,nama,kelas_id'])
            ->whereIn('jadwal_id', $jadwalIds)
            ->whereHas('siswa', function ($q) use ($kelasId) {
                if ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                }
            })
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'jadwalId' => $s->jadwal_id,
                    'siswaId' => $s->siswa_id,
                    'namaSiswa' => $s->siswa?->nama ?? 'Siswa',
                    'nis' => '100'.$s->siswa_id,
                    'status' => $s->status,
                    'jumlahPelanggaran' => $s->jumlah_pelanggaran,
                    'soalTerjawab' => $s->soal_terjawab,
                    'sisaWaktuDetik' => $s->sisa_waktu_detik,
                    'waktuGabung' => $s->waktu_gabung ? $s->waktu_gabung->format('H:i') : null,
                ];
            })
            ->toArray();
    }

    /**
     * Join CBT Exam Terminal / Waiting Room before starting exam.
     */
    public function gabungRuangTunggu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
        ]);

        $siswa = $this->resolveAuthorizedSiswa($request, $validated['siswa_id'] ?? null);
        if (! $siswa) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak: siswa tidak valid atau bukan anggota kelas ini.'], 403);
        }

        $sesi = CbtSesi::firstOrCreate(
            [
                'jadwal_id' => $validated['jadwal_id'],
                'siswa_id' => $siswa->id,
            ],
            [
                'status' => 'menunggu',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'soal_terjawab' => 0,
                'jumlah_pelanggaran' => 0,
                'sisa_waktu_detik' => 0,
                'waktu_gabung' => now(),
                'last_heartbeat' => now(),
            ]
        );

        if ($sesi->status === 'menunggu') {
            $sesi->update([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'waktu_gabung' => $sesi->waktu_gabung ?: now(),
                'last_heartbeat' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil bergabung ke ruang tunggu CBT!',
            'sesi' => [
                'id' => $sesi->id,
                'jadwalId' => $sesi->jadwal_id,
                'siswaId' => $sesi->siswa_id,
                'status' => $sesi->status,
                'jumlahPelanggaran' => $sesi->jumlah_pelanggaran,
                'sisaWaktuDetik' => $sesi->sisa_waktu_detik,
            ],
        ]);
    }

    /**
     * Exit CBT Exam Terminal / Waiting Room.
     */
    public function keluarRuangTunggu(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
        ]);

        $siswa = $this->resolveAuthorizedSiswa($request, $validated['siswa_id'] ?? null);
        if (! $siswa) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak: identitas siswa tidak valid.'], 403);
        }

        CbtSesi::where('jadwal_id', $validated['jadwal_id'])
            ->where('siswa_id', $siswa->id)
            ->where('status', 'menunggu')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil keluar dari ruang tunggu.',
        ]);
    }

    /**
     * Record Anti-Cheat Violation (Tab switch, blur, split screen, AI access).
     */
    public function catatPelanggaran(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
            'jenis' => 'nullable|string',
        ]);

        $siswa = $this->resolveAuthorizedSiswa($request, $validated['siswa_id'] ?? null);
        if (! $siswa) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak valid atau akses ditolak.'], 403);
        }

        $sesi = CbtSesi::where('jadwal_id', $validated['jadwal_id'])
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($sesi) {
            $sesi->increment('jumlah_pelanggaran');

            // Bilamana keluar dari sistem lebih dari 3 kali (> 3 kali), langsung diblokir / dikunci
            $shouldLock = $sesi->jumlah_pelanggaran > 3 || $sesi->status === 'terkunci';
            $newStatus = $shouldLock ? 'terkunci' : $sesi->status;

            $sesi->update([
                'status' => $newStatus,
                'last_heartbeat' => now(),
            ]);

            return response()->json([
                'success' => true,
                'jumlah_pelanggaran' => $sesi->jumlah_pelanggaran,
                'is_locked' => $shouldLock,
                'status' => $newStatus,
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Sesi tidak ditemukan.'], 404);
    }

    /**
     * Heartbeat ping to keep session alive and synchronize answers / timer / status.
     */
    public function heartbeatSesi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jadwal_id' => 'required|integer|exists:cbt_jadwals,id',
            'siswa_id' => 'nullable|integer|exists:siswas,id',
            'soal_terjawab' => 'nullable|integer',
            'sisa_waktu_detik' => 'nullable|integer',
            'status' => 'nullable|string|in:menunggu,mengerjakan,selesai,terkunci',
        ]);

        $siswa = $this->resolveAuthorizedSiswa($request, $validated['siswa_id'] ?? null);
        if (! $siswa) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak: data siswa tidak valid.'], 403);
        }

        $sesi = CbtSesi::where('jadwal_id', $validated['jadwal_id'])
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($sesi) {
            $updateData = ['last_heartbeat' => now()];
            if (isset($validated['soal_terjawab'])) {
                $updateData['soal_terjawab'] = $validated['soal_terjawab'];
            }
            if (isset($validated['sisa_waktu_detik'])) {
                $updateData['sisa_waktu_detik'] = $validated['sisa_waktu_detik'];
            }
            if (! empty($validated['status'])) {
                // Tetap kunci jika sesi sedang diblokir oleh guru atau sistem (>3 pelanggaran)
                if ($sesi->status === 'terkunci') {
                    $updateData['status'] = 'terkunci';
                } else {
                    $updateData['status'] = $validated['status'];
                    if ($validated['status'] === 'mengerjakan' && ! $sesi->waktu_mulai) {
                        $updateData['waktu_mulai'] = now();
                    }
                }
            }
            $sesi->update($updateData);

            return response()->json([
                'success' => true,
                'status' => $sesi->status,
                'jumlah_pelanggaran' => $sesi->jumlah_pelanggaran,
                'is_locked' => $sesi->status === 'terkunci',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Sesi tidak ditemukan.'], 404);
    }

    /**
     * Record or update when a student opens/reads learning material.
     */
    public function trackMateriOpen(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'siswa_id' => 'nullable|integer|exists:siswas,id',
            'progress_persen' => 'nullable|integer|min:0|max:100',
        ]);

        $siswa = $this->resolveAuthorizedSiswa($request, $validated['siswa_id'] ?? null);
        if (! $siswa) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak: siswa tidak valid.'], 403);
        }

        $materi = Materi::findOrFail($id);

        $read = MateriRead::firstOrNew([
            'materi_id' => $materi->id,
            'siswa_id' => $siswa->id,
        ]);

        if (! $read->exists) {
            $read->status = 'sedang_membaca';
            $read->progress_persen = $validated['progress_persen'] ?? 10;
            $read->opened_at = now();
            $read->terakhir_dibaca_at = now();
            $read->save();
        } else {
            if (! $read->opened_at) {
                $read->opened_at = now();
            }
            if ($read->status !== 'selesai') {
                $read->status = 'sedang_membaca';
                $newProgress = $validated['progress_persen'] ?? $read->progress_persen;
                if ($newProgress > $read->progress_persen) {
                    $read->progress_persen = $newProgress;
                }
            }
            $read->terakhir_dibaca_at = now();
            $read->save();
        }

        return response()->json([
            'success' => true,
            'status' => $read->status,
            'progress_persen' => $read->progress_persen,
        ]);
    }

    /**
     * Mark material reading as completed by student.
     */
    public function trackMateriComplete(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'siswa_id' => 'nullable|integer|exists:siswas,id',
        ]);

        $siswa = $this->resolveAuthorizedSiswa($request, $validated['siswa_id'] ?? null);
        if (! $siswa) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak: siswa tidak valid.'], 403);
        }

        $materi = Materi::findOrFail($id);

        $read = MateriRead::firstOrNew([
            'materi_id' => $materi->id,
            'siswa_id' => $siswa->id,
        ]);

        if (! $read->opened_at) {
            $read->opened_at = now();
        }
        $read->status = 'selesai';
        $read->progress_persen = 100;
        $read->completed_at = now();
        $read->terakhir_dibaca_at = now();
        $read->save();

        return response()->json([
            'success' => true,
            'status' => 'selesai',
            'progress_persen' => 100,
        ]);
    }
}
