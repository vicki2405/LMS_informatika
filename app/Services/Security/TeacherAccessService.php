<?php

namespace App\Services\Security;

use App\Models\CbtBankSoal;
use App\Models\CbtJadwal;
use App\Models\CbtNilai;
use App\Models\CbtSesi;
use App\Models\CbtSoal;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Materi;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeacherAccessService
{
    public static function resolveGuru(?User $user): ?Guru
    {
        if (! $user) {
            return null;
        }

        return $user->guru ?: Guru::query()->where('user_id', $user->id)->first();
    }

    public static function isAdmin(?User $user): bool
    {
        return (bool) $user?->hasRole('admin');
    }

    public static function kelasIds(?Guru $guru): array
    {
        if (! $guru) {
            return [];
        }

        return DB::table('guru_kelas')
            ->where('guru_id', $guru->id)
            ->pluck('kelas_id')
            ->merge(Jadwal::query()->where('guru_id', $guru->id)->pluck('kelas_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function mapelNames(?Guru $guru): array
    {
        if (! $guru) {
            return [];
        }

        $scheduleNames = Jadwal::query()
            ->where('guru_id', $guru->id)
            ->pluck('mata_pelajaran');

        $utamaNames = collect(explode(',', (string) $guru->mapel_utama))
            ->map(fn ($name) => trim($name));

        return $scheduleNames
            ->merge($utamaNames)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function mapelIds(?Guru $guru): array
    {
        $names = self::mapelNames($guru);

        if (empty($names)) {
            return [];
        }

        return DB::table('mata_pelajarans')
            ->whereIn('nama', $names)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function canAccessKelas(?User $user, ?Guru $guru, ?int $kelasId): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (! $guru || ! $kelasId) {
            return false;
        }

        return in_array((int) $kelasId, self::kelasIds($guru), true);
    }

    public static function canAccessSiswa(?User $user, ?Guru $guru, Siswa $siswa): bool
    {
        return self::canAccessKelas($user, $guru, $siswa->kelas_id);
    }

    public static function canAccessBankSoal(?User $user, ?Guru $guru, CbtBankSoal $bankSoal): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (! $guru) {
            return false;
        }

        return $bankSoal->guru_id === null || (int) $bankSoal->guru_id === (int) $guru->id;
    }

    public static function canAccessJadwal(?User $user, ?Guru $guru, CbtJadwal $jadwal): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (! $guru) {
            return false;
        }

        return $jadwal->guru_id === null || (int) $jadwal->guru_id === (int) $guru->id;
    }

    public static function canAccessSoal(?User $user, ?Guru $guru, CbtSoal $soal): bool
    {
        $bankSoal = $soal->relationLoaded('bankSoal')
            ? $soal->bankSoal
            : $soal->bankSoal()->first();

        return $bankSoal ? self::canAccessBankSoal($user, $guru, $bankSoal) : false;
    }

    public static function canAccessNilai(?User $user, ?Guru $guru, CbtNilai $nilai): bool
    {
        $jadwal = $nilai->relationLoaded('jadwal')
            ? $nilai->jadwal
            : $nilai->jadwal()->withTrashed()->first();

        return $jadwal ? self::canAccessJadwal($user, $guru, $jadwal) : false;
    }

    public static function canAccessSesi(?User $user, ?Guru $guru, CbtSesi $sesi): bool
    {
        $jadwal = $sesi->relationLoaded('jadwal')
            ? $sesi->jadwal
            : $sesi->jadwal()->withTrashed()->first();

        return $jadwal ? self::canAccessJadwal($user, $guru, $jadwal) : false;
    }

    public static function canAccessMateri(?User $user, ?Guru $guru, Materi $materi): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (! $guru) {
            return false;
        }

        if ($materi->guru_id !== null) {
            return (int) $materi->guru_id === (int) $guru->id;
        }

        if ($materi->kelas_id !== null) {
            return self::canAccessKelas($user, $guru, (int) $materi->kelas_id);
        }

        if ($materi->mapel_id !== null) {
            return in_array((int) $materi->mapel_id, self::mapelIds($guru), true);
        }

        return false;
    }

    public static function canAccessTp(?User $user, ?Guru $guru, TujuanPembelajaran $tp): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (! $guru) {
            return false;
        }

        $allowedMapels = self::mapelNames($guru);

        if (empty($allowedMapels) || $tp->mapel === null) {
            return true;
        }

        return in_array(trim((string) $tp->mapel), $allowedMapels, true);
    }
}
