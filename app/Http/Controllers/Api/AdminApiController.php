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

        $user = User::updateOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['nama'],
                'username' => strtolower(explode(' ', $validated['nama'])[0]),
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
}
