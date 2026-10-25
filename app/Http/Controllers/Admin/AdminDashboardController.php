<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\KelompokMapel;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use App\Services\Security\FileSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Display the main Admin Dashboard overview and all master school data.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'guruListProp' => fn () => $this->getGuruList(),
            'waliKelasListProp' => fn () => $this->getWaliKelasList(),
            'siswaListProp' => fn () => $this->getSiswaList(),
            'kelasListProp' => fn () => $this->getKelasList(),
            'jurusanListProp' => fn () => $this->getJurusanList(),
            'mapelListProp' => fn () => $this->getMapelList(),
            'kelompokMapelListProp' => fn () => $this->getKelompokMapelList(),
            'userAccountsListProp' => fn () => $this->getUserAccountsList(),
            'tahunAjaranListProp' => fn () => $this->getTahunAjaranList(),
        ]);
    }

    private function getGuruList(): array
    {
        return \App\Models\Guru::with('user')->orderBy('id', 'desc')->get()->map(function ($g) {
            return [
                'id' => $g->id,
                'nip' => $g->nip ?? ('19850110201' . sprintf('%03d', $g->id)),
                'nama' => $g->nama,
                'email' => $g->user?->email ?? '-',
                'mapelUtama' => $g->mapel_utama ?? '-',
                'telepon' => $g->telepon ?? '-',
                'user_id' => $g->user_id,
                'hasAccount' => $g->user_id ? true : false,
                'status' => 'Aktif',
            ];
        })->toArray();
    }

    private function getWaliKelasList(): array
    {
        return \App\Models\Guru::has('kelasBinaan')->with('kelasBinaan', 'user')->get()->map(function ($g) {
            return [
                'id' => $g->id,
                'nip' => $g->nip ?? ('19850110201' . sprintf('%03d', $g->id)),
                'nama' => $g->nama,
                'email' => $g->user?->email ?? '-',
                'kelasBinaan' => $g->kelasBinaan?->nama_kelas ?? 'Belum Ditugaskan',
                'telepon' => $g->telepon ?? '-',
            ];
        })->toArray();
    }

    private function getSiswaList(): array
    {
        return Siswa::query()
            ->select(['id', 'nis', 'nama', 'jenis_kelamin', 'kelas_id', 'telepon', 'status', 'tanggal_status'])
            ->with(['kelas:id,nama_kelas,tingkat,guru_id', 'kelas.waliKelas:id,nama'])
            ->orderBy('nama', 'asc')
            ->get()
            ->map(function ($s) {
            return [
                'id' => $s->id,
                'nis' => $s->nis ?: ('100' . $s->id),
                'nama' => $s->nama,
                'jenisKelamin' => $s->jenis_kelamin,
                'kelas' => $s->kelas?->nama_kelas ?? 'X RPL 1',
                'tingkat' => $s->kelas?->tingkat ?? 'X',
                'waliKelas' => $s->kelas?->waliKelas?->nama ?? '-',
                'telepon' => $s->telepon ?? '',
                'teleponSiswa' => $s->telepon ?? '',
                'namaOrangTua' => 'Bapak/Ibu ' . explode(' ', $s->nama)[0],
                'teleponOrtu' => '081299887766',
                'linkMaps' => 'https://maps.google.com/?q=-6.200000,106.816666',
                'status' => $s->status ?? 'Aktif',
                'tanggal_status' => $s->tanggal_status,
            ];
        })->toArray();
    }

    private function getKelasList(): array
    {
        return Kelas::query()
            ->with(['jurusan', 'waliKelas'])
            ->withCount('siswas')
            ->get()
            ->sortBy('nama_kelas', SORT_NATURAL)
            ->values()
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'namaKelas' => $k->nama_kelas,
                    'tingkat' => $k->tingkat ?? 'X',
                    'jurusan' => $k->jurusan?->nama ?? 'Belum Diatur',
                    'jurusanKode' => $k->jurusan?->kode ?? null,
                    'jurusanId' => $k->jurusan_id,
                    'guruId' => $k->guru_id,
                    'waliKelas' => $k->waliKelas?->nama ?? 'Belum Ditugaskan',
                    'teleponWali' => $k->waliKelas?->telepon ?? '-',
                    'jumlahSiswa' => $k->siswas_count ?? 0,
                ];
            })->toArray();
    }

    private function getJurusanList(): array
    {
        return Jurusan::query()->orderBy('id', 'asc')->get()->map(function ($j) {
            return [
                'id' => $j->id,
                'kode' => $j->kode,
                'nama' => $j->nama,
                'deskripsi' => $j->deskripsi ?? '',
            ];
        })->toArray();
    }

    private function getMapelList(): array
    {
        return MataPelajaran::query()->orderBy('id', 'asc')->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'kode' => $m->kode,
                'nama' => $m->nama,
                'kelompok' => $m->kelompok ?? 'Umum',
                'deskripsi' => $m->deskripsi ?? '',
            ];
        })->toArray();
    }

    private function getUserAccountsList(): array
    {
        return User::query()->with(['roles', 'guru.kelasBinaan', 'kelas'])->orderBy('id', 'asc')->get()->map(function ($usr) {
            $rolesList = $usr->roles->pluck('name')->toArray();
            
            $namaKelas = null;
            $kelasId = null;
            
            if ($usr->kelas) {
                $namaKelas = $usr->kelas->nama_kelas;
                $kelasId = $usr->kelas->id;
            } elseif ($usr->guru && $usr->guru->kelasBinaan) {
                $namaKelas = $usr->guru->kelasBinaan->nama_kelas;
                $kelasId = $usr->guru->kelasBinaan->id;
            }

            return [
                'id' => $usr->id,
                'username' => $usr->username ?? strtolower(explode(' ', $usr->name)[0]),
                'name' => $usr->name,
                'email' => $usr->email,
                'nip' => $usr->guru?->nip,
                'guru_id' => $usr->guru?->id,
                'mapelUtama' => $usr->guru?->mapel_utama,
                'telepon' => $usr->guru?->telepon,
                'kelasId' => $kelasId,
                'namaKelas' => $namaKelas,
                'role' => $usr->roles->first()?->name ?? 'guru',
                'roles' => $rolesList,
                'status' => 'Aktif',
            ];
        })->toArray();
    }

    private function getKelompokMapelList(): array
    {

        return KelompokMapel::query()->orderBy('id', 'asc')->get()->map(function ($k) {
            return [
                'id' => $k->id,
                'kode' => $k->kode ?? ('KMP-' . $k->id),
                'nama' => $k->nama,
                'deskripsi' => $k->deskripsi ?? '',
            ];
        })->toArray();
    }

    /**
     * Store or update School Subject (Mata Pelajaran).
     */
    public function storeMapel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'kode' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'kelompok' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
        ]);

        $kodeMapel = !empty($validated['kode']) 
            ? strtoupper($validated['kode']) 
            : strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $validated['nama']), 0, 8));

        $existing = MataPelajaran::query()->where('kode', '=', $kodeMapel)->first();

        if (!empty($validated['id'])) {
            if ($existing && $existing->id !== (int) $validated['id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['kode' => 'Kode Mapel ' . $kodeMapel . ' sudah digunakan oleh mata pelajaran lain!']);
            }
            MataPelajaran::query()->where('id', '=', $validated['id'])->update([
                'kode' => $kodeMapel,
                'nama' => $validated['nama'],
                'kelompok' => $validated['kelompok'] ?? 'Umum',
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]);
        } else {
            if ($existing) {
                throw \Illuminate\Validation\ValidationException::withMessages(['kode' => 'Mata Pelajaran dengan kode ' . $kodeMapel . ' sudah terdaftar! Silakan gunakan nama/kode lain.']);
            }
            MataPelajaran::query()->create([
                'kode' => $kodeMapel,
                'nama' => $validated['nama'],
                'kelompok' => $validated['kelompok'] ?? 'Umum',
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]);
        }

        return redirect()->back()->with('message', 'Mata Pelajaran berhasil disimpan!');
    }

    /**
     * Delete School Subject.
     */
    public function deleteMapel(int $id): RedirectResponse
    {
        MataPelajaran::query()->where('id', '=', $id)->delete();
        return redirect()->back()->with('message', 'Mata Pelajaran berhasil dihapus!');
    }

    /**
     * Store or update Kelompok Mapel.
     */
    public function storeKelompokMapel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'kode' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
        ]);

        if (!empty($validated['id'])) {
            KelompokMapel::query()->where('id', '=', $validated['id'])->update([
                'kode' => !empty($validated['kode']) ? strtoupper($validated['kode']) : null,
                'nama' => $validated['nama'],
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]);
        } else {
            KelompokMapel::query()->create([
                'kode' => !empty($validated['kode']) ? strtoupper($validated['kode']) : null,
                'nama' => $validated['nama'],
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]);
        }

        return redirect()->back()->with('message', 'Kelompok Mapel berhasil disimpan!');
    }

    /**
     * Delete Kelompok Mapel.
     */
    public function deleteKelompokMapel(int $id): RedirectResponse
    {
        KelompokMapel::query()->where('id', '=', $id)->delete();
        return redirect()->back()->with('message', 'Kelompok Mapel berhasil dihapus!');
    }

    /**
     * Store or update Master Guru.
     */
    public function storeGuruMaster(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'nip' => 'nullable|string|max:100',
            'nama' => 'required|string|max:255',
            'telepon' => 'nullable|string|max:50',
            'mapel_utama' => 'nullable|string|max:255',
        ]);

        if (!empty($validated['id'])) {
            \App\Models\Guru::query()->where('id', '=', $validated['id'])->update([
                'nip' => $validated['nip'],
                'nama' => $validated['nama'],
                'telepon' => $validated['telepon'],
                'mapel_utama' => $validated['mapel_utama'],
            ]);
        } else {
            \App\Models\Guru::query()->create([
                'nip' => $validated['nip'],
                'nama' => $validated['nama'],
                'telepon' => $validated['telepon'],
                'mapel_utama' => $validated['mapel_utama'],
            ]);
        }

        return redirect()->back()->with('message', 'Data Guru Master berhasil disimpan!');
    }

    /**
     * Delete Master Guru.
     */
    public function deleteGuruMaster(int $id): RedirectResponse
    {
        \App\Models\Guru::query()->where('id', '=', $id)->delete();
        return redirect()->back()->with('message', 'Data Guru Master berhasil dihapus!');
    }

    /**
     * Alias methods for storeGuru and deleteGuru.
     */
    public function storeGuru(Request $request): RedirectResponse
    {
        return $this->storeGuruMaster($request);
    }

    public function deleteGuru(int $id): RedirectResponse
    {
        return $this->deleteGuruMaster($id);
    }

    /**
     * Store or update User (Admin, Guru, Wali Kelas, Siswa Account) with Roles.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'username' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'required|email',
            'kelas_id' => 'nullable|integer',
            'guru_id' => 'nullable|integer',
            'roles' => 'required|array|min:1',
            'password' => 'nullable|string|min:6',
        ]);

        $userData = [
            'username' => $validated['username'],
            'name' => $validated['name'] ?? $validated['username'],
            'email' => $validated['email'],
            'kelas_id' => $validated['kelas_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        if (!empty($validated['id'])) {
            $user = User::query()->findOrFail($validated['id']);
            // Check uniqueness except current user
            $existing = User::query()->where('id', '!=', $user->id)
                ->where(function($q) use ($validated) {
                    $q->where('email', $validated['email'])->orWhere('username', $validated['username']);
                })->first();
            if ($existing) {
                 throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Username atau Email sudah terdaftar pada pengguna lain!']);
            }
            $user->update($userData);
        } else {
            if (empty($userData['password'])) {
                $userData['password'] = Hash::make('password123'); // Default fallback
            }
            $existing = User::query()->where('email', $validated['email'])->orWhere('username', $validated['username'])->first();
            if ($existing) {
                 throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Username atau Email sudah terdaftar!']);
            }
            $user = User::create($userData);
        }

        // Sync Spatie Roles
        $user->syncRoles($validated['roles']);

        // Manage Wali Kelas Assignment
        // Wali Kelas logic should now link Kelas with Guru ID, but we only have user_id here.
        // We will link User to Guru if guru_id is provided
        if (!empty($validated['guru_id'])) {
            // Disconnect old guru record assigned to this user
            \App\Models\Guru::query()->where('user_id', '=', $user->id)->update(['user_id' => null]);
            \App\Models\Guru::query()->where('id', '=', $validated['guru_id'], 'and')->update(['user_id' => $user->id]);
            
            // Clear old class binaan assignment for this guru
            Kelas::query()->where('guru_id', '=', $validated['guru_id'], 'and')->update(['guru_id' => null]);
            
            // Set new class if they have the wali_kelas role and a class is selected
            if (in_array('wali_kelas', $validated['roles']) && !empty($validated['kelas_id'])) {
                Kelas::query()->where('id', '=', $validated['kelas_id'])->update(['guru_id' => $validated['guru_id']]);
            }
        }

        return redirect()->back()->with('message', 'Data Akun & Hak Akses berhasil disimpan!');
    }

    /**
     * Store or update Master Class in database.
     */
    public function storeKelas(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'namaKelas' => 'required|string|max:255',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan' => 'nullable|string',
            'waliKelas' => 'nullable',
        ]);

        $jurusanObj = null;
        if (!empty($validated['jurusan'])) {
            $jurusanObj = Jurusan::query()
                ->where('nama', '=', $validated['jurusan'])
                ->orWhere('kode', '=', $validated['jurusan'])
                ->first();
        }

        $guruId = (!empty($validated['waliKelas']) && is_numeric($validated['waliKelas'])) 
            ? (int) $validated['waliKelas'] 
            : null;

        if (!empty($validated['id'])) {
            $existingKelas = Kelas::find($validated['id']);
            if (!$existingKelas) {
                return redirect()->back()->with('error', 'Data kelas tidak ditemukan!');
            }

            $duplicate = Kelas::where('nama_kelas', $validated['namaKelas'])
                ->where('id', '!=', $validated['id'])
                ->first();
            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'namaKelas' => 'Nama kelas ' . $validated['namaKelas'] . ' sudah digunakan oleh kelas lain!'
                ]);
            }

            $existingKelas->update([
                'nama_kelas' => $validated['namaKelas'],
                'tingkat' => $validated['tingkat'],
                'jurusan_id' => $jurusanObj?->id,
                'guru_id' => $guruId,
            ]);
        } else {
            $duplicate = Kelas::where('nama_kelas', $validated['namaKelas'])->first();
            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'namaKelas' => 'Nama kelas ' . $validated['namaKelas'] . ' sudah terdaftar!'
                ]);
            }

            Kelas::create([
                'nama_kelas' => $validated['namaKelas'],
                'tingkat' => $validated['tingkat'],
                'jurusan_id' => $jurusanObj?->id,
                'guru_id' => $guruId,
            ]);
        }

        return redirect()->back()->with('message', 'Master Kelas berhasil disimpan ke database!');
    }

    /**
     * Delete Class from database.
     */
    public function deleteKelas(int $id): RedirectResponse
    {
        Kelas::query()->where('id', '=', $id)->delete();
        return redirect()->back()->with('message', 'Master Kelas berhasil dihapus!');
    }

    /**
     * Store or update Master Jurusan in database.
     */
    public function storeJurusan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'kode' => 'required|string|max:50',
            'nama' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
        ]);

        if (!empty($validated['id'])) {
            Jurusan::query()->where('id', '=', $validated['id'])->update([
                'kode' => strtoupper($validated['kode']),
                'nama' => $validated['nama'],
                'deskripsi' => $validated['deskripsi'] ?? null,
            ]);
        } else {
            Jurusan::updateOrCreate(
                ['kode' => strtoupper($validated['kode'])],
                [
                    'nama' => $validated['nama'],
                    'deskripsi' => $validated['deskripsi'] ?? null,
                ]
            );
        }

        return redirect()->back()->with('message', 'Master Jurusan berhasil disimpan ke database!');
    }

    /**
     * Delete Jurusan from database.
     */
    public function deleteJurusan(int $id): RedirectResponse
    {
        Jurusan::query()->where('id', '=', $id)->delete();
        return redirect()->back()->with('message', 'Master Jurusan berhasil dihapus!');
    }

    /**
     * Store or update Student in database.
     */
    public function storeSiswa(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'nis' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'jenisKelamin' => 'required|string',
            'kelas' => 'required|string',
            'telepon' => 'nullable|string',
            'teleponSiswa' => 'nullable|string',
            'status' => 'nullable|string|in:Aktif,Pindah,Keluar',
            'tanggal_status' => 'nullable|date',
        ]);

        $kelasObj = Kelas::query()->where('nama_kelas', '=', $validated['kelas'])->first();

        $status = $validated['status'] ?? 'Aktif';
        $tanggal_status = $status !== 'Aktif' ? ($validated['tanggal_status'] ?? null) : null;
        $telepon = !empty($validated['telepon']) ? $validated['telepon'] : ($validated['teleponSiswa'] ?? null);
        $nis = !empty($validated['nis']) ? trim($validated['nis']) : null;

        if (!empty($validated['id'])) {
            Siswa::query()->where('id', '=', $validated['id'])->update([
                'nis' => $nis,
                'nama' => $validated['nama'],
                'jenis_kelamin' => $validated['jenisKelamin'],
                'kelas_id' => $kelasObj?->id,
                'telepon' => $telepon,
                'status' => $status,
                'tanggal_status' => $tanggal_status,
            ]);
        } else {
            Siswa::create([
                'nis' => $nis,
                'nama' => $validated['nama'],
                'jenis_kelamin' => $validated['jenisKelamin'],
                'kelas_id' => $kelasObj?->id,
                'telepon' => $telepon,
                'status' => $status,
                'tanggal_status' => $tanggal_status,
            ]);
        }

        return redirect()->back()->with('message', 'Data Siswa berhasil disimpan ke database!');
    }

    /**
     * Import batch students into database atomically.
     */
    public function importSiswaBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.nis' => 'nullable|string|max:50',
            'rows.*.nama' => 'required|string|max:255',
            'rows.*.jenisKelamin' => 'nullable|string',
            'rows.*.kelas' => 'nullable|string',
            'rows.*.telepon' => 'nullable|string',
        ]);

        $insertedCount = 0;

        DB::transaction(function () use ($validated, &$insertedCount) {
            $kelasList = Kelas::all();

            foreach ($validated['rows'] as $row) {
                $nama = trim($row['nama']);
                if (empty($nama)) continue;

                $jenisKelaminInput = !empty($row['jenisKelamin']) ? trim($row['jenisKelamin']) : 'Laki-laki';
                $jkClean = strtolower(str_replace([' ', '-'], '', $jenisKelaminInput));
                if (str_contains($jkClean, 'laki') || str_contains($jkClean, 'pria') || str_contains($jkClean, 'male') || $jkClean === 'l') {
                    $jenisKelamin = 'Laki-laki';
                } elseif (str_contains($jkClean, 'perempuan') || str_contains($jkClean, 'wanita') || str_contains($jkClean, 'female') || $jkClean === 'p') {
                    $jenisKelamin = 'Perempuan';
                } else {
                    $jenisKelamin = 'Laki-laki';
                }

                $namaKelasInput = !empty($row['kelas']) ? trim($row['kelas']) : '';
                $kelasObj = null;

                if ($namaKelasInput) {
                    $kelasObj = $kelasList->first(function ($k) use ($namaKelasInput) {
                        return strtolower($k->nama_kelas) === strtolower($namaKelasInput);
                    });

                    if (!$kelasObj) {
                        $tingkat = 'X';
                        $upperKelas = strtoupper($namaKelasInput);
                        if (str_starts_with($upperKelas, 'XII')) {
                            $tingkat = 'XII';
                        } elseif (str_starts_with($upperKelas, 'XI')) {
                            $tingkat = 'XI';
                        }

                        $kelasObj = Kelas::create([
                            'nama_kelas' => $upperKelas,
                            'tingkat' => $tingkat,
                        ]);
                        $kelasList->push($kelasObj);
                    }
                }

                $matchAttributes = [];
                if (!empty($row['nis'])) {
                    $matchAttributes = ['nis' => trim($row['nis'])];
                } else {
                    $matchAttributes = ['nama' => $nama, 'kelas_id' => $kelasObj?->id];
                }

                Siswa::updateOrCreate(
                    $matchAttributes,
                    [
                        'nis' => !empty($row['nis']) ? trim($row['nis']) : null,
                        'nama' => $nama,
                        'jenis_kelamin' => $jenisKelamin,
                        'kelas_id' => $kelasObj?->id,
                        'telepon' => !empty($row['telepon']) ? trim($row['telepon']) : null,
                    ]
                );

                $insertedCount++;
            }
        });

        return redirect()->back()->with('message', "Berhasil mengimpor {$insertedCount} data siswa ke database!");
    }

    /**
     * Delete Student from database.
     */
    public function deleteSiswa(int $id): RedirectResponse
    {
        Siswa::query()->where('id', '=', $id)->delete();
        return redirect()->back()->with('message', 'Data Siswa berhasil dihapus!');
    }

    /**
     * Delete multiple students atomically from database.
     */
    public function deleteSiswaBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:siswas,id',
        ]);

        $deletedCount = Siswa::query()->whereIn('id', $validated['ids'], 'and', false)->delete();

        return redirect()->back()->with('message', "{$deletedCount} data siswa berhasil dihapus dari database!");
    }

    /**
     * Reset User Password in database.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'userId' => 'required|integer|exists:users,id',
            'newPassword' => 'required|string|min:6',
        ]);

        $user = User::query()->where('id', '=', $validated['userId'])->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($validated['newPassword']),
            ]);
        }

        return redirect()->back()->with('message', 'Password User berhasil direset!');
    }

    /**
     * Delete User Account from database.
     */
    public function deleteUser(int $id): RedirectResponse
    {
        $user = User::query()->where('id', '=', $id)->first();
        if ($user) {
            // Unlink homeroom class if user is a homeroom teacher
            $guru = $user->guru;
            if ($guru) {
                Kelas::query()->where('guru_id', '=', $guru->id)->update(['guru_id' => null]);
            }
            User::destroy($id);
        }
        return redirect()->back()->with('message', 'Akun User berhasil dihapus dari database!');
    }

    /**
     * Download a CSV import template for students (Excel-compatible UTF-8 BOM CSV).
     */
    public function downloadSiswaTemplate(): HttpResponse
    {
        $bom = "\xEF\xBB\xBF"; // UTF-8 BOM so Excel opens correctly
        $rows = [
            ['NIS', 'Nama Lengkap', 'Jenis Kelamin', 'Kelas', 'No Telepon'],
            ['1001', 'Budi Santoso', 'Laki-laki', 'X RPL 1', '081234567890'],
            ['1002', 'Siti Aminah', 'Perempuan', 'XI TKJ 2', '082345678901'],
            ['1003', 'Dewi Lestari', 'Perempuan', 'XII DKV 1', '083456789012'],
        ];

        $output = fopen('php://temp', 'w+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = $bom . stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_siswa.csv"',
        ]);
    }

    /**
     * Import students from an uploaded .xlsx or .csv file.
     * Parses .xlsx natively (no external package) via ZIP/XML extraction.
     */
    public function importSiswaFile(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $file = $request->file('file');
        FileSecurityService::validateUploadedFile($file, ['xlsx', 'xls', 'csv']);
        $ext = strtolower($file->getClientOriginalExtension());
        $rows     = [];

        if ($ext === 'csv') {
            // Parse CSV directly
            $handle = fopen($file->getRealPath(), 'r');
            $header = null;
            while (($line = fgetcsv($handle, 1000)) !== false) {
                if (!$header) { $header = array_map('strtolower', $line); continue; }
                $rows[] = $line;
            }
            fclose($handle);
        } else {
            // Parse .xlsx (ZIP with XML inside)
            $zip = new \ZipArchive();
            if ($zip->open($file->getRealPath()) !== true) {
                return redirect()->back()->withErrors(['file' => 'File .xlsx tidak dapat dibaca.']);
            }

            $sharedStrings = [];
            $ssXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($ssXml) {
                $ss = simplexml_load_string($ssXml, 'SimpleXMLElement', LIBXML_NONET);
                foreach ($ss->si as $si) {
                    $texts = [];
                    foreach ($si->r as $r) {
                        $texts[] = (string) $r->t;
                    }
                    $sharedStrings[] = $texts ? implode('', $texts) : (string) $si->t;
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            if (!$sheetXml) {
                return redirect()->back()->withErrors(['file' => 'Sheet di dalam file .xlsx tidak ditemukan.']);
            }

            $sheet    = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
            $allRows  = [];
            foreach ($sheet->sheetData->row as $row) {
                $rowData = array_fill(0, 5, '');
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    $colLetters = preg_replace('/[0-9]/', '', $ref);
                    $colIdx = 0;
                    for ($i = 0; $i < strlen($colLetters); $i++) {
                        $colIdx = $colIdx * 26 + (ord(strtoupper($colLetters[$i])) - ord('A') + 1);
                    }
                    $colIdx -= 1;

                    $type  = (string) $cell['t'];
                    $value = (string) ($cell->v ?? '');
                    if ($type === 's' && isset($sharedStrings[(int) $value])) {
                        $value = $sharedStrings[(int) $value];
                    } elseif ($type === 'inlineStr' || ! isset($cell->v)) {
                        $value = (string) ($cell->is->t ?? $cell->v ?? '');
                    }

                    if ($colIdx >= 0) {
                        $rowData[$colIdx] = $value;
                    }
                }
                $allRows[] = $rowData;
            }
            // Skip header row
            $rows = array_slice($allRows, 1);
        }

        // Determine column indexes from header (for CSV) or assume fixed order for xlsx
        // Fixed order: NIS, Nama, Jenis Kelamin, Kelas, Telepon
        $insertedCount = 0;
        DB::transaction(function () use ($rows, &$insertedCount) {
            $kelasList = Kelas::all();

            foreach ($rows as $cols) {
                // Pad array to avoid undefined index
                while (count($cols) < 5) $cols[] = '';

                $nis           = trim($cols[0] ?? '');
                $nama          = trim($cols[1] ?? '');
                $jenisKelamin  = trim($cols[2] ?? 'Laki-laki') ?: 'Laki-laki';
                $namaKelasInput = trim($cols[3] ?? '');
                $telepon       = trim($cols[4] ?? '');

                if (!$nama) continue;

                // Normalize jenis kelamin
                $jkLower = strtolower(str_replace([' ', '-'], '', $jenisKelamin));
                if (str_contains($jkLower, 'perempuan') || str_contains($jkLower, 'wanita') || str_contains($jkLower, 'female') || $jkLower === 'p') {
                    $jenisKelamin = 'Perempuan';
                } else {
                    $jenisKelamin = 'Laki-laki';
                }

                // Find or create Kelas
                $kelasObj = null;
                if ($namaKelasInput) {
                    $kelasObj = $kelasList->first(fn($k) => strtolower($k->nama_kelas) === strtolower($namaKelasInput));
                    if (!$kelasObj) {
                        $upperKelas = strtoupper($namaKelasInput);
                        $tingkat = 'X';
                        if (str_starts_with($upperKelas, 'XII')) $tingkat = 'XII';
                        elseif (str_starts_with($upperKelas, 'XI')) $tingkat = 'XI';

                        $kelasObj = Kelas::create(['nama_kelas' => $upperKelas, 'tingkat' => $tingkat]);
                        $kelasList->push($kelasObj);
                    }
                }

                $matchAttributes = [];
                if (!empty($nis)) {
                    $matchAttributes = ['nis' => $nis];
                } else {
                    $matchAttributes = ['nama' => $nama, 'kelas_id' => $kelasObj?->id];
                }

                Siswa::updateOrCreate(
                    $matchAttributes,
                    [
                        'nis'          => $nis ?: null,
                        'nama'         => $nama,
                        'jenis_kelamin' => $jenisKelamin,
                        'kelas_id'     => $kelasObj?->id,
                        'telepon'      => $telepon ?: null,
                    ]
                );
                $insertedCount++;
            }
        });

        return redirect()->back()->with('message', "Berhasil mengimpor {$insertedCount} data siswa dari file Excel/CSV!");
    }

    private function getTahunAjaranList(): array
    {
        return TahunAjaran::query()
            ->orderBy('is_aktif', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($ta) {
                return [
                    'id' => $ta->id,
                    'kode' => $ta->kode,
                    'is_aktif' => (bool) $ta->is_aktif,
                    'created_at' => $ta->created_at?->format('d M Y') ?? '-',
                ];
            })
            ->toArray();
    }

    /**
     * Store or update Tahun Ajaran.
     */
    public function storeTahunAjaran(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'kode' => 'required|string|max:50',
            'is_aktif' => 'nullable|boolean',
        ]);

        $isAktif = !empty($validated['is_aktif']);

        DB::transaction(function () use ($validated, $isAktif) {
            if ($isAktif) {
                TahunAjaran::query()->update(['is_aktif' => false]);
            }

            if (!empty($validated['id'])) {
                $ta = TahunAjaran::findOrFail($validated['id']);
                $ta->update([
                    'kode' => trim($validated['kode']),
                    'is_aktif' => $isAktif,
                ]);
            } else {
                TahunAjaran::create([
                    'kode' => trim($validated['kode']),
                    'is_aktif' => $isAktif,
                ]);
            }
        });

        return redirect()->back()->with('message', 'Tahun Pelajaran berhasil disimpan!');
    }

    /**
     * Set a Tahun Ajaran as active / default.
     */
    public function setDefaultTahunAjaran(int $id): RedirectResponse
    {
        DB::transaction(function () use ($id) {
            TahunAjaran::query()->update(['is_aktif' => false]);
            $ta = TahunAjaran::findOrFail($id);
            $ta->update(['is_aktif' => true]);
        });

        return redirect()->back()->with('message', 'Tahun Pelajaran berhasil dijadikan default aktif sistem!');
    }

    /**
     * Delete a Tahun Ajaran.
     */
    public function deleteTahunAjaran(int $id): RedirectResponse
    {
        $ta = TahunAjaran::findOrFail($id);

        if ($ta->is_aktif) {
            return redirect()->back()->withErrors(['error' => 'Tahun Pelajaran yang sedang aktif tidak dapat dihapus! Silakan jadikan tahun pelajaran lain sebagai default terlebih dahulu.']);
        }

        $ta->delete();

        return redirect()->back()->with('message', 'Tahun Pelajaran berhasil dihapus!');
    }
}
