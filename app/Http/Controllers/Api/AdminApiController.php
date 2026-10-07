<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuruRequest;
use App\Http\Requests\Admin\StoreSiswaRequest;
use App\Http\Resources\SiswaResource;
use App\Http\Resources\UserResource;
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

        return response()->json([
            'success' => true,
            'message' => 'Data guru berhasil disimpan',
            'data' => new UserResource($user),
        ]);
    }

    public function deleteGuru(int $id): JsonResponse
    {
        User::destroy($id);
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

        $siswa = Siswa::updateOrCreate(['id' => $request->id], $validated);
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
