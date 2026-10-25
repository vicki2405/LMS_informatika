<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\NilaiTp;
use App\Models\Siswa;
use App\Models\KasusSiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaliKelasApiController extends Controller
{
    private function getHomeroomKelas(Request $request)
    {
        $user = $request->user();
        $guru = $user?->guru ?? \App\Models\Guru::query()->where('user_id', '=', $user?->id)->first();
        $kelas = $guru?->id ? Kelas::query()->where('guru_id', '=', $guru->id)->first() : null;

        return $kelas ?? ($user?->kelas_id ? Kelas::find($user->kelas_id) : null);
    }

    public function getSiswa(Request $request): JsonResponse
    {
        $kelas = $this->getHomeroomKelas($request);
        $siswas = $kelas ? Siswa::where('kelas_id', $kelas->id)->get() : [];

        return response()->json([
            'success' => true,
            'kelas_binaan' => $kelas?->nama_kelas,
            'data' => $siswas
        ]);
    }

    public function getLedgerNilai(Request $request): JsonResponse
    {
        $kelas = $this->getHomeroomKelas($request);
        $siswas = $kelas ? Siswa::where('kelas_id', $kelas->id)->get() : [];

        $siswaIds = $siswas->pluck('id');
        $allScores = NilaiTp::with('tp')->whereIn('siswa_id', $siswaIds)->get()->groupBy('siswa_id');

        $data = $siswas->map(function ($s) use ($allScores) {
            $scores = $allScores->get($s->id)?->pluck('nilai', 'tp_id')->toArray() ?? [];
            $avg = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
            return [
                'siswa_id' => $s->id,
                'nama' => $s->nama,
                'scores' => $scores,
                'nilai_akhir' => $avg,
                'status' => $avg >= 75 ? 'Tuntas' : 'Belum Tuntas'
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getKasus(Request $request): JsonResponse
    {
        $kelas = $this->getHomeroomKelas($request);
        if (!$kelas) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $kasus = KasusSiswa::with('siswa')
            ->whereHas('siswa', function ($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id);
            })
            ->orderBy('tanggal', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $kasus]);
    }

    public function storeKasus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'siswa_id' => 'required|integer',
            'tanggal' => 'required|date',
            'nama_kasus' => 'required|string|max:255',
            'kategori' => 'required|string|in:Ringan,Sedang,Berat',
            'poin' => 'required|integer|min:0',
            'tindakan' => 'nullable|string',
            'status' => 'required|string|in:Dalam Pemantauan,Selesai',
            'catatan' => 'nullable|string',
        ]);

        $user = $request->user();
        $kelas = $this->getHomeroomKelas($request);

        if ($kelas && ! $user->hasRole('admin')) {
            $siswa = Siswa::find($validated['siswa_id']);
            if (! $siswa || $siswa->kelas_id !== $kelas->id) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak: Siswa ini bukan anggota kelas binaan Anda'], 403);
            }
            if (! empty($validated['id'])) {
                $existingKasus = KasusSiswa::with('siswa')->find($validated['id']);
                if ($existingKasus && $existingKasus->siswa && $existingKasus->siswa->kelas_id !== $kelas->id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak: Kasus ini bukan milik siswa kelas binaan Anda'], 403);
                }
            }
        }

        $kasus = KasusSiswa::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            $validated
        );

        return response()->json(['success' => true, 'message' => 'Kasus siswa berhasil disimpan', 'data' => $kasus]);
    }

    public function deleteKasus(int $id): JsonResponse
    {
        $user = request()->user();
        $kelas = $this->getHomeroomKelas(request());

        $kasus = KasusSiswa::with('siswa')->find($id);
        if ($kasus) {
            if ($kelas && ! $user->hasRole('admin')) {
                if ($kasus->siswa && $kasus->siswa->kelas_id !== $kelas->id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak: Kasus ini bukan milik siswa kelas binaan Anda'], 403);
                }
            }
            $kasus->delete();
        }

        return response()->json(['success' => true, 'message' => 'Kasus siswa berhasil dihapus']);
    }
}
