<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuruRequest;
use App\Http\Requests\Admin\StoreSiswaRequest;
use App\Http\Resources\SiswaResource;
use App\Http\Resources\UserResource;
use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AdminApiController extends Controller
{
    public function getGuru(): JsonResponse
    {
        $guruList = User::with('roles')->role(['guru', 'wali_kelas'])->paginate(50);
        return response()->json([
            'success' => true,
            'data' => UserResource::collection($guruList),
        ]);
    }

    public function storeGuru(StoreGuruRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $validated['nama'])[0] ?? 'guru'));
        if (empty($baseUsername)) {
            $baseUsername = 'guru';
        }
        $username = $baseUsername;
        $counter = 1;
        while (User::where('username', $username)->where('email', '!=', $validated['email'])->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        $user = User::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['nama'],
                'username' => $username,
                'password' => !empty($validated['password']) ? Hash::make($validated['password']) : Hash::make('password123'),
            ]
        );
        $user->assignRole('guru');

        // Ensure teacher record exists in gurus table
        Guru::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nama' => $user->name,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Data guru berhasil disimpan',
            'data' => new UserResource($user),
        ]);
    }

    public function deleteGuru(int $id): JsonResponse
    {
        $guru = Guru::find($id);
        if ($guru) {
            $userId = $guru->user_id;
            $guru->delete();
            if ($userId) {
                User::destroy($userId);
            }
        } else {
            $user = User::find($id);
            if ($user) {
                if ($user->guru) {
                    $user->guru->delete();
                }
                $user->delete();
            }
        }
        return response()->json(['success' => true, 'message' => 'Data guru berhasil dihapus']);
    }

    public function getSiswa(): JsonResponse
    {
        $siswaList = Siswa::with('kelas')->paginate(50);
        return response()->json([
            'success' => true,
            'data' => SiswaResource::collection($siswaList),
        ]);
    }

    public function storeSiswa(StoreSiswaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $jk = $validated['jenis_kelamin'] ?? 'Laki-laki';
        if ($jk === 'L' || $jk === 'Laki-laki') {
            $jk = 'Laki-laki';
        } else {
            $jk = 'Perempuan';
        }

        $siswaData = [
            'nama' => $validated['nama'],
            'jenis_kelamin' => $jk,
            'kelas_id' => $validated['kelas_id'],
            'telepon' => $validated['telepon'] ?? null,
            'status' => $validated['status'] ?? 'Aktif',
            'tanggal_status' => $validated['tanggal_status'] ?? null,
        ];

        $siswa = Siswa::updateOrCreate(['id' => $request->id], $siswaData);
        return response()->json([
            'success' => true,
            'message' => 'Data siswa berhasil disimpan',
            'data' => new SiswaResource($siswa->load('kelas')),
        ]);
    }

    public function deleteSiswa(int $id): JsonResponse
    {
        Siswa::destroy($id);
        return response()->json(['success' => true, 'message' => 'Data siswa berhasil dihapus']);
    }

    public function getKelas(): JsonResponse
    {
        $kelasList = Kelas::with('jurusan')->paginate(50);
        return response()->json(['success' => true, 'data' => $kelasList]);
    }

    public function getJurusan(): JsonResponse
    {
        $jurusanList = Jurusan::paginate(50);
        return response()->json(['success' => true, 'data' => $jurusanList]);
    }

    public function getUsers(): JsonResponse
    {
        $users = User::with('roles')->paginate(50);
        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
        ]);
    }

    public function getTahunAjaran(): JsonResponse
    {
        $tahunAjaran = \App\Models\TahunAjaran::orderBy('is_aktif', 'desc')->orderBy('id', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $tahunAjaran,
        ]);
    }

    public function storeTahunAjaran(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'kode' => 'required|string|max:50',
            'is_aktif' => 'nullable|boolean',
        ]);

        $isAktif = !empty($validated['is_aktif']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $isAktif, &$ta) {
            if ($isAktif) {
                \App\Models\TahunAjaran::query()->update(['is_aktif' => false]);
            }

            if (!empty($validated['id'])) {
                $ta = \App\Models\TahunAjaran::findOrFail($validated['id']);
                $ta->update([
                    'kode' => trim($validated['kode']),
                    'is_aktif' => $isAktif,
                ]);
            } else {
                $ta = \App\Models\TahunAjaran::create([
                    'kode' => trim($validated['kode']),
                    'is_aktif' => $isAktif,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Tahun Pelajaran berhasil disimpan',
            'data' => $ta,
        ]);
    }

    public function setDefaultTahunAjaran(int $id): JsonResponse
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($id, &$ta) {
            \App\Models\TahunAjaran::query()->update(['is_aktif' => false]);
            $ta = \App\Models\TahunAjaran::findOrFail($id);
            $ta->update(['is_aktif' => true]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Tahun Pelajaran berhasil dijadikan default aktif sistem',
            'data' => $ta,
        ]);
    }

    public function deleteTahunAjaran(int $id): JsonResponse
    {
        $ta = \App\Models\TahunAjaran::findOrFail($id);
        if ($ta->is_aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun Pelajaran yang sedang aktif tidak dapat dihapus',
            ], 422);
        }

        $ta->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tahun Pelajaran berhasil dihapus',
        ]);
    }
}
